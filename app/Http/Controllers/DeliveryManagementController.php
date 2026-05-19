<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ProductionInput;
use App\Models\ProductionOrder;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Arr;

class DeliveryManagementController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $branchId = $this->resolveBranchId($request);

        $items = Item::query()
            ->with(['category.location', 'branch'])
            ->orderBy('name')
            ->get();

        return view('deliveries.create', [
            'user'          => $user,
            'branches'      => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers'     => Supplier::query()->orderBy('name')->get(),
            'itemsForModal' => $items->map(fn (Item $i) => [
                'id'          => $i->id,
                'name'        => $i->name,
                'quantity'    => (float) $i->quantity,
                'unit_price'  => $i->unit_price ? (float) $i->unit_price : null,
                'location'    => $i->category?->location?->name ?? 'N/A',
                'category'    => $i->category?->name ?? 'N/A',
                'branch_id'   => $i->branch_id,
            ])->values(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $branchId = $this->resolveBranchId($request);

        $deliveries = Delivery::query()
            ->with(['items.item.category.location', 'supplier', 'sourceBranch', 'destinationBranch', 'creator', 'approver'])
            ->when(! $user->isAdmin(), function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner->where('destination_branch_id', $user->branch_id)
                        ->orWhere('source_branch_id', $user->branch_id);
                });
            })
            ->when($branchId, function ($query, $branchId) {
                $query->where(function ($inner) use ($branchId) {
                    $inner->where('destination_branch_id', $branchId)
                        ->orWhere('source_branch_id', $branchId);
                });
            })
            ->when(request('search'), fn($q, $s) => $q->where('reference_number', 'like', "%$s%"))->latest()
            ->paginate((int) request('per_page', 10))->withQueryString();

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'items' => Item::query()
                ->with(['category.location', 'branch'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function store(StoreDeliveryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Delivery::class);
        $validated = $request->validated();
        $branchId = $this->resolveBranchId($request);
        $transferSourceItemId = $request->integer('source_item_id') ?: null;
        $isTransfer = (bool) $transferSourceItemId;
        $isAutoApprove = $isTransfer
            ? false
            : ($request->user()->isAdmin() || $request->user()->isBranchManager());

        DB::transaction(function () use ($validated, $request, $isAutoApprove, $branchId): void {
            $transferSourceItemId = $request->integer('source_item_id') ?: null;
            $sourceItem = $transferSourceItemId ? Item::query()->find($transferSourceItemId) : null;

            $delivery = Delivery::create([
                'reference_number'     => 'DLV-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                'supplier_id'          => $sourceItem ? null : ($validated['supplier_id'] ?? null),
                'source_branch_id'     => $sourceItem ? (int) $sourceItem->branch_id : ($validated['source_branch_id'] ?? null),
                'destination_branch_id'=> $validated['destination_branch_id'],
                'status'               => $isAutoApprove ? 'received' : 'pending',
                'created_by'           => $request->user()->id,
                'approved_by'          => $isAutoApprove ? $request->user()->id : null,
                'approved_at'          => $isAutoApprove ? now() : null,
            ]);

            foreach ($validated['items'] as $row) {
                $deliveryItem = DeliveryItem::create([
                    'delivery_id'  => $delivery->id,
                    'item_id'      => !empty($row['item_id']) ? $row['item_id'] : null,
                    'source_item_id' => $sourceItem ? $sourceItem->id : null,
                    'description'  => $row['description'],
                    'quantity'     => $row['quantity'],
                    'unit'         => $row['unit'],
                    'price'        => $row['price'] ?? null,
                    'allocated_to' => $row['allocated_to'],
                ]);

                if ($isAutoApprove && $row['allocated_to'] === 'inventory' && !empty($row['item_id'])) {
                    $linkedItem = Item::find($row['item_id']);
                    if ($linkedItem) {
                        $this->applyInventoryIncrease(
                            $linkedItem,
                            $deliveryItem,
                            $request->user()->id,
                            'FROM DELIVERY: ' . $delivery->reference_number
                        );
                    }
                }
                // Production allocations: no transaction log yet
            }

            if ($isAutoApprove && $sourceItem) {
                $delivery->loadMissing('items');
                foreach ($delivery->items as $delItem) {
                    $beginning = (float) $sourceItem->quantity;
                    $this->inventoryService->decrease($sourceItem, (float) $delItem->quantity);
                    $remaining = (float) $sourceItem->quantity;
                    $this->recordInventoryTransaction(
                        $sourceItem,
                        'out',
                        (float) $delItem->quantity,
                        'TO BRANCH TRANSFER: ' . $delivery->reference_number,
                        $request->user()->id,
                        $beginning,
                        $sourceItem->unit_price ? (float) $sourceItem->unit_price : null,
                        $remaining
                    );
                }
            }

            if ($isAutoApprove) {
                $this->createProductionOrderFromDelivery($delivery, $request->user()->id);
            }
        });

        $message = $isAutoApprove
            ? 'Delivery created and automatically approved.'
            : 'Delivery record created and marked as pending.';

        return redirect()->route('deliveries.index')->with('success', $message);
    }

    public function show(Delivery $delivery): View
    {
        $delivery->loadMissing(['items.item.category', 'supplier', 'destinationBranch', 'sourceBranch', 'creator', 'approver']);

        return view('deliveries.view', compact('delivery'));
    }

    public function updatePrices(Request $request, Delivery $delivery): RedirectResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'Only admins can update delivery prices.');
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['required', 'exists:delivery_items,id'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($delivery, $validated): void {
            $delivery->loadMissing('items');
            $priceMap = collect($validated['items'])->keyBy('delivery_item_id');

            foreach ($delivery->items as $item) {
                $row = $priceMap->get($item->id);
                if (! $row) {
                    continue;
                }

                $item->price = Arr::exists($row, 'price') ? $row['price'] : null;
                $item->save();
            }
        });

        return redirect()->route('deliveries.show', $delivery)->with('success', 'Delivery prices updated successfully.');
    }

    public function approve(Request $request, Delivery $delivery): RedirectResponse
    {
        $branchId = $this->resolveBranchId($request);
        if ($branchId
            && (int) $delivery->destination_branch_id !== $branchId
            && (int) $delivery->source_branch_id !== $branchId) {
            abort(403, 'This delivery is outside your active branch.');
        }

        Gate::authorize('approve', $delivery);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['required', 'exists:delivery_items,id'],
            'items.*.allocated_to' => ['required', 'in:inventory,production'],
        ]);

        if ($delivery->status === 'received') {
            return redirect()->route('deliveries.index')->with('error', 'Delivery already approved.');
        }

        DB::transaction(function () use ($delivery, $validated, $request): void {
            $delivery->loadMissing('items.item');
            $allocations = collect($validated['items'])->keyBy('delivery_item_id');

            foreach ($delivery->items as $item) {
                $allocation = $allocations->get($item->id);
                if (! $allocation) {
                    // use pre-set allocated_to if not sent from form
                    if ($item->allocated_to === 'inventory' && $item->item) {
                        $this->applyInventoryIncrease(
                            $item->item,
                            $item,
                            $request->user()->id,
                            'FROM DELIVERY: ' . $delivery->reference_number
                        );
                    }
                    // production: no transaction log at this stage
                    continue;
                }

                $item->allocated_to = $allocation['allocated_to'];
                $item->save();

                if ($item->allocated_to === 'inventory' && $item->item) {
                    $this->applyInventoryIncrease(
                        $item->item,
                        $item,
                        $request->user()->id,
                        'FROM DELIVERY: ' . $delivery->reference_number
                    );
                }
                // production: no transaction log at this stage
            }

            $delivery->update([
                'status' => 'received',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            if ($delivery->source_branch_id) {
                $delivery->loadMissing('items.sourceItem');
                foreach ($delivery->items as $item) {
                    if (! $item->sourceItem) {
                        continue;
                    }

                    $beginning = (float) $item->sourceItem->quantity;
                    $this->inventoryService->decrease($item->sourceItem, (float) $item->quantity);
                    $remaining = (float) $item->sourceItem->quantity;
                    $this->recordInventoryTransaction(
                        $item->sourceItem,
                        'out',
                        (float) $item->quantity,
                        'TO BRANCH TRANSFER: ' . $delivery->reference_number,
                        $request->user()->id,
                        $beginning,
                        $item->sourceItem->unit_price ? (float) $item->sourceItem->unit_price : null,
                        $remaining
                    );
                }
            }

            $this->createProductionOrderFromDelivery($delivery, $request->user()->id);
        });

        return redirect()->route('deliveries.index')->with('success', 'Delivery approved successfully.');
    }

    public function pending(Request $request): View
    {
        $user = $request->user();
        $branchId = $this->resolveBranchId($request);

        $deliveries = Delivery::query()
            ->with(['items.item.category.location', 'supplier', 'sourceBranch', 'destinationBranch', 'creator'])
            ->where('status', 'pending')
            ->when(! $user->isAdmin(), function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner->where('destination_branch_id', $user->branch_id)
                        ->orWhere('source_branch_id', $user->branch_id);
                });
            })
            ->when($branchId, function ($query, $branchId) {
                $query->where(function ($inner) use ($branchId) {
                    $inner->where('destination_branch_id', $branchId)
                        ->orWhere('source_branch_id', $branchId);
                });
            })
            ->when(request('search'), fn($q, $s) => $q->where('reference_number', 'like', "%$s%"))->latest()
            ->paginate((int) request('per_page', 10))->withQueryString();

        return view('deliveries.pending', [
            'deliveries' => $deliveries,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()
                ->with(['category.location', 'branch'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    private function applyInventoryIncrease(
        Item $linkedItem,
        DeliveryItem $deliveryItem,
        ?int $actorId = null,
        ?string $reason = null,
    ): void
    {
        $qty       = (float) $deliveryItem->quantity;
        $price     = (float) ($deliveryItem->price ?? 0);
        $unitPrice = ($price > 0 && $qty > 0) ? $price / $qty : null;

        $beginning = (float) $linkedItem->quantity;

        if ($unitPrice !== null) {
            $this->inventoryService->increaseWithPrice($linkedItem, $qty, $unitPrice);
        } else {
            $this->inventoryService->increase($linkedItem, $qty);
        }

        $remaining = (float) $linkedItem->quantity;

        $this->recordInventoryTransaction(
            $linkedItem,
            'in',
            $qty,
            $reason ?? 'Approved delivery',
            $actorId,
            $beginning,
            $unitPrice,
            $remaining
        );
    }

    private function recordInventoryTransaction(
        Item $item,
        string $type,
        float $quantity,
        string $reason,
        ?int $actorId = null,
        ?float $beginningQuantity = null,
        ?float $transactionPrice = null,
        ?float $remainingQuantity = null,
    ): void
    {
        InventoryTransaction::create([
            'item_id'            => $item->id,
            'branch_id'          => $item->branch_id,
            'type'               => $type,
            'quantity'           => $quantity,
            'beginning_quantity' => $beginningQuantity,
            'remaining_quantity' => $remainingQuantity,
            'transaction_price'  => $transactionPrice,
            'transaction_date'   => now(),
            'reason'             => $reason,
            'status'             => 'approved',
            'created_by'         => $actorId,
        ]);
    }

    private function createProductionOrderFromDelivery(Delivery $delivery, int $actorId): void
    {
        $delivery->loadMissing('items');

        $productionItems = $delivery->items
            ->filter(fn (DeliveryItem $item): bool => $item->allocated_to === 'production');

        $availableItems = $productionItems
            ->filter(fn (DeliveryItem $item): bool => ! ProductionInput::query()->where('delivery_item_id', $item->id)->exists())
            ->values();

        if ($availableItems->isEmpty()) {
            return;
        }

        $order = ProductionOrder::create([
            'branch_id' => $delivery->destination_branch_id,
            'status' => 'in_progress',
            'created_by' => $actorId,
        ]);

        foreach ($availableItems as $deliveryItem) {
            ProductionInput::create([
                'production_order_id' => $order->id,
                'item_id' => $deliveryItem->item_id,
                'delivery_item_id' => $deliveryItem->id,
                'quantity_used' => $deliveryItem->quantity,
                'unit' => $deliveryItem->unit,
            ]);
        }
    }
}

