@extends('layouts.app')
@section('page_title', 'Transaction Report - Dianne Seafood House')
@section('content')
<x-page-header title="Transaction Report" subtitle="Stock movement history with filters" icon="list">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.transaction.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
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
                        <div class="fs-4 fw-bold text-success">{{ number_format($stockIn, 2) }}</div>
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
                        <div class="fs-4 fw-bold text-danger">{{ number_format($stockOut, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Transactions</div>
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
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    IndexTableBridge.init({
        tableId: 'transactionsTable',
        dataUrl: @json(route('reports.transaction.data')),
        filters: [
            { inputId: 'transactionDateFromFilter', param: 'date_from' },
            { inputId: 'transactionDateToFilter', param: 'date_to' },
            { inputId: 'transactionTypeFilter', param: 'type' }
        ],
        emptyMessage: 'No transactions in this period.',
        colspan: 9,
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
