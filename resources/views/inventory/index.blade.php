@extends('layouts.app')

@section('page_title', 'Inventory - Dianne Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="package"></i></div>
                            Inventory
                        </h1>
                        <div class="page-header-subtitle">Track stock levels, suppliers, and replenishment activity</div>
                    </div>
                    <div class="col-auto mt-4 d-flex gap-2">
                        <a class="btn btn-light text-primary" href="{{ route('inventory.transactions') }}">
                            <i class="me-1" data-feather="list"></i> Transactions
                        </a>
                        <a class="btn btn-light text-primary" href="{{ route('inventory.create') }}">
                            <i class="me-1" data-feather="plus-circle"></i> Add Item
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><i class="me-1" data-feather="archive"></i> Inventory Items</div>
                <div class="d-flex gap-2">
                    <select id="perPage" class="form-select form-select-sm" style="width: auto;">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search inventory..." style="max-width: 280px;">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="inventoryTable" data-server-sort="1">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Branch</th>
                                <th>Location</th>
                                <th>Category</th>
                                <th>Remaining Item</th>
                                <th>Unit</th>
                                <th>Low Stock Threshold</th>
                                <th>Supplier</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"><tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="tableInfo" class="text-muted small"></div>
                <nav aria-label="Inventory pagination">
                    <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
                </nav>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="stockInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="POST" id="stockInForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Stock In: <span id="modalItemName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantity</label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Transaction Date</label>
                        <input type="datetime-local" name="transaction_date" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="Purchase order or stock replenishment note">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Stock</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deductStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="POST" id="deductStockForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Deduct Stock: <span id="deductItemName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Remaining stock: <span id="deductRemainingStock"></span></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantity</label>
                        <input type="number" name="quantity" id="deductQuantity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Transaction Date</label>
                        <input type="datetime-local" name="transaction_date" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="Damage, usage, manual adjustment" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Deduct Stock</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="transferForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i data-feather="send" class="me-2" style="width:18px;height:18px;"></i>Transfer to Branch: <span id="transferItemName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Available stock: <span id="transferRemainingStock"></span></p>
                    <div class="alert alert-info small py-2">
                        Select the destination branch and item. The transfer will be recorded as a delivery and will remain <strong>PENDING</strong> until approved by an admin.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Source Branch</label>
                        <input type="text" class="form-control" id="transferSourceBranch" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Destination Branch <span class="text-danger">*</span></label>
                        <select name="destination_branch_id" class="form-select" id="transferDestinationBranch" required>
                            <option value="">Select destination branch</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Destination Item <span class="text-danger">*</span></label>
                        <select name="destination_item_id" class="form-select" id="transferDestinationItem" required disabled>
                            <option value="">Select destination item</option>
                        </select>
                        <div class="small text-muted mt-1" id="transferItemHelp">Choose a destination branch first.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantity to Transfer <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="transferQuantity" class="form-control" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reference/Note (optional)</label>
                        <input type="text" name="reference" class="form-control" placeholder="Transfer reference or note">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">
                        <i data-feather="send" class="me-1" style="width:14px;height:14px;"></i> Initiate Transfer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showStockIn(id, name) {
    document.getElementById('modalItemName').textContent = name;
    document.getElementById('stockInForm').action = '{{ url('/inventory') }}/' + id + '/stock-in';
    new bootstrap.Modal(document.getElementById('stockInModal')).show();
}

function showDeductStock(id, name, remainingStock) {
    document.getElementById('deductItemName').textContent = name;
    document.getElementById('deductRemainingStock').textContent = remainingStock;
    document.getElementById('deductQuantity').max = remainingStock;
    document.getElementById('deductStockForm').action = '{{ url('/inventory') }}/' + id + '/deduct';
    new bootstrap.Modal(document.getElementById('deductStockModal')).show();
}

function showTransfer(id, name, remainingStock, sourceBranchName, sourceBranchId) {
    document.getElementById('transferItemName').textContent = name;
    document.getElementById('transferRemainingStock').textContent = remainingStock;
    document.getElementById('transferSourceBranch').value = sourceBranchName || 'Current Branch';
    document.getElementById('transferQuantity').max = remainingStock;
    document.getElementById('transferForm').action = '{{ url('/inventory') }}/' + id + '/transfer';
    const destBranch = document.getElementById('transferDestinationBranch');
    const destItem = document.getElementById('transferDestinationItem');
    const help = document.getElementById('transferItemHelp');
    if (destBranch) destBranch.value = '';
    if (destItem) {
        destItem.innerHTML = '<option value=\"\">Select destination item</option>';
        destItem.disabled = true;
        destItem.value = '';
    }
    if (help) help.textContent = 'Choose a destination branch first.';

    // Disable/hide the source branch in the destination dropdown
    if (destBranch && sourceBranchId) {
        const options = destBranch.querySelectorAll('option');
        options.forEach(option => {
            if (parseInt(option.value) === parseInt(sourceBranchId)) {
                option.disabled = true;
                option.hidden = true;
            } else {
                option.disabled = false;
                option.hidden = false;
            }
        });
    }

    new bootstrap.Modal(document.getElementById('transferModal')).show();
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const branchSelect = document.getElementById('transferDestinationBranch');
    const itemSelect = document.getElementById('transferDestinationItem');
    const help = document.getElementById('transferItemHelp');

    if (!branchSelect || !itemSelect) return;

    branchSelect.addEventListener('change', async function () {
        const branchId = this.value ? parseInt(this.value) : 0;
        itemSelect.innerHTML = '<option value=\"\">Select destination item</option>';
        itemSelect.disabled = true;
        if (help) help.textContent = branchId ? 'Loading items...' : 'Choose a destination branch first.';
        if (!branchId) return;

        try {
            const res = await fetch(@json(url('/inventory/branch')) + '/' + branchId + '/items', {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Failed to load items.');
            const items = await res.json();

            itemSelect.innerHTML = '<option value=\"\">Select destination item</option>' + items.map(i => {
                const label = `#${i.id} - ${i.name}`;
                return `<option value=\"${i.id}\">${escapeHtml(label)}</option>`;
            }).join('');
            itemSelect.disabled = false;
            if (help) help.textContent = items.length ? 'Select the destination item in this branch.' : 'No items found in this branch.';
        } catch (e) {
            if (help) help.textContent = 'Failed to load items. Please try again.';
        }
    });

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
});
</script>
<script src="{{ asset('js/custom-table.js') }}"></script>
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    IndexTableBridge.init({
        tableId: 'inventoryTable',
        dataUrl: @json(route('inventory.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        sortColumns: ['name', 'category', 'branch_name', 'location', 'remaining_item', 'unit', 'low_stock_threshold', 'supplier_name'],
        defaultSort: 'name',
        defaultDirection: 'asc',
        emptyMessage: 'No inventory items found.',
        colspan: 10,
        renderRow: function(item, ctx) {
            const remaining = Number(item.remaining_item || 0);
            const threshold = Number(item.low_stock_threshold || 0);
            const lowStock = remaining <= threshold;
            const itemName = ctx.escapeHtml(item.name || '').replace(/"/g, "&quot;");
            const branchName = ctx.escapeHtml(item.branch_name || 'Current Branch').replace(/"/g, "&quot;");
            return `
                <tr class="${lowStock ? 'table-warning' : ''}">
                    <td><span class="fw-semibold">${ctx.escapeHtml(item.name)}</span>${lowStock ? '<span class="badge bg-danger ms-1">Low Stock</span>' : ''}</td>
                    <td>${ctx.escapeHtml(item.branch_name || '—')}</td>
                    <td>${ctx.escapeHtml(item.location || '—')}</td>
                    <td>${ctx.escapeHtml(item.category || item.category_label || '—')}</td>
                    <td>${remaining}</td>
                    <td>${ctx.escapeHtml(item.unit || 'N/A')}</td>
                    <td>${threshold}</td>
                    <td>${ctx.escapeHtml(item.supplier_name || 'N/A')}</td>
                    <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-success stockin-btn" data-id="${item.id}" data-name="${itemName}"><i data-feather="plus-circle"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-warning deduct-btn" data-id="${item.id}" data-name="${itemName}" data-remaining="${remaining}"><i data-feather="minus-circle"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-info transfer-btn" data-id="${item.id}" data-name="${itemName}" data-remaining="${remaining}" data-branch-name="${branchName}" data-branch-id="${item.branch_id || ''}" title="Transfer to another branch"><i data-feather="send"></i></button>
                            <a href="{{ url('/inventory') }}/${item.id}/edit" class="btn btn-sm btn-outline-primary"><i data-feather="edit-2"></i></a>
                            <form action="{{ url('/inventory') }}/${item.id}" method="POST" onsubmit="return confirm('Delete this item?')">
                                <input type="hidden" name="_token" value="${csrf}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i data-feather="trash-2"></i></button>
                            </form></td>
                </tr>
            `;
        }
    });

    document.getElementById('inventoryTable').addEventListener('click', function(e) {
        const btn = e.target.closest('button');
        if (!btn) return;
        
        if (btn.classList.contains('stockin-btn')) {
            showStockIn(btn.dataset.id, btn.dataset.name);
        } else if (btn.classList.contains('deduct-btn')) {
            showDeductStock(btn.dataset.id, btn.dataset.name, btn.dataset.remaining);
        } else if (btn.classList.contains('transfer-btn')) {
            showTransfer(btn.dataset.id, btn.dataset.name, btn.dataset.remaining, btn.dataset.branchName, btn.dataset.branchId);
        }
    });
});
</script>
@endpush