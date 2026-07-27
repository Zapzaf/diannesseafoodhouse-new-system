@extends('layouts.app')

@section('page_title', 'Table Management - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Table Management" subtitle="Manage restaurant tables, status, and assignments." icon="layout">
        <a class="btn btn-primary" href="{{ route('tables.create') }}">
            <i class="me-1" data-lucide="plus"></i> Add Table
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <ul class="nav nav-tabs card-header-tabs" id="tableStatusTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-status="" type="button" role="tab" aria-selected="true">All Tables</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-status="available" type="button" role="tab" aria-selected="false">Available</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-status="occupied" type="button" role="tab" aria-selected="false">Occupied</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-status="reserved" type="button" role="tab" aria-selected="false">Reserved</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-status="cleaning" type="button" role="tab" aria-selected="false">Cleaning</button>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <input type="hidden" id="statusFilter" value="">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search tables..." style="max-width: 280px;">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tablesTable" data-server-sort="1">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Table Number</th>
                                <th>Branch</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Current Order</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"><tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="tableInfo" class="text-muted small"></div>
                <nav aria-label="Table pagination">
                    <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
                </nav>
            </div>
    </div>

<div class="modal fade" id="assignTableModal" tabindex="-1" aria-labelledby="assignTableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignTableModalLabel">Assign Table to Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assignTableId" value="">
                <div class="mb-3">
                    <label class="form-label fw-bold">Order ID</label>
                    <input type="number" id="assignOrderId" class="form-control" min="1" placeholder="Enter menu order ID">
                </div>
                <div id="assignTableError" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary text-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="assignTableBtn">Assign Table</button>
            </div>
        </div>
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
    const assignTableModal = new bootstrap.Modal(document.getElementById('assignTableModal'));
    const assignOrderId = document.getElementById('assignOrderId');
    const assignTableError = document.getElementById('assignTableError');
    const assignTableId = document.getElementById('assignTableId');

    function renderStatusBadge(status) {
        const badgeClass = {
            available: 'success',
            occupied: 'danger',
            reserved: 'warning',
            cleaning: 'info'
        }[status] || 'secondary';
        return `<span class="badge bg-${badgeClass}">${status}</span>`;
    }

    window.IndexTableBridgeReload = null;
    IndexTableBridge.init({
        tableId: 'tablesTable',
        dataUrl: @json(route('tables.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        filterInputId: 'statusFilter',
        filterParam: 'status',
        sortColumns: ['table_number', 'capacity', 'status', 'branch_id', 'current_order_id', 'created_at'],
        defaultSort: 'table_number',
        defaultDirection: 'asc',
        emptyMessage: 'No tables found.',
        colspan: 7,
        renderRow: function(table, ctx) {
            const actions = [];
            actions.push(`<a href="${window.location.origin}/tables/${table.id}/edit" class="btn btn-sm btn-outline-secondary" title="Edit"><i data-lucide="edit"></i></a>`);
            actions.push(`<button type="button" class="btn btn-sm btn-outline-${table.status === 'available' ? 'primary' : 'secondary'}" title="${table.status === 'available' ? 'Assign' : 'Release'}" onclick="handleTableAction(${table.id}, '${table.status}')">${table.status === 'available' ? '<i data-lucide="plus-square"></i>' : '<i data-lucide="refresh-cw"></i>'}</button>`);
            actions.push(`<form action="${window.location.origin}/tables/${table.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this table?')">
                    <input type="hidden" name="_token" value="${csrf}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i data-lucide="trash-2"></i></button>
                </form>`);

            return `
                <tr>
                    <td>${ctx.index}</td>
                    <td>${ctx.escapeHtml(table.table_number)}</td>
                    <td>${ctx.escapeHtml(table.branch?.name || '—')}</td>
                    <td>${ctx.escapeHtml(table.capacity)}</td>
                    <td>${renderStatusBadge(ctx.escapeHtml(table.status))}</td>
                    <td>${table.current_order_id ? '#'+ctx.escapeHtml(table.current_order_id) : '—'}</td>
                    <td class="table-actions-cell text-nowrap">${actions.join(' ')}</td>
                </tr>
            `;
        }
    });

    function syncActiveStatusTab() {
        document.querySelectorAll('#tableStatusTabs [data-status]').forEach(function (tabButton) {
            tabButton.classList.toggle('active', (tabButton.getAttribute('data-status') || '') === statusFilter.value);
        });
    }

    document.querySelectorAll('#tableStatusTabs [data-status]').forEach(function(tabButton) {
        tabButton.addEventListener('click', function() {
            statusFilter.value = tabButton.getAttribute('data-status') || '';
            syncActiveStatusTab();
            statusFilter.dispatchEvent(new Event('change'));
        });
    });

    // Keep the active tab in sync when IndexTableBridge restores a saved
    // filter value from the URL (e.g. after Back-navigating from an edit page).
    statusFilter.addEventListener('change', syncActiveStatusTab);
    syncActiveStatusTab();

    window.handleTableAction = function(tableId, status) {
        if (status === 'available') {
            assignTableId.value = String(tableId);
            assignOrderId.value = '';
            assignTableError.classList.add('d-none');
            assignTableModal.show();
            return;
        }

        if (!confirm('Release this table and free it for new assignments?')) {
            return;
        }

        fetch(@json(url('/tables')) + '/' + tableId + '/release', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({}),
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data?.message) {
                window.location.reload = null;
            }
            if (typeof window.IndexTableBridgeReload === 'function') {
                window.IndexTableBridgeReload();
            } else {
                window.location.reload();
            }
        });
    };

    document.getElementById('assignTableBtn').addEventListener('click', function() {
        const tableId = Number(assignTableId.value || 0);
        const orderId = Number(assignOrderId.value || 0);
        assignTableError.classList.add('d-none');

        if (!orderId) {
            assignTableError.textContent = 'Please enter a valid order ID.';
            assignTableError.classList.remove('d-none');
            return;
        }

        fetch(@json(url('/tables')) + '/' + tableId + '/assign', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ order_id: orderId }),
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data?.message && !data?.errors) {
                assignTableModal.hide();
                if (typeof window.IndexTableBridgeReload === 'function') {
                    window.IndexTableBridgeReload();
                } else {
                    window.location.reload();
                }
                return;
            }

            assignTableError.textContent = data?.message || 'Unable to assign the table.';
            assignTableError.classList.remove('d-none');
        });
    });
});
</script>
@endpush

