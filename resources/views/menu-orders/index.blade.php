@extends('layouts.app')

@section('page_title', 'Menu Orders - Dianne\'s Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="shopping-bag"></i></div>
                            Menu Orders
                        </h1>
                        <div class="page-header-subtitle">Track standalone food purchases and payments</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a class="btn btn-light text-primary" href="{{ route('menu-orders.create') }}">
                            <i class="me-1" data-feather="plus"></i> New Menu Order
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <ul class="nav nav-tabs card-header-tabs" id="menuOrderStatusTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-status="open" type="button" role="tab" aria-selected="true">Open Orders</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-status="completed" type="button" role="tab" aria-selected="false">Completed</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-status="cancelled" type="button" role="tab" aria-selected="false">Cancelled</button>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <input type="hidden" id="statusFilter" value="open">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search menu orders..." style="max-width: 280px;">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="menuOrdersTable" data-server-sort="1">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Branch</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"><tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="tableInfo" class="text-muted small"></div>
                <nav aria-label="Menu orders pagination">
                    <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
                </nav>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script src="{{ asset('js/custom-table.js') }}"></script>
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const statusFilter = document.getElementById('statusFilter');

    IndexTableBridge.init({
        tableId: 'menuOrdersTable',
        dataUrl: @json(route('menu-orders.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        filterInputId: 'statusFilter',
        filterParam: 'status',
        sortColumns: ['id', 'created_at', 'total_amount', 'amount_paid', 'balance', 'payment_status', 'status'],
        defaultSort: 'created_at',
        defaultDirection: 'desc',
        emptyMessage: 'No menu orders found.',
        colspan: 9,
        renderRow: function(order, ctx) {
            const customer = order.customer_name || 'Walk-in Customer';
            const canEdit = Number(order.payments_count || 0) === 0;
            const editActions = canEdit ? `
                <a href="{{ url('/menu-orders') }}/${order.id}/edit" class="btn btn-sm btn-outline-secondary" title="Edit"><i data-feather="edit"></i></a>
                <form action="{{ url('/menu-orders') }}/${order.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this menu order?')">
                    <input type="hidden" name="_token" value="${csrf}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i data-feather="trash-2"></i></button>
                </form>
            ` : '';

            return `
                <tr>
                    <td>${ctx.index}</td>
                    <td>${ctx.escapeHtml(customer)}</td>
                    <td>${ctx.escapeHtml(order.branch ? order.branch.name : '\u2014')}</td>
                    <td>\u20B1${Number(order.total_amount || 0).toFixed(2)}</td>
                    <td class="text-success">\u20B1${Number(order.amount_paid || 0).toFixed(2)}</td>
                    <td class="${Number(order.balance || 0) > 0 ? 'text-danger fw-bold' : ''}">\u20B1${Number(order.balance || 0).toFixed(2)}</td>
                    <td><span class="badge badge-status badge-${ctx.escapeHtml(order.payment_status || 'unpaid')}">${ctx.escapeHtml(order.payment_status || 'unpaid')}</span></td>
                    <td><span class="badge badge-status badge-${ctx.escapeHtml(order.status || 'open')}">${ctx.escapeHtml(order.status || 'open')}</span></td>
                    <td class="text-nowrap">
                        <a href="{{ url('/menu-orders') }}/${order.id}" class="btn btn-sm btn-outline-info" title="View"><i data-feather="eye"></i></a>
                        ${editActions}
                    </td>
                </tr>
            `;
        }
    });

    document.querySelectorAll('#menuOrderStatusTabs [data-status]').forEach(function(tabButton) {
        tabButton.addEventListener('click', function() {
            document.querySelectorAll('#menuOrderStatusTabs .nav-link').forEach((tab) => tab.classList.remove('active'));
            tabButton.classList.add('active');
            statusFilter.value = tabButton.getAttribute('data-status') || 'open';
            statusFilter.dispatchEvent(new Event('change'));
        });
    });
});
</script>
@endpush
