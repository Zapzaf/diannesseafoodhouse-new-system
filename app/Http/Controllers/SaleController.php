<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    private const VAT_RATE = 0.12;

    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json(
            Sale::with(['items.item', 'user', 'branch'])
                ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
                ->latest()
                ->paginate((int) request('per_page', 20))
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSaleRequest $request)
    {
        $validated = $request->validated();
        $this->ensureBranchAccess($request, (int) $validated['branch_id']);
        $this->ensureSaleItemsBelongToBranch($validated['items'], (int) $validated['branch_id']);

        $sale = DB::transaction(function () use ($validated, $request): Sale {
            $subtotal = 0.0;
            $vatTotal = 0.0;
            $items = Item::query()
                ->whereIn('id', collect($validated['items'])->pluck('item_id')->map(fn ($id) => (int) $id)->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sale = Sale::create([
                'reference_number' => 'SAL-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                'branch_id' => $validated['branch_id'],
                'user_id' => $request->user()->id,
                'subtotal' => 0,
                'vat_total' => 0,
                'grand_total' => 0,
            ]);

            foreach ($validated['items'] as $row) {
                $item = $items->get((int) $row['item_id']);
                $unitPrice = $item->unit_price !== null ? (float) $item->unit_price : 0.0;
                $lineSubtotal = $unitPrice * (float) $row['quantity_sold'];
                $vatAmount = $lineSubtotal * self::VAT_RATE;
                $total = $lineSubtotal + $vatAmount;

                $subtotal += $lineSubtotal;
                $vatTotal += $vatAmount;

                $line = SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => $item->id,
                    'quantity_sold' => $row['quantity_sold'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                    'vat_amount' => $vatAmount,
                    'total' => $total,
                ]);

                $this->inventoryService->decrease($item, (float) $line->quantity_sold);
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
    public function show(Request $request, string $id)
    {
        $sale = Sale::with(['items.item', 'user', 'branch'])->findOrFail($id);
        $this->ensureBranchAccess($request, (int) $sale->branch_id);

        return response()->json($sale);
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
    public function destroy(Request $request, string $id)
    {
        $sale = Sale::findOrFail($id);
        $this->ensureBranchAccess($request, (int) $sale->branch_id);
        $sale->delete();

        return response()->json(status: 204);
    }

    private function ensureBranchAccess(Request $request, int $branchId): void
    {
        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== $branchId) {
            abort(403, 'This sale is outside your branch.');
        }
    }

    private function ensureSaleItemsBelongToBranch(array $rows, int $branchId): void
    {
        $itemIds = collect($rows)
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $scopedCount = Item::query()
            ->whereIn('id', $itemIds)
            ->where('branch_id', $branchId)
            ->count();

        if ($scopedCount !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'All sale items must belong to the selected branch.',
            ]);
        }
    }
}

