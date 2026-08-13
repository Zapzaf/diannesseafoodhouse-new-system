@extends('layouts.app')
@section('page_title', 'Delivery Report - Dianne Seafood House')
@section('content')
<x-page-header title="Delivery Report" subtitle="Delivery history with status breakdown" icon="truck">
    <a class="btn btn-success text-white" href="{{ route('reports.delivery.export', request()->query()) }}">
        <i data-lucide="download" class="me-1"></i> Export Excel
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.delivery.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="received" {{ $status === 'received' ? 'selected' : '' }}>Received</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.delivery.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i data-lucide="clock" class="text-warning" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Deliveries</div>
                        <div class="fs-4 fw-bold text-warning">{{ number_format($pendingCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-lucide="check-circle" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Received Deliveries</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($receivedCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3" style="background-color: rgba(240, 124, 89, 0.1) !important;">
                        <i data-lucide="wallet" class="text-primary" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Delivery Cost</div>
                        <div class="fs-4 fw-bold text-primary">₱{{ number_format((float) $totalDeliveryCost, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="truck" class="me-1"></i> Delivery Items</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="deliveryItemsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Delivery Reference</th>
                            <th>Date</th>
                            <th>Destination</th>
                            <th>Source</th>
                            <th>Item</th>
                            <th class="text-end">Quantity</th>
                            <th>Unit</th>
                            <th class="text-end">Cost</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"><tr><td colspan="11" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div id="tableInfo" class="text-muted small"></div>
            <nav aria-label="Delivery items pagination">
                <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
            </nav>
        </div>
    </div>

    {{-- Current filter values, mirrored into IndexTableBridge as fixed
         (non-interactive) filters — this keeps date_from/date_to/status in
         every paginated fetch AND preserved in the URL after page/per-page
         changes. They only actually change via the "Apply" reload above. --}}
    <input type="hidden" id="deliveryDateFromFilter" value="{{ $dateFrom }}">
    <input type="hidden" id="deliveryDateToFilter" value="{{ $dateTo }}">
    <input type="hidden" id="deliveryStatusFilter" value="{{ $status }}">
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    IndexTableBridge.init({
        tableId: 'deliveryItemsTable',
        dataUrl: @json(route('reports.delivery.data')),
        filters: [
            { inputId: 'deliveryDateFromFilter', param: 'date_from' },
            { inputId: 'deliveryDateToFilter', param: 'date_to' },
            { inputId: 'deliveryStatusFilter', param: 'status' }
        ],
        emptyMessage: 'No deliveries in this period.',
        colspan: 11,
        renderRow: function (row, ctx) {
            const statusLabel = (row.status || '').toUpperCase();
            return `
                <tr>
                    <td class="fw-semibold text-nowrap">${ctx.escapeHtml(row.reference_number)}</td>
                    <td class="text-nowrap text-muted small">${ctx.escapeHtml(row.date || '—')}</td>
                    <td>${ctx.escapeHtml(row.destination || '—')}</td>
                    <td>${ctx.escapeHtml(row.source || '—')}</td>
                    <td class="fw-semibold">${ctx.escapeHtml(row.item_name)}</td>
                    <td class="text-end">${Number(row.quantity || 0).toFixed(2)}</td>
                    <td>${ctx.escapeHtml(row.unit || '')}</td>
                    <td class="text-end fw-semibold">₱${Number(row.cost || 0).toFixed(2)}</td>
                    <td><span class="badge-status badge-${ctx.escapeHtml(row.status)}">${ctx.escapeHtml(statusLabel)}</span></td>
                    <td class="text-muted small">${ctx.escapeHtml(row.approved_by || '—')}</td>
                    <td class="text-muted small">${ctx.escapeHtml(row.created_by || '—')}</td>
                </tr>
            `;
        }
    });
});
</script>
@endpush
