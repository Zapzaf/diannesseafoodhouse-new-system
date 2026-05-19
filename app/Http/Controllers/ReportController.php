<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Delivery;
use App\Models\InventoryTransaction;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $type = $request->input('type', '');

        $transactions = InventoryTransaction::query()
            ->with(['inventory.branch', 'creator'])
            ->when($branchId, fn ($q, $id) => $q->whereHas('inventory', fn ($inner) => $inner->where('branch_id', $id)))
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest()
            ->paginate((int) request('per_page', 20))->withQueryString();

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
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $status = $request->input('status', '');
        $user = $request->user();

        $deliveries = Delivery::query()
            ->with(['supplier', 'sourceBranch', 'destinationBranch', 'creator', 'approver', 'items'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('destination_branch_id', $user->branch_id)
                ->orWhere('source_branch_id', $user->branch_id)
            ))
            ->when($branchId, fn ($q, $id) => $q->where(fn ($inner) => $inner
                ->where('destination_branch_id', $id)
                ->orWhere('source_branch_id', $id)
            ))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest()
            ->paginate((int) request('per_page', 20))->withQueryString();

        $pendingCount = Delivery::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('destination_branch_id', $user->branch_id)
                ->orWhere('source_branch_id', $user->branch_id)
            ))
            ->when($branchId, fn ($q, $id) => $q->where(fn ($inner) => $inner
                ->where('destination_branch_id', $id)
                ->orWhere('source_branch_id', $id)
            ))
            ->where('status', 'pending')
            ->count();

        $receivedCount = Delivery::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('destination_branch_id', $user->branch_id)
                ->orWhere('source_branch_id', $user->branch_id)
            ))
            ->when($branchId, fn ($q, $id) => $q->where(fn ($inner) => $inner
                ->where('destination_branch_id', $id)
                ->orWhere('source_branch_id', $id)
            ))
            ->where('status', 'received')
            ->count();

        return view('reports.delivery', compact('deliveries', 'pendingCount', 'receivedCount', 'dateFrom', 'dateTo', 'status') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function costing(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

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

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }
}

