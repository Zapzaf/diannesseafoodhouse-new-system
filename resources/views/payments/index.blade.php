@extends('layouts.app')
@section('page_title', 'Payments')
@section('content')
    <x-page-header title="Payments" subtitle="Review recorded payments, methods, and receivable coverage" icon="credit-card">
        <a href="{{ route('payments.create') }}" class="btn btn-light text-primary">
            <i data-lucide="plus-circle" class="me-1"></i> Record Payment
        </a>
    </x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-end">
            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search payments..." style="max-width: 280px;">
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="paymentsTable" data-server-sort="1">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Room / Source</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th class="table-actions-head">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"><tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div id="tableInfo" class="text-muted small"></div>
            <nav aria-label="Payments pagination">
                <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
            </nav>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/custom-table.js') }}"></script>
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    IndexTableBridge.init({
        tableId: 'paymentsTable',
        dataUrl: @json(route('payments.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        sortColumns: ['id', 'transaction_type', 'transaction_reference', 'payment_date', 'customer_name', 'method', 'amount', 'reference_number'],
        defaultSort: 'payment_date',
        defaultDirection: 'desc',
        emptyMessage: 'No payments found.',
        colspan: 9,
        renderRow: function(payment, ctx) {
            const typeLabel = payment.transaction_type === 'menu_order' ? 'Menu Order' : 'Check-in';
            const detailAction = payment.detail_url
                ? `<a href="${payment.detail_url}" class="btn btn-sm btn-info text-white" title="View"><i data-lucide="eye"></i></a>`
                : '';
            const receiptAction = payment.receipt_url
                ? `<a href="${payment.receipt_url}" target="_blank" class="btn btn-sm btn-secondary text-white" title="Print Receipt"><i data-lucide="printer"></i></a>`
                : '';

            return `
                <tr>
                    <td>${ctx.index}</td>
                    <td><span class="badge bg-secondary">${ctx.escapeHtml(typeLabel)}</span></td>
                    <td>${ctx.escapeHtml(payment.transaction_reference || '—')}</td>
                    <td>${ctx.formatDate(payment.payment_date)}</td>
                    <td>${ctx.escapeHtml(payment.customer_name || '—')}</td>
                    <td>${ctx.escapeHtml(payment.room_or_order || '—')}</td>
                    <td><span class="badge bg-secondary">${ctx.escapeHtml(payment.method || '')}</span></td>
                    <td class="fw-semibold">\u20B1${Number(payment.amount || 0).toFixed(2)}</td>
                    <td class="text-muted small">${ctx.escapeHtml(payment.reference_number || '-')}</td>
                    <td class="table-actions-cell text-nowrap">${detailAction}${receiptAction}</td>
                </tr>
            `;
        }
    });
});
</script>
@endpush





