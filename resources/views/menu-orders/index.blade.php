@extends('layouts.app')

@section('page_title', 'Menu Orders - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Menu Orders" subtitle="Track standalone food purchases and payments" icon="shopping-bag">
        <a class="btn btn-primary" href="{{ route('menu-orders.create') }}">
            <i class="me-1" data-lucide="plus"></i> New Menu Order
        </a>
    </x-page-header>

    <div class="container-xl px-4 menu-order-workspace">
        @include('layouts.alerts')
        <div class="card shadow-sm menu-order-index-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 menu-order-index-header">
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
                    <input type="text" id="searchInput" class="form-control form-control-sm menu-order-search" placeholder="Search menu orders...">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped menu-order-list-table" id="menuOrdersTable" data-server-sort="1">
                        <thead>
                            <tr>
                                <th>Sales ID</th>
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

<div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="voidForm" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i data-lucide="alert-triangle"></i> Void Menu Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to void this menu order? This action will replenish all deducted inventory. This cannot be undone.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Voiding <span class="text-danger">*</span></label>
                        <textarea name="void_reason" class="form-control" rows="3" required placeholder="Please provide a reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Confirm Void</button>
                </div>
            </div>
        </form>
    </div>
</div>

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
            const isBranchManager = {{ auth()->user()->isBranchManager() ? 'true' : 'false' }};
            const canVoid = isBranchManager && order.status !== 'voided' && order.status !== 'cancelled' && Number(order.payments_count || 0) === 0;

            const editActions = canEdit ? `
                <a href="{{ url('/menu-orders') }}/${order.id}/edit" class="btn btn-sm btn-primary text-white" title="Edit"><i data-lucide="edit"></i></a>
                <form action="{{ url('/menu-orders') }}/${order.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this menu order?')">
                    <input type="hidden" name="_token" value="${csrf}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-danger text-white" title="Delete"><i data-lucide="trash-2"></i></button>
                </form>
            ` : '';

            const voidAction = canVoid ? `
                <button type="button" class="btn btn-sm btn-warning text-white" title="Void Order" onclick="openVoidModal(${order.id})">
                    <i data-lucide="slash"></i>
                </button>
            ` : '';

            return `
                <tr>
                    <td class="fw-semibold">${ctx.escapeHtml(order.order_number || ('SALES-BR' + order.branch_id + '-' + order.id))}</td>
                    <td>${ctx.escapeHtml(customer)}</td>
                    <td>${ctx.escapeHtml(order.branch ? order.branch.name : '\u2014')}</td>
                    <td>\u20B1${Number(order.total_amount || 0).toFixed(2)}</td>
                    <td class="text-success">\u20B1${Number(order.amount_paid || 0).toFixed(2)}</td>
                    <td class="${Number(order.balance || 0) > 0 ? 'text-danger fw-bold' : ''}">\u20B1${Number(order.balance || 0).toFixed(2)}</td>
                    <td><span class="badge badge-status badge-${ctx.escapeHtml(order.payment_status || 'unpaid')}">${ctx.escapeHtml(order.payment_status || 'unpaid')}</span></td>
                    <td><span class="badge badge-status badge-${ctx.escapeHtml(order.status || 'open')}">${ctx.escapeHtml(order.status || 'open')}</span></td>
                    <td class="table-actions-cell text-nowrap">
                        <a href="{{ url('/menu-orders') }}/${order.id}" class="btn btn-sm btn-info text-white" title="View"><i data-lucide="eye"></i></a>
                        ${editActions}
                        ${voidAction}
                    </td>
                </tr>
            `;
        }
    });

    function syncActiveStatusTab() {
        document.querySelectorAll('#menuOrderStatusTabs [data-status]').forEach(function (tabButton) {
            tabButton.classList.toggle('active', (tabButton.getAttribute('data-status') || 'open') === statusFilter.value);
        });
    }

    document.querySelectorAll('#menuOrderStatusTabs [data-status]').forEach(function(tabButton) {
        tabButton.addEventListener('click', function() {
            statusFilter.value = tabButton.getAttribute('data-status') || 'open';
            syncActiveStatusTab();
            statusFilter.dispatchEvent(new Event('change'));
        });
    });

    // Keep the active tab in sync when IndexTableBridge restores a saved
    // filter value from the URL (e.g. after Back-navigating from an edit page).
    statusFilter.addEventListener('change', syncActiveStatusTab);
    syncActiveStatusTab();
});

function openVoidModal(orderId) {
    var form = document.getElementById('voidForm');
    form.action = '{{ url('/menu-orders') }}/' + orderId + '/void';
    var modal = new bootstrap.Modal(document.getElementById('voidModal'));
    modal.show();
}
</script>
@endpush
