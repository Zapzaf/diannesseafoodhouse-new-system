<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\WastageItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryDeletionService
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Delete a delivery and fully reverse every inventory movement it caused,
     * including any production orders (and their scrap) created from it.
     */
    public function delete(Delivery $delivery, int $actorId): void
    {
        DB::transaction(function () use ($delivery, $actorId): void {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->with(['items.item', 'items.sourceItem'])
                ->findOrFail($delivery->id);

            $reference = $delivery->reference_number ?: ('#'.$delivery->id);

            $productions = ProductionOrder::query()
                ->whereHas('inputs', fn ($q) => $q->whereIn('delivery_item_id', $delivery->items->pluck('id')))
                ->with(['inputs.item', 'outputs.item', 'wastageReports.items'])
                ->lockForUpdate()
                ->get();

            $this->assertReversalIsPossible($delivery, $productions);

            foreach ($productions as $production) {
                $this->reverseProduction($production, $reference, $actorId);
                $production->delete(); // cascades inputs, outputs, wastage reports/items
            }

            $this->reverseDeliveryMovements($delivery, $reference, $actorId);

            $delivery->delete(); // cascades delivery items and transfers
        });
    }

    /**
     * Verify every required stock deduction is still available so the whole
     * reversal either applies completely or not at all.
     */
    private function assertReversalIsPossible(Delivery $delivery, $productions): void
    {
        $requiredDeductions = [];

        $addDeduction = function (?Item $item, float $quantity) use (&$requiredDeductions): void {
            if (! $item || $quantity <= 0) {
                return;
            }
            $requiredDeductions[$item->id] = ($requiredDeductions[$item->id] ?? 0) + $quantity;
        };

        foreach ($productions as $production) {
            foreach ($production->outputs as $output) {
                $addDeduction($output->item, (float) $output->quantity_produced);
            }
            foreach ($production->wastageReports as $report) {
                foreach ($report->items as $wastageItem) {
                    if ($wastageItem->convert_to_item_id && (float) $wastageItem->converted_quantity > 0) {
                        $addDeduction(Item::find($wastageItem->convert_to_item_id), (float) $wastageItem->converted_quantity);
                    }
                }
            }
        }

        if ($delivery->status === 'received') {
            foreach ($delivery->items as $deliveryItem) {
                if ($deliveryItem->allocated_to === 'inventory') {
                    $addDeduction($deliveryItem->item, (float) $deliveryItem->quantity);
                }
            }
        }

        $shortages = [];
        foreach ($requiredDeductions as $itemId => $required) {
            $item = Item::query()->find($itemId);
            if ($item && (float) $item->quantity < $required) {
                $shortages[] = sprintf(
                    '%s (needs %s to reverse, only %s in stock)',
                    $item->name,
                    number_format($required, 2),
                    number_format((float) $item->quantity, 2)
                );
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages([
                'delivery' => 'Cannot delete this delivery — reversing it would require more stock than is available for: '
                    .implode('; ', $shortages)
                    .'. Adjust those item quantities first.',
            ]);
        }
    }

    private function reverseProduction(ProductionOrder $production, string $reference, int $actorId): void
    {
        $suffix = 'PRODUCTION '.$production->id.' — DELIVERY DELETED: '.$reference;

        foreach ($production->wastageReports as $report) {
            foreach ($report->items as $wastageItem) {
                $this->reverseWastageItem($wastageItem, $suffix, $actorId);
            }
        }

        // Remove finished products that the production added to inventory.
        foreach ($production->outputs as $output) {
            if (! $output->item || (float) $output->quantity_produced <= 0) {
                continue;
            }
            $this->applyDecrease($output->item->id, (float) $output->quantity_produced, 'REVERSAL OF PRODUCTION OUTPUT: '.$suffix, $actorId);
        }

        // Restore raw materials pulled from stock. Inputs that came from the
        // delivery itself never deducted stock, so nothing to restore there.
        foreach ($production->inputs as $input) {
            if ($input->delivery_item_id !== null || ! $input->item || ! $input->item->exists || (float) $input->quantity_used <= 0) {
                continue;
            }
            $this->applyIncrease($input->item->id, (float) $input->quantity_used, 'RETURN OF PRODUCTION INPUT: '.$suffix, $actorId);
        }
    }

    private function reverseWastageItem(WastageItem $wastageItem, string $suffix, int $actorId): void
    {
        // Undo scrap conversions that added converted stock.
        if ($wastageItem->convert_to_item_id && (float) $wastageItem->converted_quantity > 0) {
            $this->applyDecrease((int) $wastageItem->convert_to_item_id, (float) $wastageItem->converted_quantity, 'REVERSAL OF SCRAP CONVERSION: '.$suffix, $actorId);
        }

        // Undo scrap losses that deducted a source item.
        if ($wastageItem->item_id && (float) $wastageItem->quantity_lost > 0) {
            $this->applyIncrease((int) $wastageItem->item_id, (float) $wastageItem->quantity_lost, 'REVERSAL OF SCRAP LOSS: '.$suffix, $actorId);
        }
    }

    private function reverseDeliveryMovements(Delivery $delivery, string $reference, int $actorId): void
    {
        if ($delivery->status !== 'received') {
            return; // pending deliveries never touched inventory
        }

        // Deduct the quantities the delivery added to destination items.
        foreach ($delivery->items as $deliveryItem) {
            if ($deliveryItem->allocated_to !== 'inventory' || ! $deliveryItem->item || (float) $deliveryItem->quantity <= 0) {
                continue;
            }
            $this->applyDecrease($deliveryItem->item->id, (float) $deliveryItem->quantity, 'REVERSAL OF DELIVERY: '.$reference, $actorId);
        }

        // Branch transfers deducted the source branch — give it back.
        if ($delivery->source_branch_id) {
            foreach ($delivery->items as $deliveryItem) {
                if (! $deliveryItem->sourceItem || (float) $deliveryItem->quantity <= 0) {
                    continue;
                }
                $this->applyIncrease($deliveryItem->sourceItem->id, (float) $deliveryItem->quantity, 'REVERSAL OF BRANCH TRANSFER: '.$reference, $actorId);
            }
        }
    }

    private function applyIncrease(int $itemId, float $quantity, string $reason, int $actorId): void
    {
        $item = Item::query()->lockForUpdate()->find($itemId);
        if (! $item) {
            return;
        }

        $beginning = (float) $item->quantity;
        $this->inventoryService->increase($item, $quantity);
        $this->recordTransaction($item, 'in', $quantity, $beginning, $reason, $actorId);
    }

    private function applyDecrease(int $itemId, float $quantity, string $reason, int $actorId): void
    {
        $item = Item::query()->lockForUpdate()->find($itemId);
        if (! $item) {
            return;
        }

        $beginning = (float) $item->quantity;
        $this->inventoryService->decrease($item, $quantity);
        $this->recordTransaction($item, 'out', $quantity, $beginning, $reason, $actorId);
    }

    private function recordTransaction(Item $item, string $type, float $quantity, float $beginning, string $reason, int $actorId): void
    {
        InventoryTransaction::create([
            'item_id' => $item->id,
            'branch_id' => $item->branch_id,
            'type' => $type,
            'quantity' => $quantity,
            'beginning_quantity' => $beginning,
            'remaining_quantity' => (float) $item->quantity,
            'transaction_price' => $item->unit_price ? (float) $item->unit_price : null,
            'transaction_date' => now(),
            'reason' => $reason,
            'status' => 'approved',
            'created_by' => $actorId,
        ]);
    }
}
