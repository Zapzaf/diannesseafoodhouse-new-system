<?php

namespace App\Http\Controllers;

use App\Models\WastageItem;
use App\Models\WastageReport;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class WastageReportController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(WastageReport::with(['items.item', 'items.convertedItem'])->latest()->paginate((int) request('per_page', 20))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_order_id' => ['required', 'exists:production_orders,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity_lost' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.convert_to_item_id' => ['nullable', 'exists:items,id'],
            'items.*.converted_quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $report = DB::transaction(function () use ($validated, $request): WastageReport {
            $report = WastageReport::create([
                'production_order_id' => $validated['production_order_id'],
                'branch_id' => $validated['branch_id'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $row) {
                $wastageItem = WastageItem::create([
                    'wastage_report_id' => $report->id,
                    'item_id' => $row['item_id'],
                    'quantity_lost' => $row['quantity_lost'],
                    'reason' => $row['reason'] ?? null,
                    'convert_to_item_id' => $row['convert_to_item_id'] ?? null,
                    'converted_quantity' => $row['converted_quantity'] ?? null,
                ]);

                $this->inventoryService->decrease($wastageItem->item, (float) $wastageItem->quantity_lost);

                if ($wastageItem->convert_to_item_id && $wastageItem->converted_quantity) {
                    $this->inventoryService->increase($wastageItem->convertedItem, (float) $wastageItem->converted_quantity);
                }
            }

            return $report;
        });

        return response()->json($report->load('items'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(WastageReport::with(['items.item', 'items.convertedItem'])->findOrFail($id));
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
        WastageReport::findOrFail($id)->delete();

        return response()->json(status: 204);
    }
}

