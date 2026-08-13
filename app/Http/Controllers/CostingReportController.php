<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CostingReport;
use App\Models\CostingReportAttachment;
use App\Models\Delivery;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ProductionOrder;
use Illuminate\Http\JsonResponse;
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
            ->paginate($this->perPage($request, 20))
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
            ->paginate(10, ['*'], 'items_page')
            ->withQueryString();

        return view('reports.costing', compact('reports', 'items', 'status', 'dateFrom', 'dateTo', 'statusCounts') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    /**
     * JSON feed for the Report History table (see
     * ReportController::deliveryData() docblock for why).
     */
    public function reportsData(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        $status = $request->input('status', '');
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $reports = CostingReport::query()
            ->with(['item.category', 'branch', 'requester', 'approver'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->through(fn (CostingReport $report) => [
                'id' => $report->id,
                'item_name' => $report->item?->name ?? 'Deleted item',
                'category_name' => $report->item?->category?->name ?? 'N/A',
                'branch_name' => $report->branch?->name ?? 'N/A',
                'current_price' => (float) $report->current_price,
                'proposed_price' => (float) $report->proposed_price,
                'delta' => (float) $report->proposed_price - (float) $report->current_price,
                'source_label' => $report->reasonTypeLabel().($report->reference_id ? ' #'.$report->reference_id : ''),
                'status' => $report->status,
                'requester_name' => $report->requester?->name ?? 'N/A',
                'approver_name' => $report->approver?->name,
                'created_at' => $report->created_at?->format('M d, Y H:i'),
                'show_url' => route('reports.costing.show', $report),
            ]);

        return response()->json($reports);
    }

    /**
     * JSON feed for the Current Item Cost Reference table.
     */
    public function itemsData(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

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
            ->paginate($this->perPage($request, 10))
            ->through(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'location_name' => $item->category?->location?->name ?? 'N/A',
                'category_name' => $item->category?->name ?? 'N/A',
                'branch_name' => $item->branch?->name ?? 'N/A',
                'unit_price' => (float) ($item->unit_price ?? 0),
                'latest_unit_cost' => $item->latest_unit_cost !== null ? (float) $item->latest_unit_cost : null,
                'request_url' => route('reports.costing.create', ['item_id' => $item->id]),
            ]);

        return response()->json($items);
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

    public function searchDeliveries(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        $search = trim((string) $request->input('q', ''));
        $id = $request->integer('id');

        $deliveries = Delivery::query()
            ->with('supplier')
            ->when($branchId, fn ($q, $bid) => $q->where(fn ($inner) => $inner
                ->where('destination_branch_id', $bid)
                ->orWhere('source_branch_id', $bid)))
            ->when($id, fn ($q) => $q->whereKey($id))
            ->when(! $id && $search !== '', function ($q) use ($search): void {
                $q->where(fn ($inner) => $inner
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%")));
            })
            ->latest()
            ->limit(20)
            ->get();

        return response()->json($deliveries->map(fn (Delivery $delivery): array => [
            'id' => $delivery->id,
            'label' => 'Delivery #'.$delivery->id
                .' — '.($delivery->supplier?->name ?? 'No supplier')
                .' — '.$delivery->created_at?->format('M d, Y')
                .' ('.strtoupper((string) $delivery->status).')',
        ])->values());
    }

    public function searchProductions(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        $search = trim((string) $request->input('q', ''));
        $id = $request->integer('id');

        $productions = ProductionOrder::query()
            ->when($branchId, fn ($q, $bid) => $q->where('branch_id', $bid))
            ->when($id, fn ($q) => $q->whereKey($id))
            ->when(! $id && $search !== '', function ($q) use ($search): void {
                $q->where(fn ($inner) => $inner
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%"));
            })
            ->latest()
            ->limit(20)
            ->get();

        return response()->json($productions->map(fn (ProductionOrder $production): array => [
            'id' => $production->id,
            'label' => 'Production #'.$production->id
                .' — '.$production->created_at?->format('M d, Y')
                .' ('.strtoupper((string) $production->status).')',
        ])->values());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'proposed_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'reason_type' => ['required', 'in:delivery,production,others'],
            'delivery_id' => ['required_if:reason_type,delivery', 'nullable', 'integer', 'exists:deliveries,id'],
            'production_id' => ['required_if:reason_type,production', 'nullable', 'integer', 'exists:production_orders,id'],
            'reason_text' => ['required_if:reason_type,others', 'nullable', 'string', 'max:5000'],
            'costing_details' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,xls,xlsx,doc,docx,csv'],
        ], [
            'delivery_id.required_if' => 'Please select a delivery.',
            'production_id.required_if' => 'Please select a production order.',
            'reason_text.required_if' => 'Please describe the reason for this price change.',
            'attachments.*.max' => 'Each supporting document must be 5MB or smaller.',
        ]);

        $item = Item::query()->with('branch')->findOrFail($data['item_id']);
        $this->authorizeItemAccess($request, $item);

        [$referenceId, $reason] = $this->resolveReason($data);

        $report = DB::transaction(function () use ($request, $item, $data, $referenceId, $reason): CostingReport {
            $report = CostingReport::create([
                'branch_id' => $item->branch_id,
                'item_id' => $item->id,
                'current_price' => $item->unit_price ?? 0,
                'proposed_price' => $data['proposed_price'],
                'reason_type' => $data['reason_type'],
                'reference_id' => $referenceId,
                'reason' => $reason,
                'costing_details' => $data['costing_details'] ?? null,
                'status' => CostingReport::STATUS_PENDING,
                'requested_by' => $request->user()->id,
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('costing-reports/'.$report->id, 'public');

                CostingReportAttachment::create([
                    'costing_report_id' => $report->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize() ?: 0,
                ]);
            }

            return $report;
        });

        return redirect()
            ->route('reports.costing.show', $report)
            ->with('success', 'Costing report submitted for admin review. Item price was not changed.');
    }

    /**
     * @return array{0: ?int, 1: string} [reference id, human-readable reason]
     */
    private function resolveReason(array $data): array
    {
        if ($data['reason_type'] === CostingReport::REASON_DELIVERY) {
            $delivery = Delivery::query()->with('supplier')->findOrFail((int) $data['delivery_id']);

            return [
                $delivery->id,
                'From Delivery #'.$delivery->id
                    .($delivery->supplier?->name ? ' — '.$delivery->supplier->name : '')
                    .' ('.$delivery->created_at?->format('M d, Y').')',
            ];
        }

        if ($data['reason_type'] === CostingReport::REASON_PRODUCTION) {
            $production = ProductionOrder::query()->findOrFail((int) $data['production_id']);

            return [
                $production->id,
                'From Production #'.$production->id.' ('.$production->created_at?->format('M d, Y').')',
            ];
        }

        return [null, trim((string) $data['reason_text'])];
    }

    public function show(Request $request, CostingReport $costingReport): View
    {
        $this->authorizeReportAccess($request, $costingReport);

        $costingReport->load(['item.category.location', 'branch', 'requester', 'approver', 'attachments']);

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
