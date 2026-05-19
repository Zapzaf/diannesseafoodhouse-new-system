<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Transfer::with(['delivery.items.item', 'fromBranch', 'toBranch'])->latest()->paginate((int) request('per_page', 10))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_branch_id' => ['required', 'exists:branches,id'],
            'to_branch_id' => ['required', 'exists:branches,id', 'different:from_branch_id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['required', 'string', 'max:32'],
        ]);

        $transfer = DB::transaction(function () use ($validated, $request): Transfer {
            $delivery = Delivery::create([
                'reference_number' => 'TRF-DLV-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'source_branch_id' => $validated['from_branch_id'],
                'destination_branch_id' => $validated['to_branch_id'],
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);
            }

            return Transfer::create([
                'from_branch_id' => $validated['from_branch_id'],
                'to_branch_id' => $validated['to_branch_id'],
                'delivery_id' => $delivery->id,
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json($transfer->load('delivery.items.item'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(Transfer::with(['delivery.items.item', 'fromBranch', 'toBranch'])->findOrFail($id));
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
        Transfer::findOrFail($id)->delete();

        return response()->json(status: 204);
    }
}
