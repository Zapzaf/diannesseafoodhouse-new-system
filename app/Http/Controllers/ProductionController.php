<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionRequest;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ProductionInput;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductionController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json(
            ProductionOrder::with(['inputs.item', 'outputs.item'])
                ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
                ->latest()
                ->paginate((int) request('per_page', 20))
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductionRequest $request)
    {
        $validated = $request->validated();
        $this->ensureBranchAccess($request, (int) $validated['branch_id']);
        $this->ensureItemsBelongToBranch($validated['inputs'], (int) $validated['branch_id'], 'inputs');

        $order = DB::transaction(function () use ($validated, $request): ProductionOrder {
            $order = ProductionOrder::create([
                'branch_id' => $validated['branch_id'],
                'status' => 'in_progress',
                'created_by' => $request->user()->id,
            ]);

            $reason = 'Pulling Items for Production: PROD-'.$order->id;

            foreach ($validated['inputs'] as $input) {
                $item = Item::query()->lockForUpdate()->findOrFail((int) $input['item_id']);
                $beginning = (float) $item->quantity;

                $this->inventoryService->decrease($item, (float) $input['quantity_used']);
                $remaining = (float) $item->quantity;

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

            return $order;
        });

        return response()->json($order->load('inputs.item'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $production = ProductionOrder::with(['inputs.item', 'outputs.item', 'wastageReports.items'])->findOrFail($id);
        $this->ensureBranchAccess($request, (int) $production->branch_id);

        return response()->json($production);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        ProductionOrder::findOrFail($id)->delete();

        return response()->json(status: 204);
    }

    public function finish(Request $request, ProductionOrder $production)
    {
        $this->ensureBranchAccess($request, (int) $production->branch_id);

        $validated = $request->validate([
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.item_id' => ['required', 'exists:items,id'],
            'outputs.*.quantity_produced' => ['required', 'numeric', 'gt:0'],
            'outputs.*.unit' => ['nullable', 'string', 'max:32'],
            'outputs.*.allocated_to' => ['required', 'in:inventory,sale,transfer'],
        ]);

        $this->ensureItemsBelongToBranch($validated['outputs'], (int) $production->branch_id, 'outputs');

        DB::transaction(function () use ($production, $validated): void {
            $production = ProductionOrder::query()->lockForUpdate()->findOrFail($production->id);

            if ($production->status === 'finished') {
                throw ValidationException::withMessages([
                    'production' => 'Production order already finished.',
                ]);
            }

            $production->loadMissing('inputs.item');

            foreach ($validated['outputs'] as $output) {
                $item = Item::query()
                    ->whereKey((int) $output['item_id'])
                    ->where('branch_id', $production->branch_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $outputRow = ProductionOutput::create([
                    'production_order_id' => $production->id,
                    'item_id' => $item->id,
                    'quantity_produced' => $output['quantity_produced'],
                    'unit' => $item->unit,
                    'allocated_to' => $output['allocated_to'],
                ]);

                if ($outputRow->allocated_to === 'inventory') {
                    $this->inventoryService->increase($item, (float) $outputRow->quantity_produced);
                }
            }

            $production->update([
                'status' => 'finished',
                'finished_at' => now(),
            ]);
        });

        return response()->json($production->refresh()->load(['inputs.item', 'outputs.item']));
    }

    private function ensureBranchAccess(Request $request, int $branchId): void
    {
        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== $branchId) {
            abort(403, 'This production order is outside your branch.');
        }
    }

    private function ensureItemsBelongToBranch(array $rows, int $branchId, string $errorKey): void
    {
        $itemIds = collect($rows)
            ->pluck('item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $scopedCount = Item::query()
            ->whereIn('id', $itemIds)
            ->where('branch_id', $branchId)
            ->count();

        if ($scopedCount !== $itemIds->count()) {
            throw ValidationException::withMessages([
                $errorKey => 'All selected items must belong to the production branch.',
            ]);
        }
    }
}

