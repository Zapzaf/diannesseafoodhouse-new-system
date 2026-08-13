<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionRequest;
use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ProductionInput;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Services\InventoryService;
use App\Services\WastageInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionManagementController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly WastageInventoryService $wastageInventoryService,
    ) {}

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
            'unit' => $item->unit,
            'label' => '#'.$item->id.' - '.$item->name
                .' ('.($item->category?->location?->name ?? 'N/A')
                .' / '.($item->category?->name ?? 'N/A').')'
                .' - '.number_format((float) $item->quantity, 2).' '.$item->unit,
        ])->values()->all();

        return view('productions.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => $items,
            'itemOptions' => $itemOptions,
        ]);
    }

    public function index(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'branch_name', 'status', 'inputs_count', 'outputs_count', 'created_at', 'finished_at'];

        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $productionsQuery = ProductionOrder::query()
            ->select('production_orders.*')
            ->with(['branch', 'creator', 'inputs.item', 'inputs.deliveryItem', 'outputs.item'])
            ->withCount(['inputs', 'outputs'])
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(request('search'), fn ($q, $s) => $q->where('id', 'like', "%$s%"));

        if ($sort === 'branch_name') {
            $productionsQuery->leftJoin('branches as branch_sort', 'production_orders.branch_id', '=', 'branch_sort.id')
                ->orderBy('branch_sort.name', $direction);
        } elseif (in_array($sort, ['inputs_count', 'outputs_count'], true)) {
            $productionsQuery->orderBy($sort, $direction);
        } else {
            $productionsQuery->orderBy("production_orders.{$sort}", $direction);
        }

        return view('productions.index', [
            'productions' => $productionsQuery
                ->orderBy('production_orders.created_at', 'desc')
                ->paginate($this->perPage(request(), 10))->withQueryString(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()
                ->with(['category.location', 'branch'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * JSON feed for the Production Orders table, driven by the same
     * IndexTableBridge client-side pagination used by Inventory Items.
     */
    public function data(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'branch_name', 'status', 'inputs_count', 'outputs_count', 'created_at', 'finished_at'];

        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $productionsQuery = ProductionOrder::query()
            ->select('production_orders.*')
            ->with(['branch', 'creator', 'inputs.item', 'inputs.deliveryItem', 'outputs.item'])
            ->withCount(['inputs', 'outputs'])
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($request->input('search'), fn ($q, $s) => $q->where('id', 'like', "%$s%"));

        if ($sort === 'branch_name') {
            $productionsQuery->leftJoin('branches as branch_sort', 'production_orders.branch_id', '=', 'branch_sort.id')
                ->orderBy('branch_sort.name', $direction);
        } elseif (in_array($sort, ['inputs_count', 'outputs_count'], true)) {
            $productionsQuery->orderBy($sort, $direction);
        } else {
            $productionsQuery->orderBy("production_orders.{$sort}", $direction);
        }

        $isAdmin = $request->user()->isAdmin();

        $productions = $productionsQuery
            ->paginate($this->perPage($request, 10))
            ->through(fn (ProductionOrder $production) => [
                'id' => $production->id,
                'creator_name' => $production->creator?->name ?? 'System',
                'branch_name' => $isAdmin ? ($production->branch?->name ?? 'N/A') : null,
                'status' => $production->status,
                'status_label' => strtoupper(str_replace('_', ' ', $production->status)),
                'created_at' => $production->created_at?->format('M d, Y'),
                'finished_at' => $production->finished_at?->format('M d, Y'),
                'outputs_count' => $production->outputs_count,
                'inputs' => $this->summarizeProductionItems($production->inputs, 'quantity_used'),
                'outputs' => $this->summarizeProductionItems($production->outputs, 'quantity_produced'),
                'show_url' => route('productions.show', $production),
                'cancel_url' => $production->status === 'in_progress' && $production->outputs_count === 0
                    ? route('productions.cancel', $production)
                    : null,
            ]);

        return response()->json($productions);
    }

    /**
     * Mirrors <x-production-item-summary>'s name/quantity/unit resolution,
     * capped to the same first-2-then-"N more" shape the JS renderer expects
     * — so the AJAX-rendered table looks identical to the old server-rendered one.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\ProductionInput|\App\Models\ProductionOutput>  $entries
     * @return array{items: array<int, array{name: string, quantity: float, unit: ?string}>, more: int}
     */
    private function summarizeProductionItems($entries, string $quantityField): array
    {
        $visible = $entries->take(2);

        return [
            'items' => $visible->map(function ($entry) use ($quantityField) {
                $name = $entry->item?->name;

                if (! $name || $name === 'Delivery Material') {
                    $name = $entry->deliveryItem?->description ?: $name;
                }

                return [
                    'name' => $name ?: 'Unavailable item',
                    'quantity' => (float) data_get($entry, $quantityField, 0),
                    'unit' => $entry->unit ?: $entry->item?->unit ?: $entry->deliveryItem?->unit,
                ];
            })->values()->all(),
            'more' => max($entries->count() - $visible->count(), 0),
        ];
    }

    public function store(StoreProductionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $branchId = $this->resolveBranchId($request);

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
                'branch_id' => $validated['branch_id'],
                'status' => 'in_progress',
                'created_by' => $request->user()->id,
            ]);

            $reason = 'Pulling Items for Production: PROD-'.$order->id;

            foreach ($validated['inputs'] as $input) {
                $item = Item::query()->lockForUpdate()->findOrFail((int) $input['item_id']);

                $beginning = (float) $item->quantity;

                // Deduct from inventory stock
                $this->inventoryService->decrease($item, (float) $input['quantity_used']);
                $item->refresh();
                $remaining = (float) $item->quantity;

                // Create deduction transaction record
                InventoryTransaction::create([
                    'item_id' => $item->id,
                    'branch_id' => $item->branch_id,
                    'type' => 'out',
                    'quantity' => (float) $input['quantity_used'],
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price' => $item->unit_price ? (float) $item->unit_price : null,
                    'transaction_date' => now(),
                    'reason' => $reason,
                    'status' => 'approved',
                    'created_by' => $request->user()?->id,
                ]);

                ProductionInput::create([
                    'production_order_id' => $order->id,
                    'item_id' => $item->id,
                    'quantity_used' => $input['quantity_used'],
                    'unit' => $item->unit,
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

        $production->load([
            'branch',
            'creator',
            'inputs.item.category.location',
            'inputs.deliveryItem.item.category.location',
            'inputs.deliveryItem.sourceItem.category.location',
            'inputs.deliveryItem.delivery.supplier',
            'outputs.item.category.location',
            'wastageReports.items.item',
            'wastageReports.items.convertedItem',
        ]);
        $scrapUnit = $production->inputs
            ->map(fn ($input) => $input->item?->unit
                ?? $input->deliveryItem?->item?->unit
                ?? $input->deliveryItem?->sourceItem?->unit
                ?? $input->unit)
            ->filter()
            ->first();

        return view('productions.show', [
            'production' => $production,
            'items' => Item::query()->with(['category.location'])->where('branch_id', $production->branch_id)->orderBy('name')->get(),
            'scrapUnit' => $scrapUnit,
        ]);
    }

    public function finish(Request $request, ProductionOrder $production): RedirectResponse
    {
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $production->branch_id !== $branchId) {
            abort(403, 'This production order is outside your active branch.');
        }

        $outputs = collect($request->input('outputs', []))
            ->filter(fn (array $row): bool => ! empty($row['item_id']) || ! empty($row['quantity_produced']))
            ->values()
            ->all();

        $wastage = collect($request->input('wastage', []))
            ->filter(fn (array $row): bool => ! empty($row['scrap_name'])
                || ! empty($row['quantity_lost'])
                || ! empty($row['convert_to_item_id'])
                || ! empty($row['converted_quantity']))
            ->values()
            ->all();

        if (empty($outputs) && empty($wastage)) {
            throw ValidationException::withMessages([
                'outputs' => 'Please provide either a Finish Production output or a Scrap Conversion entry.',
            ]);
        }

        $request->merge(['outputs' => $outputs, 'wastage' => $wastage]);

        $validated = $request->validate(
            $this->finishProductionRules(),
            $this->finishProductionMessages()
        );

        if ($production->status === 'finished') {
            return redirect()->route('productions.show', $production)->with('error', 'Production order already finished.');
        }

        $relatedItemIds = collect($validated['outputs'] ?? [])
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
            $production = ProductionOrder::query()->lockForUpdate()->findOrFail($production->id);

            if ($production->status === 'finished') {
                throw ValidationException::withMessages([
                    'production' => 'Production order already finished.',
                ]);
            }

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

            $totalOutputQty = collect($validated['outputs'] ?? [])->sum(fn (array $row) => (float) $row['quantity_produced']);
            $outputUnitCost = ($totalInputCost > 0 && $totalOutputQty > 0) ? ($totalInputCost / $totalOutputQty) : null;

            foreach ($validated['outputs'] ?? [] as $output) {
                $item = Item::query()->lockForUpdate()->findOrFail((int) $output['item_id']);
                $row = ProductionOutput::create([
                    'production_order_id' => $production->id,
                    'item_id' => $item->id,
                    'quantity_produced' => $output['quantity_produced'],
                    'unit' => $item->unit,
                    'allocated_to' => 'inventory',
                ]);

                $beginning = (float) $item->quantity;
                if ($outputUnitCost !== null) {
                    $this->inventoryService->increaseWithPrice($item, (float) $row->quantity_produced, (float) $outputUnitCost);
                } else {
                    $this->inventoryService->increase($item, (float) $row->quantity_produced);
                }
                $remaining = (float) $item->quantity;

                InventoryTransaction::create([
                    'item_id' => $item->id,
                    'branch_id' => $item->branch_id,
                    'type' => 'in',
                    'quantity' => (float) $row->quantity_produced,
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price' => $outputUnitCost !== null ? (float) $outputUnitCost : ($item->unit_price ? (float) $item->unit_price : null),
                    'transaction_date' => now(),
                    'reason' => 'FROM PRODUCTION: '.$production->id,
                    'status' => 'approved',
                    'created_by' => $request->user()?->id,
                ]);
            }

            $wastageRows = collect($validated['wastage'] ?? [])
                ->filter(fn (array $row): bool => ! empty($row['scrap_name'])
                    || ! empty($row['quantity_lost'])
                    || ! empty($row['convert_to_item_id'])
                    || ! empty($row['converted_quantity']))
                ->values();

            if ($wastageRows->isNotEmpty()) {
                $this->wastageInventoryService->createConversionsFromProduction($production, $wastageRows, $request->user()->id, 'wastage');
            }

            $production->update([
                'status' => 'finished',
                'finished_at' => now(),
            ]);
        });

        return redirect()->route('productions.show', $production)->with('success', 'Production order finished successfully.');
    }

    public function cancel(Request $request, ProductionOrder $production): RedirectResponse
    {
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $production->branch_id !== $branchId) {
            abort(403, 'This production order is outside your active branch.');
        }

        DB::transaction(function () use ($production, $request): void {
            $production = ProductionOrder::query()->lockForUpdate()->findOrFail($production->id);

            if ($production->status !== 'in_progress' || $production->outputs()->exists()) {
                throw ValidationException::withMessages([
                    'production' => 'This production order can no longer be cancelled.',
                ]);
            }

            $production->loadMissing('inputs');
            $reason = 'Cancelled Production: PROD-'.$production->id;

            foreach ($production->inputs as $input) {
                // Inputs sourced straight from a delivery (delivery_item_id set) were never
                // deducted from item stock, so there's nothing to restock for those rows.
                if ($input->delivery_item_id || ! $input->item_id) {
                    continue;
                }

                $item = Item::query()->lockForUpdate()->find($input->item_id);
                if (! $item) {
                    continue;
                }

                $beginning = (float) $item->quantity;
                $this->inventoryService->increase($item, (float) $input->quantity_used);
                $remaining = (float) $item->quantity;

                InventoryTransaction::create([
                    'item_id' => $item->id,
                    'branch_id' => $item->branch_id,
                    'type' => 'in',
                    'quantity' => (float) $input->quantity_used,
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price' => $item->unit_price ? (float) $item->unit_price : null,
                    'transaction_date' => now(),
                    'reason' => $reason,
                    'status' => 'approved',
                    'created_by' => $request->user()?->id,
                ]);
            }

            $production->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        });

        return redirect()->route('productions.index')->with('success', 'Production order cancelled and inputs restocked.');
    }

    public function storeWastage(Request $request, ProductionOrder $production): RedirectResponse
    {
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $production->branch_id !== $branchId) {
            abort(403, 'This production order is outside your active branch.');
        }

        $validated = $request->validate(
            $this->productionWastageRules('items', true),
            $this->productionWastageMessages('items')
        );

        $convertedItemIds = collect($validated['items'])
            ->pluck('convert_to_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        $branchItemCount = Item::query()
            ->whereIn('id', $convertedItemIds)
            ->where('branch_id', $production->branch_id)
            ->count();

        if ($branchItemCount !== $convertedItemIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'All converted waste items must belong to the production branch.',
            ]);
        }

        DB::transaction(function () use ($validated, $request, $production): void {
            $this->wastageInventoryService->createFromProduction($production, $validated['items'], $request->user()->id);
        });

        return redirect()->route('productions.show', $production)->with('success', 'Wastage report submitted successfully.');
    }

    public function processing(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'branch_name', 'inputs_count', 'outputs_count', 'created_at'];

        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $productionsQuery = ProductionOrder::query()
            ->select('production_orders.*')
            ->with(['branch', 'creator', 'inputs.item', 'inputs.deliveryItem', 'outputs.item'])
            ->withCount(['inputs', 'outputs'])
            ->where('status', 'in_progress')
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(request('search'), fn ($q, $s) => $q->where('id', 'like', "%$s%"));

        if ($sort === 'branch_name') {
            $productionsQuery->leftJoin('branches as branch_sort', 'production_orders.branch_id', '=', 'branch_sort.id')
                ->orderBy('branch_sort.name', $direction);
        } elseif (in_array($sort, ['inputs_count', 'outputs_count'], true)) {
            $productionsQuery->orderBy($sort, $direction);
        } else {
            $productionsQuery->orderBy("production_orders.{$sort}", $direction);
        }

        return view('productions.processing', [
            'productions' => $productionsQuery
                ->orderBy('production_orders.created_at', 'desc')
                ->paginate($this->perPage(request(), 10))->withQueryString(),
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

    private function finishProductionRules(): array
    {
        return [
            'outputs' => ['nullable', 'array'],
            'outputs.*.item_id' => ['required', 'exists:items,id'],
            'outputs.*.quantity_produced' => ['required', 'numeric', 'gt:0'],
            'outputs.*.unit' => ['nullable', 'string', 'max:32'],
            'wastage' => ['nullable', 'array'],
            // Converting scrap into another item is optional; the pair is only
            // required together when one half of the conversion is provided.
            'wastage.*.convert_to_item_id' => ['nullable', 'exists:items,id', 'required_with:wastage.*.converted_quantity'],
            'wastage.*.converted_quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:wastage.*.convert_to_item_id'],
        ];
    }

    private function finishProductionMessages(): array
    {
        return [
            'wastage.*.convert_to_item_id.required_with' => 'Convert To is required when a converted quantity is entered.',
            'wastage.*.converted_quantity.required_with' => 'Convert Qty is required when a Convert To item is selected.',
        ];
    }

    private function productionWastageRules(string $prefix, bool $requireRows = false): array
    {
        $base = [
            $prefix => [$requireRows ? 'required' : 'nullable', 'array', 'min:1'],
            "{$prefix}.*.quantity_lost" => ['nullable', 'numeric', 'gt:0', "required_with:{$prefix}.*.convert_to_item_id,{$prefix}.*.converted_quantity"],
            "{$prefix}.*.quantity_lost_unit" => ['nullable', "required_with:{$prefix}.*.quantity_lost", 'string', 'max:32'],
            // Conversion is optional: only require the pair together.
            "{$prefix}.*.convert_to_item_id" => ['nullable', 'exists:items,id', "required_with:{$prefix}.*.converted_quantity"],
            "{$prefix}.*.converted_quantity" => ['nullable', 'numeric', 'gt:0', "required_with:{$prefix}.*.convert_to_item_id"],
        ];

        if ($prefix === 'items') {
            $base["{$prefix}.*.scrap_name"] = ['nullable', 'string', 'max:255'];
            $base["{$prefix}.*.reason"] = ['nullable', 'string', 'max:255'];
        }

        return $base;
    }

    private function productionWastageMessages(string $prefix): array
    {
        return [
            "{$prefix}.*.quantity_lost.required_with" => 'Qty Lost is required for every scrap conversion row.',
            "{$prefix}.*.convert_to_item_id.required_with" => 'Convert To is required for every scrap conversion row.',
            "{$prefix}.*.converted_quantity.required_with" => 'Convert Qty is required for every scrap conversion row.',
        ];
    }
}
