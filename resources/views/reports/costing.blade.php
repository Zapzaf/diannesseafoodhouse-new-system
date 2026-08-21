@extends('layouts.app')
@section('page_title', 'Costing Reports - Dianne Seafood House')
@section('content')
<x-page-header title="Costing Reports" subtitle="Price change requests, approvals, and audit history" icon="dollar-sign">
    <a href="{{ route('reports.costing.create') }}" class="btn btn-light">
        <i data-lucide="plus" class="me-1"></i> New Costing Report
    </a>
</x-page-header>

@php
    $pendingCount = (int) ($statusCounts['pending'] ?? 0);
    $approvedCount = (int) ($statusCounts['approved'] ?? 0);
    $rejectedCount = (int) ($statusCounts['rejected'] ?? 0);
@endphp

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i data-lucide="clock" class="text-warning" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending</div>
                        <div class="fs-4 fw-bold text-warning">{{ number_format($pendingCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-lucide="check-circle" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Approved</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($approvedCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i data-lucide="x-circle" class="text-danger" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Rejected</div>
                        <div class="fs-4 fw-bold text-danger">{{ number_format($rejectedCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3" style="background-color: rgba(240, 124, 89, 0.1) !important;">
                        <i data-lucide="file-text" class="text-primary" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Reports Shown</div>
                        <div class="fs-4 fw-bold">{{ number_format($reports->total()) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.costing.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.costing.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <i data-lucide="clipboard" class="me-1"></i> Report History
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle" id="costingReportsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Branch</th>
                            <th class="text-end">Price Change</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Requested By</th>
                            <th>Reviewed By</th>
                            <th>Submitted</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"><tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div id="tableInfo" class="text-muted small"></div>
            <nav aria-label="Report history pagination">
                <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
            </nav>
        </div>
    </div>

    <input type="hidden" id="costingDateFromFilter" value="{{ $dateFrom }}">
    <input type="hidden" id="costingDateToFilter" value="{{ $dateTo }}">
    <input type="hidden" id="costingStatusFilter" value="{{ $status }}">

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i data-lucide="archive" class="me-1"></i> Current Item Cost Reference
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="costingItemsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Item</th>
                            <th>Branch</th>
                            <th class="text-end">Current Unit Price</th>
                            <th class="text-end">Latest Approved Unit Cost</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody"><tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div id="itemsTableInfo" class="text-muted small"></div>
            <nav aria-label="Item cost reference pagination">
                <ul id="itemsPagination" class="pagination pagination-sm mb-0"></ul>
            </nav>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    IndexTableBridge.init({
        tableId: 'costingReportsTable',
        dataUrl: @json(route('reports.costing.data')),
        filters: [
            { inputId: 'costingDateFromFilter', param: 'date_from' },
            { inputId: 'costingDateToFilter', param: 'date_to' },
            { inputId: 'costingStatusFilter', param: 'status' }
        ],
        emptyMessage: 'No costing reports found.',
        colspan: 10,
        renderRow: function (row, ctx) {
            const delta = Number(row.delta || 0);
            const deltaClass = delta > 0 ? 'text-success' : (delta < 0 ? 'text-danger' : 'text-muted');
            const deltaSign = delta > 0 ? '+' : '';
            const statusBadge = row.status === 'approved' ? 'badge-approved' : (row.status === 'rejected' ? 'badge-expired' : 'badge-pending');

            return `
                <tr>
                    <td class="text-muted small">${row.id}</td>
                    <td class="fw-semibold">
                        ${ctx.escapeHtml(row.item_name)}
                        <div class="small text-muted">${ctx.escapeHtml(row.category_name)}</div>
                    </td>
                    <td class="text-muted small">${ctx.escapeHtml(row.branch_name)}</td>
                    <td class="text-end text-nowrap">
                        <span class="text-muted small">₱${Number(row.current_price || 0).toFixed(2)}</span>
                        <i data-lucide="arrow-right" class="mx-1 text-muted" style="width:12px;height:12px;"></i>
                        <span class="fw-semibold">₱${Number(row.proposed_price || 0).toFixed(2)}</span>
                        <div class="small fw-semibold ${deltaClass}">${deltaSign}${delta.toFixed(2)}</div>
                    </td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary">${ctx.escapeHtml(row.source_label)}</span></td>
                    <td><span class="badge-status ${statusBadge}">${ctx.escapeHtml(row.status.toUpperCase())}</span></td>
                    <td class="text-muted small">${ctx.escapeHtml(row.requester_name)}</td>
                    <td class="text-muted small">${ctx.escapeHtml(row.approver_name || '—')}</td>
                    <td class="text-muted small text-nowrap">${ctx.escapeHtml(row.created_at || '')}</td>
                    <td class="table-actions-cell text-end">
                        <a href="${row.show_url}" class="btn btn-sm btn-info text-white" title="View report">
                            <i data-lucide="eye" style="width:15px;height:15px;"></i>
                        </a>
                    </td>
                </tr>
            `;
        }
    });

    IndexTableBridge.init({
        tableId: 'costingItemsTable',
        tbodyId: 'itemsTableBody',
        paginationId: 'itemsPagination',
        infoId: 'itemsTableInfo',
        stateKey: 'costingItemsTable',
        dataUrl: @json(route('reports.costing.items-data')),
        emptyMessage: 'No items found.',
        colspan: 5,
        renderRow: function (row, ctx) {
            const latestCost = row.latest_unit_cost !== null ? '₱' + Number(row.latest_unit_cost).toFixed(2) : '-';
            return `
                <tr>
                    <td class="fw-semibold">
                        ${ctx.escapeHtml(row.name)}
                        <div class="small text-muted">${ctx.escapeHtml(row.location_name)} / ${ctx.escapeHtml(row.category_name)}</div>
                    </td>
                    <td>${ctx.escapeHtml(row.branch_name)}</td>
                    <td class="text-end">₱${Number(row.unit_price || 0).toFixed(2)}</td>
                    <td class="text-end">${latestCost}</td>
                    <td class="table-actions-cell text-end">
                        <a href="${row.request_url}" class="btn btn-sm btn-primary text-white" title="Request price change">
                            <i data-lucide="file-plus" style="width:15px;height:15px;"></i>
                        </a>
                    </td>
                </tr>
            `;
        }
    });
});
</script>
@endpush
