<?php

namespace App\Http\Controllers;

use App\Exports\DeliveryReportExport;
use App\Exports\TransactionReportExport;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Feedback;
use App\Models\InventoryTransaction;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

    /**
     * Cost of Goods Sold: for each item, how much left inventory (approved
     * "out" transactions — production input, waste, order deduction, etc.)
     * within the period, valued at that item's most recent purchase cost —
     * the same latest_unit_cost convention the Costing Report already uses,
     * so "cost" means the same thing everywhere in the app.
     */
    public function cogs(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);

        $latestUnitCostSub = InventoryTransaction::query()
            ->select('transaction_price')
            ->whereColumn('item_id', 'items.id')
            ->where('type', 'in')
            ->where('status', 'approved')
            ->whereNotNull('transaction_price')
            ->latest('created_at')
            ->limit(1);

        $itemsQuery = Item::query()
            ->with(['category.location', 'branch'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->select('items.*')
            ->selectSub($latestUnitCostSub, 'latest_unit_cost')
            ->withSum(['transactions as quantity_sold' => function ($q) use ($dateFrom, $dateTo) {
                $q->where('type', 'out')
                    ->where('status', 'approved')
                    ->whereDate('created_at', '>=', $dateFrom)
                    ->whereDate('created_at', '<=', $dateTo);
            }], 'quantity')
            ->having('quantity_sold', '>', 0)
            ->orderBy('name');

        $items = (clone $itemsQuery)
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        // Grand total across every matching item, not just the current page.
        $totalCogs = (clone $itemsQuery)->get()
            ->sum(fn (Item $item) => (float) $item->quantity_sold * (float) ($item->latest_unit_cost ?? $item->unit_price));

        return view('reports.cogs', compact('items', 'dateFrom', 'dateTo', 'totalCogs') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function transaction(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $type = $request->validate(['type' => ['nullable', 'in:in,out']])['type'] ?? '';
        $itemIds = $this->parseItemIds($request);

        $transactions = $this->transactionQuery($request, $branchId, $dateFrom, $dateTo, $type, $itemIds)
            ->latest()
            ->paginate($this->perPage(request(), 20))->withQueryString();

        $stockIn = InventoryTransaction::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('inventory', fn ($inner) => $inner->where('branch_id', $id)))
            ->when($itemIds, fn ($q, $ids) => $q->whereIn('item_id', $ids))
            ->where('type', 'in')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('quantity');

        $stockOut = InventoryTransaction::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('inventory', fn ($inner) => $inner->where('branch_id', $id)))
            ->when($itemIds, fn ($q, $ids) => $q->whereIn('item_id', $ids))
            ->where('type', 'out')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('quantity');

        $items = Item::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.transaction', compact('transactions', 'stockIn', 'stockOut', 'dateFrom', 'dateTo', 'type', 'items', 'itemIds') + [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    /**
     * JSON feed for the Transaction Report table (see deliveryData() docblock).
     * Also carries fresh Stock In/Out totals for the summary cards, since
     * search/item filters are live here — unlike date/type, which still
     * require the "Apply" reload — the cards would otherwise silently go
     * stale the moment someone searches or picks an item.
     */
    public function transactionData(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $type = $request->validate(['type' => ['nullable', 'in:in,out']])['type'] ?? '';
        $itemIds = $this->parseItemIds($request);

        $transactions = $this->transactionQuery($request, $branchId, $dateFrom, $dateTo, $type, $itemIds)
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->through(fn (InventoryTransaction $tx) => [
                'id' => $tx->id,
                'log_id' => $tx->log_id ?? 'N/A',
                'date' => $tx->created_at?->format('M d, Y H:i'),
                'item_name' => $tx->inventory?->name,
                'branch_name' => $tx->inventory?->branch?->name,
                'type' => $tx->type,
                'quantity' => (float) $tx->quantity,
                'unit' => $tx->inventory?->unit,
                'status' => $tx->status,
                'reason' => $tx->reason,
                'created_by' => $tx->creator?->name,
            ]);

        // Stock In/Out always reflect both directions regardless of the
        // "type" dropdown — that filter narrows the table rows, not the cards.
        $stockIn = (clone $this->transactionQuery($request, $branchId, $dateFrom, $dateTo, '', $itemIds))
            ->where('type', 'in')->sum('quantity');
        $stockOut = (clone $this->transactionQuery($request, $branchId, $dateFrom, $dateTo, '', $itemIds))
            ->where('type', 'out')->sum('quantity');

        return response()->json([
            ...$transactions->toArray(),
            'stock_in' => (float) $stockIn,
            'stock_out' => (float) $stockOut,
        ]);
    }

    public function exportTransaction(Request $request)
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $type = $request->validate(['type' => ['nullable', 'in:in,out']])['type'] ?? '';
        $itemIds = $this->parseItemIds($request);

        $transactions = $this->transactionQuery($request, $branchId, $dateFrom, $dateTo, $type, $itemIds)
            ->latest()
            ->get();

        $filename = 'transaction-report-'.$dateFrom.'-to-'.$dateTo.'.xlsx';

        return Excel::download(new TransactionReportExport($transactions), $filename);
    }

    /**
     * Shared query behind the Transaction Report's page load, AJAX
     * pagination, and Excel export, so the three can never drift apart on
     * what "matching this filter set" means.
     *
     * @param  array<int, int>  $itemIds
     */
    private function transactionQuery(Request $request, ?int $branchId, string $dateFrom, string $dateTo, string $type, array $itemIds): Builder
    {
        $search = trim((string) $request->input('search', ''));

        return InventoryTransaction::query()
            ->with(['inventory.branch', 'creator'])
            ->when($branchId, fn ($q, $id) => $q->whereHas('inventory', fn ($inner) => $inner->where('branch_id', $id)))
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->when($itemIds, fn ($q, $ids) => $q->whereIn('item_id', $ids))
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('log_id', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhereHas('inventory', fn ($item) => $item->where('name', 'like', "%{$search}%"))))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);
    }

    /**
     * @return array<int, int>
     */
    private function parseItemIds(Request $request): array
    {
        $raw = (string) $request->input('item_ids', '');

        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
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

    /**
     * JSON feed for the Delivery Report table, driven by the same
     * IndexTableBridge client-side pagination used by Inventory Items —
     * plain fetch() + client render, so there's no full-page reload for
     * "page 2", and none of the fragility that comes with it.
     */
    public function deliveryData(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $status = $request->validate(['status' => ['nullable', 'in:pending,received']])['status'] ?? '';
        $perPage = $this->perPage($request, 20);

        $deliveryItems = $this->deliveryReportItemQuery($request, $branchId, $dateFrom, $dateTo, $status)
            ->paginate($perPage)
            ->through(fn (DeliveryItem $deliveryItem) => [
                'id' => $deliveryItem->id,
                'reference_number' => $deliveryItem->delivery->reference_number,
                'date' => optional($deliveryItem->delivery->created_at)->format('M d, Y H:i'),
                'destination' => $deliveryItem->delivery->destinationBranch?->name,
                'source' => $deliveryItem->delivery->sourceBranch?->name ?? $deliveryItem->delivery->supplier?->name,
                'item_name' => $deliveryItem->description ?: ($deliveryItem->item?->name ?? $deliveryItem->sourceItem?->name ?? 'Unspecified item'),
                'quantity' => (float) $deliveryItem->quantity,
                'unit' => $deliveryItem->unit,
                'cost' => (float) ($deliveryItem->price ?? 0),
                'status' => $deliveryItem->delivery->status,
                'approved_by' => $deliveryItem->delivery->approver?->name,
                'created_by' => $deliveryItem->delivery->creator?->name,
            ]);

        return response()->json($deliveryItems);
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

    public function feedback(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);

        $baseQuery = Feedback::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('branch_id', $request->user()->branch_id))
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo);

        $totalResponses = (clone $baseQuery)->count();

        $averagesRow = (clone $baseQuery)
            ->selectRaw(implode(', ', array_map(
                fn (string $field) => "AVG({$field}) as {$field}",
                array_keys(Feedback::RATING_FIELDS)
            )))
            ->first();

        $averages = [];
        foreach (Feedback::RATING_FIELDS as $field => $label) {
            $averages[$field] = $averagesRow?->{$field} !== null ? round((float) $averagesRow->{$field}, 2) : null;
        }

        $ratedAverages = array_filter($averages, fn ($value) => $value !== null);
        $overallAverage = $ratedAverages !== []
            ? round(array_sum($ratedAverages) / count($ratedAverages), 2)
            : null;

        $experienceDistribution = (clone $baseQuery)
            ->selectRaw('overall_experience as rating, COUNT(*) as total')
            ->groupBy('overall_experience')
            ->pluck('total', 'rating');

        $feedback = (clone $baseQuery)
            ->with('branch')
            ->latest('date')
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('reports.feedback', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
            'feedback' => $feedback,
            'ratingFields' => Feedback::RATING_FIELDS,
            'averages' => $averages,
            'overallAverage' => $overallAverage,
            'totalResponses' => $totalResponses,
            'experienceDistribution' => $experienceDistribution,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    /**
     * JSON feed for the Feedback Report table (see deliveryData() docblock).
     */
    public function feedbackData(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);

        $feedback = Feedback::query()
            ->with('branch')
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('branch_id', $request->user()->branch_id))
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->latest('date')
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->through(fn (Feedback $entry) => [
                'id' => $entry->id,
                'date' => $entry->date->format('M d, Y'),
                'branch' => $entry->branch?->name,
                'name' => $entry->name ?: 'Anonymous',
                'ratings' => collect(Feedback::RATING_FIELDS)->keys()
                    ->mapWithKeys(fn ($field) => [$field => (int) $entry->{$field}]),
                'average_rating' => $entry->average_rating,
                'improvements' => $entry->improvements,
            ]);

        return response()->json($feedback);
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

