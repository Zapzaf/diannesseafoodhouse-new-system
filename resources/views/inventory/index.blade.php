@extends('layouts.app')

@section('page_title', 'Inventory - Dianne Seafood House')

@section('content')
    <x-page-header title="Inventory" subtitle="Track stock levels, suppliers, and replenishment activity" icon="package">
        <button type="button" class="btn btn-success text-white" data-bs-toggle="modal" data-bs-target="#exportInventoryModal">
            <i class="me-1" data-lucide="download"></i> Export Excel
        </button>
        <a class="btn btn-light text-primary" href="{{ route('inventory.transactions') }}">
            <i class="me-1" data-lucide="list"></i> Transactions
        </a>
        <a class="btn btn-primary" href="{{ route('inventory.create') }}">
            <i class="me-1" data-lucide="plus-circle"></i> Add Item
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="d-flex align-items-end gap-3 flex-wrap">
                    <div class="flex-grow-1" style="min-width: 240px;">
                        <label for="searchInput" class="form-label small fw-semibold text-muted mb-1">Search Inventory</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i data-lucide="search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search item, category, or location...">
                        </div>
                    </div>
                    <div style="min-width: 180px;">
                        <label for="categoryFilter" class="form-label small fw-semibold text-muted mb-1">Category</label>
                        <select id="categoryFilter" class="form-select form-select-sm" aria-label="Filter by category">
                            <option value="">All Categories</option>
                            @foreach($inventoryLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width: 190px;">
                        <label for="subcategoryFilter" class="form-label small fw-semibold text-muted mb-1">Subcategory</label>
                        <select id="subcategoryFilter" class="form-select form-select-sm" aria-label="Filter by subcategory" disabled>
                            <option value="">All Subcategories</option>
                        </select>
                    </div>
                    <div style="width: 110px;">
                        <label for="perPage" class="form-label small fw-semibold text-muted mb-1">Show Rows</label>
                        <select id="perPage" class="form-select form-select-sm" aria-label="Items per page">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="archive"></i> Inventory Items</div>
                <span class="badge bg-primary-soft text-primary">Live inventory</span>
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
                        <tbody id="tableBody"><tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
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
                        <input type="number" name="quantity" id="stockInQuantity" class="form-control" min="1" step="1" required>
                        <div class="form-text" id="stockInQuantityHelp"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Transaction Date</label>
                        <input type="datetime-local" name="transaction_date" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="stockInReasonSelect">Reason</label>
                        <select name="reason" id="stockInReasonSelect" class="form-select" required>
                            <option value="">Select reason</option>
                            <option value="Sales">Sales</option>
                            <option value="Withdrawal">Withdrawal</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="stockInCustomReasonWrapper">
                        <label class="form-label fw-semibold" for="stockInCustomReason">Other Reason</label>
                        <input type="text" name="custom_reason" id="stockInCustomReason" class="form-control" maxlength="255" placeholder="Enter reason">
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

<div class="modal fade" id="exportInventoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('inventory.export') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Please select a branch.</p>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Branch</label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select a branch</option>
                            @foreach($exportBranches as $branch)
                            <option value="{{ $branch->id }}" {{ (string) old('branch_id', $selectedBranchId) === (string) $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="me-1" data-lucide="download"></i> Export
                    </button>
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
                        <input type="number" name="quantity" id="deductQuantity" class="form-control" min="1" step="1" required>
                        <div class="form-text" id="deductQuantityHelp"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Transaction Date</label>
                        <input type="datetime-local" name="transaction_date" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="deductReasonSelect">Reason</label>
                        <select name="reason" id="deductReasonSelect" class="form-select" required>
                            <option value="">Select reason</option>
                            <option value="Sales">Sales</option>
                            <option value="Withdrawal">Withdrawal</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="deductCustomReasonWrapper">
                        <label class="form-label fw-semibold" for="deductCustomReason">Other Reason</label>
                        <input type="text" name="custom_reason" id="deductCustomReason" class="form-control" maxlength="255" placeholder="Enter reason">
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
                    <h5 class="modal-title"><i data-lucide="send" class="me-2" style="width:18px;height:18px;"></i>Transfer to Branch: <span id="transferItemName"></span></h5>
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
                        <i data-lucide="send" class="me-1" style="width:14px;height:14px;"></i> Initiate Transfer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function configureQuantityInput(inputId, helpId, unit, allowsDecimals, maximum = null) {
    const input = document.getElementById(inputId);
    const help = document.getElementById(helpId);
    const decimalQuantity = allowsDecimals === true || allowsDecimals === 'true' || allowsDecimals === '1';
    input.value = '';
    input.min = decimalQuantity ? '0.01' : '1';
    input.step = decimalQuantity ? '0.01' : '1';

    if (maximum !== null) {
        input.max = maximum;
    } else {
        input.removeAttribute('max');
    }

    help.textContent = decimalQuantity
        ? `${unit || 'This unit'} accepts decimal quantities up to 2 decimal places.`
        : `${unit || 'This unit'} accepts whole-number quantities only.`;
}

function showStockIn(id, name, unit, allowsDecimals) {
    document.getElementById('modalItemName').textContent = name;
    document.getElementById('stockInForm').action = '{{ url('/inventory') }}/' + id + '/stock-in';
    configureQuantityInput('stockInQuantity', 'stockInQuantityHelp', unit, allowsDecimals);
    resetReasonDropdown('stockInReasonSelect', 'stockInCustomReason', 'stockInCustomReasonWrapper');
    new bootstrap.Modal(document.getElementById('stockInModal')).show();
}

function showDeductStock(id, name, remainingStock, unit, allowsDecimals) {
    document.getElementById('deductItemName').textContent = name;
    document.getElementById('deductRemainingStock').textContent = remainingStock;
    document.getElementById('deductStockForm').action = '{{ url('/inventory') }}/' + id + '/deduct';
    configureQuantityInput('deductQuantity', 'deductQuantityHelp', unit, allowsDecimals, remainingStock);
    resetReasonDropdown('deductReasonSelect', 'deductCustomReason', 'deductCustomReasonWrapper');
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

function resetReasonDropdown(selectId, inputId, wrapperId) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    const wrapper = document.getElementById(wrapperId);

    if (select) {
        select.value = '';
    }

    if (input) {
        input.value = '';
        input.required = false;
    }

    if (wrapper) {
        wrapper.classList.add('d-none');
    }
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if(session('showExportModal'))
    const exportModalEl = document.getElementById('exportInventoryModal');
    if (exportModalEl) {
        new bootstrap.Modal(exportModalEl).show();
    }
    @endif

    const branchSelect = document.getElementById('transferDestinationBranch');
    const itemSelect = document.getElementById('transferDestinationItem');
    const help = document.getElementById('transferItemHelp');

    setupReasonDropdown('stockInReasonSelect', 'stockInCustomReason', 'stockInCustomReasonWrapper');
    setupReasonDropdown('deductReasonSelect', 'deductCustomReason', 'deductCustomReasonWrapper');

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

    function setupReasonDropdown(selectId, inputId, wrapperId) {
        const select = document.getElementById(selectId);
        const input = document.getElementById(inputId);
        const wrapper = document.getElementById(wrapperId);

        if (!select || !input || !wrapper) {
            return;
        }

        const toggleCustomReason = () => {
            const isOthers = select.value === 'Others';
            wrapper.classList.toggle('d-none', !isOthers);
            input.required = isOthers;

            if (!isOthers) {
                input.value = '';
            }
        };

        select.addEventListener('change', toggleCustomReason);
        toggleCustomReason();
    }
});
</script>
<script src="{{ asset('js/custom-table.js') }}"></script>
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const categoryFilter = document.getElementById('categoryFilter');
    const subcategoryFilter = document.getElementById('subcategoryFilter');
    const subcategoriesByCategory = {{ Illuminate\Support\Js::from($inventorySubcategories) }};

    categoryFilter.addEventListener('change', function () {
        const subcategories = subcategoriesByCategory[this.value] || [];
        subcategoryFilter.innerHTML = '<option value="">All Subcategories</option>' + subcategories.map(function (subcategory) {
            return '<option value="' + subcategory.id + '">' + escapeFilterHtml(subcategory.name) + '</option>';
        }).join('');
        subcategoryFilter.disabled = subcategories.length === 0;
    });

    function escapeFilterHtml(value) {
        const element = document.createElement('div');
        element.textContent = value;
        return element.innerHTML;
    }

    IndexTableBridge.init({
        tableId: 'inventoryTable',
        dataUrl: @json(route('inventory.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        filters: [
            { inputId: 'categoryFilter', param: 'location_id' },
            { inputId: 'subcategoryFilter', param: 'category_id' }
        ],
        sortColumns: ['name', 'branch_name', 'location', 'category', 'quantity', 'unit', 'low_stock_threshold', ''],
        defaultSort: 'name',
        defaultDirection: 'asc',
        emptyMessage: 'No inventory items found.',
        colspan: 9,
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
                    <td class="table-actions-cell text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-success stockin-btn" data-id="${item.id}" data-name="${itemName}" data-unit="${ctx.escapeHtml(item.unit || '')}" data-allows-decimals="${item.allows_decimal_quantity ? '1' : '0'}"><i data-lucide="plus-circle"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-warning deduct-btn" data-id="${item.id}" data-name="${itemName}" data-remaining="${remaining}" data-unit="${ctx.escapeHtml(item.unit || '')}" data-allows-decimals="${item.allows_decimal_quantity ? '1' : '0'}"><i data-lucide="minus-circle"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-info transfer-btn" data-id="${item.id}" data-name="${itemName}" data-remaining="${remaining}" data-branch-name="${branchName}" data-branch-id="${item.branch_id || ''}" title="Transfer to another branch"><i data-lucide="send"></i></button>
                            <a href="{{ url('/inventory') }}/${item.id}/edit" class="btn btn-sm btn-outline-primary"><i data-lucide="edit-2"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger inventory-delete-btn" data-delete-url="{{ url('/inventory') }}/${item.id}" data-token="${csrf}" title="Delete item"><i data-lucide="trash-2"></i></button></td>
                </tr>
            `;
        }
    });

    const inventoryTable = document.getElementById('inventoryTable');

    async function submitInventoryDelete(button) {
        if (!button || button.dataset.submitting === '1') {
            return;
        }

        const message = button.dataset.confirm || 'Delete this item?';
        if (!window.confirm(message)) {
            return;
        }

        button.dataset.submitting = '1';
        button.disabled = true;

        const formData = new FormData();
        formData.append('_token', button.dataset.token || csrf);
        formData.append('_method', 'DELETE');

        try {
            const response = await fetch(button.dataset.deleteUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html, application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Delete request failed.');
            }

            if (typeof window.IndexTableBridgeReload === 'function') {
                window.IndexTableBridgeReload();
            } else {
                window.location.reload();
            }
        } catch (error) {
            console.error(error);
            button.dataset.submitting = '0';
            button.disabled = false;
            window.alert('Unable to delete item. Please try again.');
        }
    }

    inventoryTable.addEventListener('click', function(e) {
        const deleteButton = e.target.closest('.inventory-delete-btn');
        if (deleteButton) {
            e.preventDefault();
            e.stopPropagation();
            submitInventoryDelete(deleteButton);
            return;
        }

        const btn = e.target.closest('button');
        if (!btn) return;
        
        if (btn.classList.contains('stockin-btn')) {
            showStockIn(btn.dataset.id, btn.dataset.name, btn.dataset.unit, btn.dataset.allowsDecimals);
        } else if (btn.classList.contains('deduct-btn')) {
            showDeductStock(btn.dataset.id, btn.dataset.name, btn.dataset.remaining, btn.dataset.unit, btn.dataset.allowsDecimals);
        } else if (btn.classList.contains('transfer-btn')) {
            showTransfer(btn.dataset.id, btn.dataset.name, btn.dataset.remaining, btn.dataset.branchName, btn.dataset.branchId);
        }
    });

});
</script>
@endpush
