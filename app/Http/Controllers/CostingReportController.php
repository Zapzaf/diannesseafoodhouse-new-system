<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CostingReport;
use App\Models\InventoryTransaction;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CostingReportController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        $status = $request->input('status', '');
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $reports = CostingReport::query()
            ->with(['item.category.location', 'branch', 'requester', 'approver'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest()
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        $statusCounts = CostingReport::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $latestUnitCostSub = InventoryTransaction::query()
            ->select('transaction_price')
            ->whereColumn('item_id', 'items.id')
            ->where('type', 'in')
            ->where('status', 'approved')
            ->whereNotNull('transaction_price')
            ->latest('created_at')
            ->limit(1);

        $items = Item::query()
            ->with(['category.location', 'branch'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->select('items.*')
            ->selectSub($latestUnitCostSub, 'latest_unit_cost')
            ->orderBy('name')
            ->limit(50)
            ->get();

        return view('reports.costing', compact('reports', 'items', 'status', 'dateFrom', 'dateTo', 'statusCounts') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function create(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        $items = Item::query()
            ->with(['branch', 'category.location'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->get();

        return view('reports.costing-create', [
            'items' => $items,
            'selectedItemId' => (int) $request->input('item_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'proposed_price' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'reason' => ['required', 'string', 'max:5000'],
            'costing_details' => ['nullable', 'string', 'max:10000'],
        ]);

        $item = Item::query()->with('branch')->findOrFail($data['item_id']);
        $this->authorizeItemAccess($request, $item);

        $report = CostingReport::create([
            'branch_id' => $item->branch_id,
            'item_id' => $item->id,
            'current_price' => $item->unit_price ?? 0,
            'proposed_price' => $data['proposed_price'],
            'reason' => $data['reason'],
            'costing_details' => $data['costing_details'] ?? null,
            'status' => CostingReport::STATUS_PENDING,
            'requested_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('reports.costing.show', $report)
            ->with('success', 'Costing report submitted for admin review. Item price was not changed.');
    }

    public function show(Request $request, CostingReport $costingReport): View
    {
        $this->authorizeReportAccess($request, $costingReport);

        $costingReport->load(['item.category.location', 'branch', 'requester', 'approver']);

        return view('reports.costing-show', compact('costingReport'));
    }

    public function approve(Request $request, CostingReport $costingReport): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'approval_remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($costingReport, $request, $data): void {
            $report = CostingReport::query()->lockForUpdate()->findOrFail($costingReport->id);

            if (! $report->isPending()) {
                abort(422, 'Only pending costing reports can be approved.');
            }

            $item = Item::query()->lockForUpdate()->findOrFail($report->item_id);
            $item->update(['unit_price' => $report->proposed_price]);

            $report->update([
                'status' => CostingReport::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_remarks' => $data['approval_remarks'] ?? null,
            ]);
        });

        return redirect()
            ->route('reports.costing.show', $costingReport)
            ->with('success', 'Costing report approved and item price updated.');
    }

    public function reject(Request $request, CostingReport $costingReport): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'approval_remarks' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($costingReport, $request, $data): void {
            $report = CostingReport::query()->lockForUpdate()->findOrFail($costingReport->id);

            if (! $report->isPending()) {
                abort(422, 'Only pending costing reports can be rejected.');
            }

            $report->update([
                'status' => CostingReport::STATUS_REJECTED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_remarks' => $data['approval_remarks'],
            ]);
        });

        return redirect()
            ->route('reports.costing.show', $costingReport)
            ->with('success', 'Costing report rejected. Item price was left unchanged.');
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    private function authorizeItemAccess(Request $request, Item $item): void
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || (int) $user->branch_id === (int) $item->branch_id, 403);
    }

    private function authorizeReportAccess(Request $request, CostingReport $report): void
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || (int) $user->branch_id === (int) $report->branch_id, 403);
    }
}
