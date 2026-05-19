<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleManagementController extends Controller
{
    private const VAT_RATE = 0.12;

    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function create(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        return view('sales.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()
                ->with(['category.location', 'branch'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'vatRate' => self::VAT_RATE,
        ]);
    }

    public function index(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        return view('sales.index', [
            'sales' => Sale::query()
                ->with(['items.item', 'user', 'branch'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->latest()
                ->paginate((int) request('per_page', 12))->withQueryString(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()
                ->with(['category.location', 'branch'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'vatRate' => self::VAT_RATE,
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $branchId = $this->resolveBranchId($request);

        if ($branchId && (int) $validated['branch_id'] !== $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please use the active branch for sales.',
            ]);
        }

        $itemIds = collect($validated['items'])->pluck('item_id')->unique()->values();
        $scopedItemCount = Item::query()
            ->whereIn('id', $itemIds)
            ->where('branch_id', $validated['branch_id'])
            ->count();

        if ($scopedItemCount !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'All sale items must belong to the selected branch.',
            ]);
        }

        DB::transaction(function () use ($validated, $request): void {
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
                $lineTotal = $lineSubtotal + $vatAmount;

                $subtotal += $lineSubtotal;
                $vatTotal += $vatAmount;

                $line = SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => $row['item_id'],
                    'quantity_sold' => $row['quantity_sold'],
                    'unit_price' => $row['unit_price'],
                    'subtotal' => $lineSubtotal,
                    'vat_amount' => $vatAmount,
                    'total' => $lineTotal,
                ]);

                $this->inventoryService->decrease($line->item, (float) $line->quantity_sold);
            }

            $sale->update([
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'grand_total' => $subtotal + $vatTotal,
            ]);
        });

        return redirect()->route('sales.index')->with('success', 'Sale recorded successfully.');
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
