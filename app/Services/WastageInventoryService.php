<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\WastageItem;
use App\Models\WastageReport;
use App\Support\InventoryUnit;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WastageInventoryService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function createFromProduction(ProductionOrder $production, iterable $rows, int $creatorId, string $errorKey = 'items'): ?WastageReport
    {
        $rows = collect($rows)
            ->filter(fn (array $row): bool => isset($row['quantity_lost']) && (float) $row['quantity_lost'] > 0)
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $this->ensureConvertedItemsBelongToProductionBranch($production, $rows, $errorKey);

        $sourceItem = $this->resolveProductionSourceItem($production, $errorKey);
        $this->ensureQuantityLostUnitsMatchSource($rows, $sourceItem, $errorKey);

        $report = WastageReport::create([
            'production_order_id' => $production->id,
            'branch_id' => $production->branch_id,
            'created_by' => $creatorId,
        ]);

        foreach ($rows as $row) {
            $wastageItem = WastageItem::create([
                'wastage_report_id' => $report->id,
                'item_id' => $sourceItem->id,
                'scrap_name' => $row['scrap_name'] ?? null,
                'quantity_lost' => $row['quantity_lost'],
                'quantity_lost_unit' => $row['quantity_lost_unit'] ?? $sourceItem->unit,
                'reason' => $row['reason'] ?? null,
                'convert_to_item_id' => $row['convert_to_item_id'] ?? null,
                'converted_quantity' => $row['converted_quantity'] ?? null,
            ]);

            $this->deductScrapSource($sourceItem, $wastageItem, $production, $creatorId, $errorKey);

            if ($wastageItem->convert_to_item_id && $wastageItem->converted_quantity) {
                $this->addConvertedInventory($wastageItem, $production, $creatorId);
            }
        }

        return $report;
    }

    public function createConversionsFromProduction(ProductionOrder $production, iterable $rows, int $creatorId, string $errorKey = 'items'): ?WastageReport
    {
        $rows = collect($rows)
            ->filter(fn (array $row): bool => ! empty($row['convert_to_item_id'])
                && isset($row['converted_quantity'])
                && (float) $row['converted_quantity'] > 0)
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $this->ensureConvertedItemsBelongToProductionBranch($production, $rows, $errorKey);

        $report = WastageReport::create([
            'production_order_id' => $production->id,
            'branch_id' => $production->branch_id,
            'created_by' => $creatorId,
        ]);

        foreach ($rows as $row) {
            $convertedItem = Item::query()
                ->whereKey((int) $row['convert_to_item_id'])
                ->where('branch_id', $production->branch_id)
                ->firstOrFail();

            $wastageItem = WastageItem::create([
                'wastage_report_id' => $report->id,
                'item_id' => null,
                'scrap_name' => null,
                'quantity_lost' => $row['converted_quantity'],
                'quantity_lost_unit' => $convertedItem->unit,
                'reason' => null,
                'convert_to_item_id' => $convertedItem->id,
                'converted_quantity' => $row['converted_quantity'],
            ]);

            $this->addConvertedInventory($wastageItem, $production, $creatorId);
        }

        return $report;
    }

    public function ensureConvertedItemsBelongToProductionBranch(ProductionOrder $production, iterable $rows, string $errorKey = 'items'): void
    {
        $convertedItemIds = collect($rows)
            ->pluck('convert_to_item_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($convertedItemIds->isEmpty()) {
            return;
        }

        $branchItemCount = Item::query()
            ->whereIn('id', $convertedItemIds)
            ->where('branch_id', $production->branch_id)
            ->count();

        if ($branchItemCount !== $convertedItemIds->count()) {
            throw ValidationException::withMessages([
                $errorKey => 'All converted waste items must belong to the production branch.',
            ]);
        }
    }

    public function resolveProductionSourceItem(ProductionOrder $production, string $errorKey = 'items'): Item
    {
        $sourceItems = $this->productionSourceItems($production);

        if ($sourceItems->isEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => 'A production input item is required before scrap can be recorded.',
            ]);
        }

        $sourceItem = $sourceItems->first();

        return Item::query()
            ->whereKey($sourceItem->id)
            ->where('branch_id', $production->branch_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureQuantityLostUnitsMatchSource(Collection $rows, Item $sourceItem, string $errorKey): void
    {
        $mismatchedRows = $rows
            ->filter(fn (array $row): bool => ! empty($row['quantity_lost_unit'])
                && ! InventoryUnit::matches($row['quantity_lost_unit'], $sourceItem->unit))
            ->values();

        if ($mismatchedRows->isNotEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => "Qty Lost Unit must match the production input unit ({$sourceItem->unit}).",
            ]);
        }
    }

    private function productionSourceItems(ProductionOrder $production): Collection
    {
        $production->loadMissing(['inputs.item', 'inputs.deliveryItem.item', 'inputs.deliveryItem.sourceItem']);

        return $production->inputs
            ->map(function ($input): ?Item {
                if ($input->item_id && $input->item?->id) {
                    return $input->item;
                }

                return $input->deliveryItem?->item ?? $input->deliveryItem?->sourceItem;
            })
            ->filter(fn (?Item $item): bool => $item instanceof Item && $item->id !== null)
            ->unique('id')
            ->values();
    }

    private function deductScrapSource(Item $sourceItem, WastageItem $wastageItem, ProductionOrder $production, int $creatorId, string $errorKey): void
    {
        $beginning = (float) $sourceItem->quantity;
        $quantityLost = (float) $wastageItem->quantity_lost;

        if ($beginning < $quantityLost) {
            throw ValidationException::withMessages([
                $errorKey => "Insufficient stock for {$sourceItem->name} scrap deduction.",
            ]);
        }

        $this->inventoryService->decrease($sourceItem, $quantityLost);
        $remaining = (float) $sourceItem->quantity;

        InventoryTransaction::create([
            'item_id' => $sourceItem->id,
            'branch_id' => $sourceItem->branch_id,
            'type' => 'out',
            'quantity' => $quantityLost,
            'beginning_quantity' => $beginning,
            'remaining_quantity' => $remaining,
            'transaction_price' => $sourceItem->unit_price ? (float) $sourceItem->unit_price : null,
            'transaction_date' => now(),
            'reason' => 'SCRAP/WASTE LOSS: PRODUCTION '.$production->id,
            'status' => 'approved',
            'created_by' => $creatorId,
        ]);
    }

    private function addConvertedInventory(WastageItem $wastageItem, ProductionOrder $production, int $creatorId): void
    {
        $convertedItem = Item::query()
            ->whereKey($wastageItem->convert_to_item_id)
            ->where('branch_id', $production->branch_id)
            ->lockForUpdate()
            ->firstOrFail();

        $convertedQuantity = (float) $wastageItem->converted_quantity;
        $beginning = (float) $convertedItem->quantity;

        $this->inventoryService->increase($convertedItem, $convertedQuantity);
        $remaining = (float) $convertedItem->quantity;

        InventoryTransaction::create([
            'item_id' => $convertedItem->id,
            'branch_id' => $convertedItem->branch_id,
            'type' => 'in',
            'quantity' => $convertedQuantity,
            'beginning_quantity' => $beginning,
            'remaining_quantity' => $remaining,
            'transaction_price' => $convertedItem->unit_price ? (float) $convertedItem->unit_price : null,
            'transaction_date' => now(),
            'reason' => 'FROM SCRAP/WASTE CONVERSION: PRODUCTION '.$production->id,
            'status' => 'approved',
            'created_by' => $creatorId,
        ]);
    }
}
