<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Delivery::with(['items.item', 'supplier', 'sourceBranch', 'destinationBranch'])->latest()->paginate((int) request('per_page', 20))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeliveryRequest $request)
    {
        $this->authorize('create', Delivery::class);
        $validated = $request->validated();

        $delivery = DB::transaction(function () use ($validated, $request): Delivery {
            $delivery = Delivery::create([
                'reference_number' => 'DLV-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                'supplier_id' => $validated['supplier_id'] ?? null,
                'source_branch_id' => $validated['source_branch_id'] ?? null,
                'destination_branch_id' => $validated['destination_branch_id'],
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $row) {
                DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'item_id' => $row['item_id'],
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'],
                ]);
            }

            return $delivery;
        });

        return response()->json($delivery->load('items.item'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $delivery = Delivery::with(['items.item', 'supplier', 'sourceBranch', 'destinationBranch'])->findOrFail($id);
        $this->authorize('view', $delivery);

        return response()->json($delivery);
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
        $delivery = Delivery::findOrFail($id);
        $this->authorize('delete', $delivery);
        $delivery->delete();

        return response()->json(status: 204);
    }

    public function approve(Request $request, Delivery $delivery)
    {
        $this->authorize('approve', $delivery);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['required', 'exists:delivery_items,id'],
            'items.*.allocated_to' => ['required', 'in:inventory,production'],
        ]);

        if ($delivery->status === 'received') {
            return response()->json(['message' => 'Delivery already approved.'], 422);
        }

        DB::transaction(function () use ($delivery, $validated, $request): void {
            $allocMap = collect($validated['items'])->keyBy('delivery_item_id');

            foreach ($delivery->items as $item) {
                $allocation = $allocMap->get($item->id);
                if (! $allocation) {
                    continue;
                }

                $item->allocated_to = $allocation['allocated_to'];
                $item->save();

                if ($item->allocated_to === 'inventory') {
                    $this->inventoryService->increase($item->item, (float) $item->quantity);
                }
            }

            $delivery->update([
                'status' => 'received',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            if ($delivery->source_branch_id) {
                foreach ($delivery->items as $item) {
                    $this->inventoryService->decrease($item->item, (float) $item->quantity);
                }
            }
        });

        return response()->json($delivery->refresh()->load('items.item'));
    }
}

