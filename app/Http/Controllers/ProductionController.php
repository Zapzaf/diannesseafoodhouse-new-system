<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionRequest;
use App\Models\Item;
use App\Models\ProductionInput;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ProductionOrder::with(['inputs.item', 'outputs.item'])->latest()->paginate((int) request('per_page', 20))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductionRequest $request)
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($validated, $request): ProductionOrder {
            $order = ProductionOrder::create([
                'branch_id' => $validated['branch_id'],
                'status' => 'in_progress',
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['inputs'] as $input) {
                ProductionInput::create([
                    'production_order_id' => $order->id,
                    'item_id' => $input['item_id'],
                    'quantity_used' => $input['quantity_used'],
                    'unit' => $input['unit'],
                ]);
            }

            return $order;
        });

        return response()->json($order->load('inputs.item'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(ProductionOrder::with(['inputs.item', 'outputs.item', 'wastageReports.items'])->findOrFail($id));
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
        $validated = $request->validate([
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.item_id' => ['required', 'exists:items,id'],
            'outputs.*.quantity_produced' => ['required', 'numeric', 'gt:0'],
            'outputs.*.unit' => ['required', 'string', 'max:32'],
            'outputs.*.allocated_to' => ['required', 'in:inventory,sale,transfer'],
        ]);

        DB::transaction(function () use ($production, $validated): void {
            foreach ($production->inputs as $input) {
                $this->inventoryService->decrease($input->item, (float) $input->quantity_used);
            }

            foreach ($validated['outputs'] as $output) {
                $outputRow = ProductionOutput::create([
                    'production_order_id' => $production->id,
                    'item_id' => $output['item_id'],
                    'quantity_produced' => $output['quantity_produced'],
                    'unit' => $output['unit'],
                    'allocated_to' => $output['allocated_to'],
                ]);

                if ($outputRow->allocated_to === 'inventory') {
                    $this->inventoryService->increase($outputRow->item, (float) $outputRow->quantity_produced);
                }
            }

            $production->update([
                'status' => 'finished',
                'finished_at' => now(),
            ]);
        });

        return response()->json($production->refresh()->load(['inputs.item', 'outputs.item']));
    }
}

