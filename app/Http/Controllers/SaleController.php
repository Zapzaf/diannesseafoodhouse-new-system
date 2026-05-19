<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    private const VAT_RATE = 0.12;

    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Sale::with(['items.item', 'user', 'branch'])->latest()->paginate((int) request('per_page', 20))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSaleRequest $request)
    {
        $validated = $request->validated();

        $sale = DB::transaction(function () use ($validated, $request): Sale {
            $subtotal = 0.0;
            $vatTotal = 0.0;

            $sale = Sale::create([
                'reference_number' => 'SAL-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                'branch_id' => $validated['branch_id'],
                'user_id' => $request->user()->id,
                'subtotal' => 0,
                'vat_total' => 0,
                'grand_total' => 0,
            ]);

            foreach ($validated['items'] as $row) {
                $lineSubtotal = (float) $row['unit_price'] * (float) $row['quantity_sold'];
                $vatAmount = $lineSubtotal * self::VAT_RATE;
                $total = $lineSubtotal + $vatAmount;

                $subtotal += $lineSubtotal;
                $vatTotal += $vatAmount;

                $line = SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => $row['item_id'],
                    'quantity_sold' => $row['quantity_sold'],
                    'unit_price' => $row['unit_price'],
                    'subtotal' => $lineSubtotal,
                    'vat_amount' => $vatAmount,
                    'total' => $total,
                ]);

                $this->inventoryService->decrease($line->item, (float) $line->quantity_sold);
            }

            $sale->update([
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'grand_total' => $subtotal + $vatTotal,
            ]);

            return $sale;
        });

        return response()->json($sale->load('items.item'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(Sale::with(['items.item', 'user', 'branch'])->findOrFail($id));
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
        Sale::findOrFail($id)->delete();

        return response()->json(status: 204);
    }
}

