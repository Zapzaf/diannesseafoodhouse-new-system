@extends('layouts.app')
@section('page_title', 'Transaction Report - Dianne Seafood House')
@section('content')
<x-page-header title="Transaction Report" subtitle="Stock movement history with filters" icon="list">
    <a class="btn btn-success text-white" href="{{ route('reports.transaction.export', request()->query()) }}" id="exportTransactionsBtn">
        <i data-lucide="download" class="me-1"></i> Export Excel
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.transaction.index') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All</option>
                        <option value="in" {{ $type === 'in' ? 'selected' : '' }}>Stock In</option>
                        <option value="out" {{ $type === 'out' ? 'selected' : '' }}>Stock Out</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.transaction.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-lucide="trending-up" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Stock In</div>
                        <div class="fs-4 fw-bold text-success" id="stockInValue">{{ number_format($stockIn, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i data-lucide="trending-down" class="text-danger" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Stock Out</div>
                        <div class="fs-4 fw-bold text-danger" id="stockOutValue">{{ number_format($stockOut, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div><i data-lucide="list" class="me-1"></i> Transactions</div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="max-width: 220px;">
                    <span class="input-group-text"><i data-lucide="search" style="width:14px;height:14px;"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search log #, item, reason...">
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="itemFilterButton" style="max-width: 220px;">
                        <span id="itemFilterButtonLabel">All Items</span>
                    </button>
                    <div class="dropdown-menu p-2" style="width: 280px; max-height: 320px; overflow-y: auto;">
                        <input type="text" class="form-control form-control-sm mb-2" id="itemFilterSearch" placeholder="Filter items...">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <button type="button" class="btn btn-link btn-sm p-0" id="itemFilterClear">Clear selection</button>
                        </div>
                        <div id="itemFilterOptions">
                            @foreach($items as $item)
                            <div class="form-check">
                                <input class="form-check-input item-filter-checkbox" type="checkbox" value="{{ $item->id }}" id="itemFilterOpt{{ $item->id }}" data-name="{{ $item->name }}" {{ in_array($item->id, $itemIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="itemFilterOpt{{ $item->id }}">{{ $item->name }}</label>
                            </div>
                            @endforeach
                            @if($items->isEmpty())
                            <div class="text-muted small">No items found.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="transactionsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Log ID</th>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Branch</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"><tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div id="tableInfo" class="text-muted small"></div>
            <nav aria-label="Transactions pagination">
                <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
            </nav>
        </div>
    </div>

    <input type="hidden" id="transactionDateFromFilter" value="{{ $dateFrom }}">
    <input type="hidden" id="transactionDateToFilter" value="{{ $dateTo }}">
    <input type="hidden" id="transactionTypeFilter" value="{{ $type }}">
    <input type="hidden" id="transactionItemIdsFilter" value="{{ implode(',', $itemIds) }}">
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const exportBtn = document.getElementById('exportTransactionsBtn');
    const exportBaseUrl = @json(route('reports.transaction.export'));
    const dateFromInput = document.getElementById('transactionDateFromFilter');
    const dateToInput = document.getElementById('transactionDateToFilter');
    const typeInput = document.getElementById('transactionTypeFilter');
    const itemIdsInput = document.getElementById('transactionItemIdsFilter');
    const searchInput = document.getElementById('searchInput');

    const itemCheckboxes = Array.from(document.querySelectorAll('.item-filter-checkbox'));
    const itemFilterSearch = document.getElementById('itemFilterSearch');
    const itemFilterButtonLabel = document.getElementById('itemFilterButtonLabel');
    const itemFilterClear = document.getElementById('itemFilterClear');

    function selectedItemCheckboxes() {
        return itemCheckboxes.filter(function (cb) { return cb.checked; });
    }

    function updateItemFilterLabel() {
        const selected = selectedItemCheckboxes();
        if (selected.length === 0) {
            itemFilterButtonLabel.textContent = 'All Items';
        } else if (selected.length === 1) {
            itemFilterButtonLabel.textContent = selected[0].dataset.name;
        } else {
            itemFilterButtonLabel.textContent = selected.length + ' items selected';
        }
    }

    function updateExportLink() {
        const params = new URLSearchParams();
        if (dateFromInput.value) params.set('date_from', dateFromInput.value);
        if (dateToInput.value) params.set('date_to', dateToInput.value);
        if (typeInput.value) params.set('type', typeInput.value);
        if (searchInput.value) params.set('search', searchInput.value);
        if (itemIdsInput.value) params.set('item_ids', itemIdsInput.value);
        exportBtn.href = exportBaseUrl + '?' + params.toString();
    }

    // Syncs the hidden filter input IndexTableBridge reads from, and fires
    // its own 'change' so the table (already listening via `filters`) reloads.
    function syncItemIdsFilter() {
        const ids = selectedItemCheckboxes().map(function (cb) { return cb.value; });
        itemIdsInput.value = ids.join(',');
        updateItemFilterLabel();
        updateExportLink();
        itemIdsInput.dispatchEvent(new Event('change'));
    }

    itemCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', syncItemIdsFilter);
    });

    itemFilterClear?.addEventListener('click', function () {
        itemCheckboxes.forEach(function (cb) { cb.checked = false; });
        syncItemIdsFilter();
    });

    itemFilterSearch?.addEventListener('input', function () {
        const term = this.value.toLowerCase();
        itemCheckboxes.forEach(function (cb) {
            const row = cb.closest('.form-check');
            row.style.display = cb.dataset.name.toLowerCase().includes(term) ? '' : 'none';
        });
    });

    searchInput.addEventListener('input', updateExportLink);

    updateItemFilterLabel();
    updateExportLink();

    IndexTableBridge.init({
        tableId: 'transactionsTable',
        dataUrl: @json(route('reports.transaction.data')),
        searchInputId: 'searchInput',
        filters: [
            { inputId: 'transactionDateFromFilter', param: 'date_from' },
            { inputId: 'transactionDateToFilter', param: 'date_to' },
            { inputId: 'transactionTypeFilter', param: 'type' },
            { inputId: 'transactionItemIdsFilter', param: 'item_ids' }
        ],
        emptyMessage: 'No transactions in this period.',
        colspan: 9,
        onData: function (data) {
            if (typeof data.stock_in === 'number') {
                document.getElementById('stockInValue').textContent = Number(data.stock_in).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (typeof data.stock_out === 'number') {
                document.getElementById('stockOutValue').textContent = Number(data.stock_out).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        },
        renderRow: function (row, ctx) {
            const typeBadge = row.type === 'in' ? 'bg-success' : 'bg-danger';
            const statusBadge = row.status === 'pending' ? 'badge-pending' : 'badge-approved';
            const statusLabel = row.status === 'pending' ? 'PENDING' : 'APPROVED';
            return `
                <tr>
                    <td class="text-nowrap fw-semibold small">${ctx.escapeHtml(row.log_id)}</td>
                    <td class="text-nowrap text-muted small">${ctx.escapeHtml(row.date || '—')}</td>
                    <td class="fw-semibold">${ctx.escapeHtml(row.item_name || '—')}</td>
                    <td class="text-muted small">${ctx.escapeHtml(row.branch_name || '—')}</td>
                    <td><span class="badge ${typeBadge}">${ctx.escapeHtml((row.type || '').toUpperCase())}</span></td>
                    <td>${Number(row.quantity || 0).toFixed(2)} ${ctx.escapeHtml(row.unit || '')}</td>
                    <td><span class="badge-status ${statusBadge}">${statusLabel}</span></td>
                    <td class="text-muted small">${ctx.escapeHtml(row.reason || '—')}</td>
                    <td class="text-muted small">${ctx.escapeHtml(row.created_by || '—')}</td>
                </tr>
            `;
        }
    });
});
</script>
@endpush
