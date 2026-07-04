<?php

namespace App\Http\Controllers;

use App\Exports\DeliveryReportExport;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function inventory(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        $items = Item::query()
            ->with(['category.location', 'branch'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->get();

        $lowStockItems = $items->filter(fn (Item $item) => $item->quantity <= $item->low_stock_threshold);

        $totalItems = $items->count();
        $totalQuantity = $items->sum('quantity');
        $lowStockCount = $lowStockItems->count();

        return view('reports.inventory', compact(
            'items',
            'lowStockItems',
            'totalItems',
            'totalQuantity',
            'lowStockCount',
            'branchId'
        ) + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function transaction(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $type = $request->validate(['type' => ['nullable', 'in:in,out']])['type'] ?? '';

        $transactions = InventoryTransaction::query()
            ->with(['inventory.branch', 'creator'])
            ->when($branchId, fn ($q, $id) => $q->whereHas('inventory', fn ($inner) => $inner->where('branch_id', $id)))
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest()
            ->paginate($this->perPage(request(), 20))->withQueryString();

        $stockIn = InventoryTransaction::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('inventory', fn ($inner) => $inner->where('branch_id', $id)))
            ->where('type', 'in')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('quantity');

        $stockOut = InventoryTransaction::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('inventory', fn ($inner) => $inner->where('branch_id', $id)))
            ->where('type', 'out')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('quantity');

        return view('reports.transaction', compact('transactions', 'stockIn', 'stockOut', 'dateFrom', 'dateTo', 'type') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function delivery(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $status = $request->validate(['status' => ['nullable', 'in:pending,received']])['status'] ?? '';

        $deliveryItemsQuery = $this->deliveryReportItemQuery($request, $branchId, $dateFrom, $dateTo, $status);
        $deliveryItems = (clone $deliveryItemsQuery)
            ->paginate($this->perPage(request(), 20))->withQueryString();

        $totalDeliveryCost = (clone $deliveryItemsQuery)->sum('delivery_items.price');

        $deliveryCountQuery = $this->deliveryReportDeliveryQuery($request, $branchId, $dateFrom, $dateTo);

        $pendingCount = (clone $deliveryCountQuery)
            ->where('status', 'pending')
            ->count();

        $receivedCount = (clone $deliveryCountQuery)
            ->where('status', 'received')
            ->count();

        return view('reports.delivery', compact('deliveryItems', 'pendingCount', 'receivedCount', 'totalDeliveryCost', 'dateFrom', 'dateTo', 'status') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function exportDelivery(Request $request)
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $status = $request->validate(['status' => ['nullable', 'in:pending,received']])['status'] ?? '';

        $deliveryItemsQuery = $this->deliveryReportItemQuery($request, $branchId, $dateFrom, $dateTo, $status);
        $deliveryItems = (clone $deliveryItemsQuery)->get();
        $totalDeliveryCost = (clone $deliveryItemsQuery)->sum('delivery_items.price');

        $filename = 'delivery-report-'.$dateFrom.'-to-'.$dateTo.'.xlsx';

        return Excel::download(
            new DeliveryReportExport($deliveryItems, (float) $totalDeliveryCost),
            $filename
        );
    }

    public function costing(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);

        $latestUnitCostSub = InventoryTransaction::query()
            ->select('transaction_price')
            ->whereColumn('item_id', 'items.id')
            ->where('type', 'in')
            ->where('status', 'approved')
            ->whereNotNull('transaction_price')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest('created_at')
            ->limit(1);

        $latestCostDateSub = InventoryTransaction::query()
            ->select('created_at')
            ->whereColumn('item_id', 'items.id')
            ->where('type', 'in')
            ->where('status', 'approved')
            ->whereNotNull('transaction_price')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest('created_at')
            ->limit(1);

        $latestCostReasonSub = InventoryTransaction::query()
            ->select('reason')
            ->whereColumn('item_id', 'items.id')
            ->where('type', 'in')
            ->where('status', 'approved')
            ->whereNotNull('transaction_price')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest('created_at')
            ->limit(1);

        $items = Item::query()
            ->with(['category.location', 'branch'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->select('items.*')
            ->selectSub($latestUnitCostSub, 'latest_unit_cost')
            ->selectSub($latestCostDateSub, 'latest_cost_date')
            ->selectSub($latestCostReasonSub, 'latest_cost_reason')
            ->orderBy('name')
            ->get();

        return view('reports.costing', compact('items', 'dateFrom', 'dateTo') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    /**
     * @return array{0: string, 1: string} [date_from, date_to]
     */
    private function validatedDateRange(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return [
            $validated['date_from'] ?? now()->startOfMonth()->toDateString(),
            $validated['date_to'] ?? now()->toDateString(),
        ];
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    private function deliveryReportItemQuery(Request $request, ?int $branchId, string $dateFrom, string $dateTo, string $status = ''): Builder
    {
        return DeliveryItem::query()
            ->select('delivery_items.*')
            ->join('deliveries', 'delivery_items.delivery_id', '=', 'deliveries.id')
            ->with([
                'item',
                'sourceItem',
                'delivery.supplier',
                'delivery.sourceBranch',
                'delivery.destinationBranch',
                'delivery.creator',
                'delivery.approver',
            ])
            ->when(! $request->user()->isAdmin(), fn (Builder $query) => $this->scopeDeliveryRowsToBranch($query, (int) $request->user()->branch_id))
            ->when($branchId, fn (Builder $query, int $id) => $this->scopeDeliveryRowsToBranch($query, $id))
            ->when($status, fn (Builder $query, string $selectedStatus) => $query->where('deliveries.status', $selectedStatus))
            ->whereDate('deliveries.created_at', '>=', $dateFrom)
            ->whereDate('deliveries.created_at', '<=', $dateTo)
            ->orderByDesc('deliveries.created_at')
            ->orderBy('delivery_items.id');
    }

    private function deliveryReportDeliveryQuery(Request $request, ?int $branchId, string $dateFrom, string $dateTo): Builder
    {
        return Delivery::query()
            ->when(! $request->user()->isAdmin(), fn (Builder $query) => $this->scopeDeliveryRowsToBranch($query, (int) $request->user()->branch_id))
            ->when($branchId, fn (Builder $query, int $id) => $this->scopeDeliveryRowsToBranch($query, $id))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);
    }

    private function scopeDeliveryRowsToBranch(Builder $query, int $branchId): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->where('deliveries.destination_branch_id', $branchId)
            ->orWhere('deliveries.source_branch_id', $branchId)
        );
    }
}

