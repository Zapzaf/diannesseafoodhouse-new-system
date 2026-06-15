<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionRequest;
use App\Models\Branch;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ProductionInput;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\WastageItem;
use App\Models\WastageReport;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionManagementController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function create(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        $items = Item::query()
            ->with(['category.location'])
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        $itemOptions = $items->map(fn ($item) => [
            'value' => $item->id,
            'label' => '#' . $item->id . ' - ' . $item->name
                . ' (' . ($item->category?->location?->name ?? 'N/A')
                . ' / ' . ($item->category?->name ?? 'N/A') . ')'
                . ' â€” ' . number_format((float) $item->quantity, 2) . ' ' . $item->unit,
        ])->values()->all();

        return view('productions.create', [
            'branches'    => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'items'       => $items,
            'itemOptions' => $itemOptions,
        ]);
    }

    public function index(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        return view('productions.index', [
            'productions' => ProductionOrder::query()
                ->with(['branch', 'creator', 'inputs.item', 'outputs.item'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->when(request('search'), fn($q, $s) => $q->where('id', 'like', "%$s%"))->latest()
                ->paginate((int) request('per_page', 12))->withQueryString(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()
                ->with(['category.location', 'branch'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreProductionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $branchId  = $this->resolveBranchId($request);

        if ($branchId && (int) $validated['branch_id'] !== $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please use the active branch for production.',
            ]);
        }

        // Validate all items belong to the selected branch
        $itemIds = collect($validated['inputs'])
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $scopedCount = Item::query()
            ->whereIn('id', $itemIds)
            ->where('branch_id', $validated['branch_id'])
            ->count();

        if ($scopedCount !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'inputs' => 'All selected items must belong to the chosen branch.',
            ]);
        }

        DB::transaction(function () use ($validated, $request): void {
            $order = ProductionOrder::create([
                'branch_id'  => $validated['branch_id'],
                'status'     => 'in_progress',
                'created_by' => $request->user()->id,
            ]);

            $reason = 'Pulling Items for Production: PROD-' . $order->id;

            foreach ($validated['inputs'] as $input) {
                $item = Item::query()->lockForUpdate()->findOrFail((int) $input['item_id']);

                $beginning = (float) $item->quantity;

                // Deduct from inventory stock
                $this->inventoryService->decrease($item, (float) $input['quantity_used']);
                $item->refresh();
                $remaining = (float) $item->quantity;

                // Create deduction transaction record
                InventoryTransaction::create([
                    'item_id'            => $item->id,
                    'branch_id'          => $item->branch_id,
                    'type'               => 'out',
                    'quantity'           => (float) $input['quantity_used'],
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price'  => $item->unit_price ? (float) $item->unit_price : null,
                    'transaction_date'   => now(),
                    'reason'             => $reason,
                    'status'             => 'approved',
                    'created_by'         => $request->user()?->id,
                ]);

                ProductionInput::create([
                    'production_order_id' => $order->id,
                    'item_id'             => $item->id,
                    'quantity_used'       => $input['quantity_used'],
                    'unit'                => $input['unit'],
                ]);
            }
        });

        return redirect()->route('productions.index')->with('success', 'Production order started successfully.');
    }

    public function show(ProductionOrder $production): View
    {
        $branchId = $this->resolveBranchId(request());
        if ($branchId && (int) $production->branch_id !== $branchId) {
            abort(403, 'This production order is outside your active branch.');
        }

        $production->load(['branch', 'creator', 'inputs.item', 'inputs.deliveryItem.delivery.supplier', 'outputs.item', 'wastageReports.items.item', 'wastageReports.items.convertedItem']);

        return view('productions.show', [
            'production' => $production,
            'items' => Item::query()->with(['category.location'])->where('branch_id', $production->branch_id)->orderBy('name')->get(),
        ]);
    }

    public function finish(Request $request, ProductionOrder $production): RedirectResponse
    {
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $production->branch_id !== $branchId) {
            abort(403, 'This production order is outside your active branch.');
        }

        $validated = $request->validate([
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.item_id' => ['required', 'exists:items,id'],
            'outputs.*.quantity_produced' => ['required', 'numeric', 'gt:0'],
            'outputs.*.unit' => ['required', 'string', 'max:32'],
            'wastage' => ['nullable', 'array'],
            'wastage.*.scrap_name' => ['nullable', 'string', 'max:255'],
            'wastage.*.quantity_lost' => ['nullable', 'numeric', 'gt:0'],
            'wastage.*.reason' => ['nullable', 'string', 'max:255'],
            'wastage.*.convert_to_item_id' => ['nullable', 'exists:items,id'],
            'wastage.*.converted_quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:wastage.*.convert_to_item_id'],
        ]);

        if ($production->status === 'finished') {
            return redirect()->route('productions.show', $production)->with('error', 'Production order already finished.');
        }

        $relatedItemIds = collect($validated['outputs'])
            ->pluck('item_id')
            ->merge(collect($validated['wastage'] ?? [])->pluck('convert_to_item_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        $branchItemCount = Item::query()
            ->whereIn('id', $relatedItemIds)
            ->where('branch_id', $production->branch_id)
            ->count();

        if ($branchItemCount !== $relatedItemIds->count()) {
            throw ValidationException::withMessages([
                'outputs' => 'All output and converted waste items must belong to the production branch.',
            ]);
        }

        DB::transaction(function () use ($production, $validated, $request): void {
            $production->loadMissing('inputs.item', 'inputs.deliveryItem');

            $totalInputCost = 0.0;
            foreach ($production->inputs as $input) {
                $qtyUsed = (float) $input->quantity_used;
                if ($qtyUsed <= 0) {
                    continue;
                }

                if ($input->delivery_item_id && $input->deliveryItem) {
                    $delQty = (float) $input->deliveryItem->quantity;
                    $delPrice = $input->deliveryItem->price !== null ? (float) $input->deliveryItem->price : 0.0;
                    $unitCost = ($delQty > 0 && $delPrice > 0) ? ($delPrice / $delQty) : 0.0;
                    $totalInputCost += ($unitCost * $qtyUsed);
                    continue;
                }

                $unitCost = $input->item?->unit_price !== null ? (float) $input->item->unit_price : 0.0;
                $totalInputCost += ($unitCost * $qtyUsed);
            }

            $totalOutputQty = collect($validated['outputs'])->sum(fn (array $row) => (float) $row['quantity_produced']);
            $outputUnitCost = ($totalInputCost > 0 && $totalOutputQty > 0) ? ($totalInputCost / $totalOutputQty) : null;

            foreach ($validated['outputs'] as $output) {
                $row = ProductionOutput::create([
                    'production_order_id' => $production->id,
                    'item_id' => $output['item_id'],
                    'quantity_produced' => $output['quantity_produced'],
                    'unit' => $output['unit'],
                    'allocated_to' => 'inventory',
                ]);

                $item      = $row->item;
                $beginning = (float) $item->quantity;
                if ($outputUnitCost !== null) {
                    $this->inventoryService->increaseWithPrice($item, (float) $row->quantity_produced, (float) $outputUnitCost);
                } else {
                    $this->inventoryService->increase($item, (float) $row->quantity_produced);
                }
                $remaining = (float) $item->quantity;

                InventoryTransaction::create([
                    'item_id'            => $item->id,
                    'branch_id'          => $item->branch_id,
                    'type'               => 'in',
                    'quantity'           => (float) $row->quantity_produced,
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price'  => $outputUnitCost !== null ? (float) $outputUnitCost : ($item->unit_price ? (float) $item->unit_price : null),
                    'transaction_date'   => now(),
                    'reason'             => 'FROM PRODUCTION: ' . $production->id,
                    'status'             => 'approved',
                    'created_by'         => $request->user()?->id,
                ]);
            }

            $wastageRows = collect($validated['wastage'] ?? [])
                ->filter(fn (array $row): bool => !empty($row['quantity_lost']) && (!empty($row['scrap_name']) || !empty($row['convert_to_item_id'])))
                ->values();

            if ($wastageRows->isNotEmpty()) {
                $report = WastageReport::create([
                    'production_order_id' => $production->id,
                    'branch_id' => $production->branch_id,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($wastageRows as $row) {
                    $wastageItem = WastageItem::create([
                        'wastage_report_id' => $report->id,
                        'item_id' => null,
                        'scrap_name' => $row['scrap_name'] ?? null,
                        'quantity_lost' => $row['quantity_lost'],
                        'reason' => $row['reason'] ?? null,
                        'convert_to_item_id' => $row['convert_to_item_id'] ?? null,
                        'converted_quantity' => $row['converted_quantity'] ?? null,
                    ]);

                    if ($wastageItem->convert_to_item_id && $wastageItem->converted_quantity) {
                        $convertedItem = $wastageItem->convertedItem;
                        $beginning = (float) $convertedItem->quantity;
                        $this->inventoryService->increase($convertedItem, (float) $wastageItem->converted_quantity);
                        $remaining = (float) $convertedItem->quantity;

                        InventoryTransaction::create([
                            'item_id'            => $convertedItem->id,
                            'branch_id'          => $convertedItem->branch_id,
                            'type'               => 'in',
                            'quantity'           => (float) $wastageItem->converted_quantity,
                            'beginning_quantity' => $beginning,
                            'remaining_quantity' => $remaining,
                            'transaction_price'  => $convertedItem->unit_price ? (float) $convertedItem->unit_price : null,
                            'transaction_date'   => now(),
                            'reason'             => 'FROM SCRAP/WASTE CONVERSION: PRODUCTION ' . $production->id,
                            'status'             => 'approved',
                            'created_by'         => $request->user()?->id,
                        ]);
                    }
                }
            }

            $production->update([
                'status' => 'finished',
                'finished_at' => now(),
            ]);
        });

        return redirect()->route('productions.show', $production)->with('success', 'Production order finished successfully.');
    }

    public function storeWastage(Request $request, ProductionOrder $production): RedirectResponse
    {
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $production->branch_id !== $branchId) {
            abort(403, 'This production order is outside your active branch.');
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.scrap_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity_lost' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.convert_to_item_id' => ['nullable', 'exists:items,id'],
            'items.*.converted_quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:items.*.convert_to_item_id'],
        ]);

        DB::transaction(function () use ($validated, $request, $production): void {
            $report = WastageReport::create([
                'production_order_id' => $production->id,
                'branch_id' => $production->branch_id,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $row) {
                $wastageItem = WastageItem::create([
                    'wastage_report_id' => $report->id,
                    'item_id' => null,
                    'scrap_name' => $row['scrap_name'] ?? null,
                    'quantity_lost' => $row['quantity_lost'],
                    'reason' => $row['reason'] ?? null,
                    'convert_to_item_id' => $row['convert_to_item_id'] ?? null,
                    'converted_quantity' => $row['converted_quantity'] ?? null,
                ]);

                if ($wastageItem->convert_to_item_id && $wastageItem->converted_quantity) {
                    $convertedItem = $wastageItem->convertedItem;
                    $beginning = (float) $convertedItem->quantity;
                    $this->inventoryService->increase($convertedItem, (float) $wastageItem->converted_quantity);
                    $remaining = (float) $convertedItem->quantity;

                    InventoryTransaction::create([
                        'item_id'            => $convertedItem->id,
                        'branch_id'          => $convertedItem->branch_id,
                        'type'               => 'in',
                        'quantity'           => (float) $wastageItem->converted_quantity,
                        'beginning_quantity' => $beginning,
                        'remaining_quantity' => $remaining,
                        'transaction_price'  => $convertedItem->unit_price ? (float) $convertedItem->unit_price : null,
                        'transaction_date'   => now(),
                        'reason'             => 'FROM SCRAP/WASTE CONVERSION: PRODUCTION ' . $production->id,
                        'status'             => 'approved',
                        'created_by'         => $request->user()?->id,
                    ]);
                }
            }
        });

        return redirect()->route('productions.show', $production)->with('success', 'Wastage report submitted successfully.');
    }

    public function processing(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        return view('productions.processing', [
            'productions' => ProductionOrder::query()
                ->with(['branch', 'creator', 'inputs.item', 'outputs.item'])
                ->where('status', 'in_progress')
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->when(request('search'), fn($q, $s) => $q->where('id', 'like', "%$s%"))->latest()
                ->paginate((int) request('per_page', 12))->withQueryString(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
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
}

