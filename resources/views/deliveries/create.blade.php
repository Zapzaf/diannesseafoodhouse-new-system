@extends('layouts.app')

@section('page_title', 'Log Delivery - Dianne Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="truck"></i></div>
                            Log Incoming Delivery
                        </h1>
                        <div class="page-header-subtitle">Record a delivery from an external supplier</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a href="{{ route('deliveries.index') }}" class="btn btn-light text-primary">
                            <i data-feather="arrow-left" class="me-1"></i> All Deliveries
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">Delivery Details</div>
                    <div class="small text-muted">Add each delivered item, then choose where it should go.</div>
                </div>
                <span class="badge bg-primary">Destination required per row</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('deliveries.store') }}" id="delivery-form">
                    @csrf

                    {{-- Hidden source branch (used by controller for branch-transfer flow) --}}
                    <input type="hidden" name="source_branch_id" id="hiddenSourceBranchId" value="{{ old('source_branch_id', request('source_branch_id', '')) }}">
                    <input type="hidden" name="source_item_id" id="hiddenSourceItemId" value="{{ old('source_item_id', request('source_item_id', '')) }}">

                    <div class="row g-3">
                        {{-- Supplier --}}
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" id="supplierSelect" required>
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>
                                    {{ $s->name }}@if($s->contact_person) - {{ $s->contact_person }}@endif
                                </option>
                                @endforeach
                            </select>
                            @error('supplier_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        {{-- Non-admin or Admin: branch is fixed to selected branch --}}
                        <input type="hidden" name="destination_branch_id" value="{{ $selectedBranchId ?? '' }}">
                    </div>

                    {{-- Items Table --}}
                    <div class="mt-4 d-flex justify-content-between align-items-center border-top pt-4">
                        <h6 class="mb-0 fw-semibold">Delivery Items</h6>
                        <button type="button" class="btn btn-sm btn-light" id="add-row">
                            <i data-feather="plus" class="me-1"></i> Add Item
                        </button>
                    </div>
                    @error('items') <div class="alert alert-danger py-2 mt-3 mb-0">{{ $message }}</div> @enderror

                    <div class="table-responsive mt-3">
                        <table class="table table-bordered align-middle delivery-items-table" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:200px">Item Description</th>
                                    <th style="min-width:90px">Quantity</th>
                                    <th style="min-width:120px">Unit</th>
                                    <th style="min-width:110px">Price</th>
                                    <th style="min-width:170px">Destination</th>
                                    <th style="min-width:80px">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="items-body">
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end gap-2">
                        <a href="{{ route('deliveries.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="save" class="me-1"></i> Save Delivery
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Destination Modal --}}
    <div class="modal fade" id="destinationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="destinationModalTitle">Select Destination</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- Step 1: Choose Production or Inventory Storage --}}
                    <div id="stepChoose">
                        <p class="text-black mb-3">Where should this delivery item go?</p>
                        <div class="d-flex gap-3">
                            <button type="button" id="chooseInventory"
                                    class="btn btn-outline-primary d-flex flex-column align-items-center flex-grow-1 py-4 gap-2">
                                <i data-feather="archive" style="width:32px;height:32px;"></i>
                                <span class="fw-semibold">Inventory Storage</span>
                                <span class="small text-white">Add to stock inventory</span>
                            </button>
                            <button type="button" id="chooseProduction"
                                    class="btn btn-outline-secondary d-flex flex-column align-items-center flex-grow-1 py-4 gap-2">
                                <i data-feather="settings" style="width:32px;height:32px;"></i>
                                <span class="fw-semibold">Production</span>
                                <span class="small text-white">Send items to production</span>
                            </button>
                        </div>
                    </div>

                    {{-- Step 2: Search & pick inventory item (only shown when Inventory Storage chosen) --}}
                    <div id="stepSearch" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-light" id="backToChoose">
                                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> Back
                            </button>
                            <span class="fw-semibold">Search Inventory Items</span>
                        </div>
                        <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search by Item Name or ID...">

                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item ID</th>
                                        <th>Item Name</th>
                                        <th>Unit</th>
                                        <th>Quantity</th>
                                        <th>Location</th>
                                        <th>Category</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody"></tbody>
                            </table>
                        </div>

                        <div class="text-center text-muted py-3" id="noResultsMsg">
                            Type to search for items...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody   = document.getElementById('items-body');
    const addBtn  = document.getElementById('add-row');
    let rowCount  = 0;
    let activeRow = null;

    const UNITS = @json($deliveryUnitOptions);

    const itemsData         = @json($itemsForModal);
    let selectedBranchId     = parseInt(@json($selectedBranchId ?? 0)) || 0;
    const transferSourceItemId = parseInt(@json(request('source_item_id'))) || 0;
    const transferQty = parseFloat(@json(request('quantity'))) || 0;
    const transferReason = @json(request('reason', ''));
    const transferDestinationItemId = parseInt(@json(request('destination_item_id'))) || 0;

    // Admin: branch dropdown removed, relying on session branch

    // ── Row builder ───────────────────────────────────────────────────────────
    function unitOptions() {
        return '<option value="">Select Unit</option>'
            + Object.entries(UNITS).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
    }

    function buildRow(index) {
        const tr = document.createElement('tr');
        tr.dataset.rowIndex = index;
        tr.innerHTML = `
            <td>
                <input type="text" name="items[${index}][description]"
                       class="form-control" placeholder="e.g. Tuna in brine 185g" required>
            </td>
            <td>
                <input type="number" name="items[${index}][quantity]"
                       class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
            </td>
            <td>
                <select name="items[${index}][unit]" class="form-select row-unit" required>
                    ${unitOptions()}
                </select>
            </td>
            <td>
                <input type="number" name="items[${index}][price]"
                       class="form-control" step="0.01" min="0" placeholder="0.00">
            </td>
            <td>
                <input type="hidden" name="items[${index}][item_id]" class="row-item-id" value="">
                <input type="hidden" name="items[${index}][allocated_to]" class="row-allocated" value="">
                <div class="destination-control">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-dest w-100">
                        <i data-feather="map-pin"></i> Select Destination
                    </button>
                    <div class="dest-selection d-none">
                        <span class="dest-label"></span>
                        <button type="button" class="btn-clear-dest" aria-label="Clear destination">&times;</button>
                    </div>
                    <div class="destination-error small text-danger mt-1 d-none"></div>
                </div>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove">Remove</button>
            </td>
        `;
        return tr;
    }

    function addRow() {
        const row = buildRow(rowCount++);
        tbody.appendChild(row);
        if (typeof feather !== 'undefined') feather.replace();
    }

    addRow();

    // Transfer flow: lock supplier; prefill qty/description; force item selection from modal
    if (transferSourceItemId) {
        const hiddenSourceItem = document.getElementById('hiddenSourceItemId');
        const hiddenSourceBranch = document.getElementById('hiddenSourceBranchId');
        if (hiddenSourceItem) hiddenSourceItem.value = String(transferSourceItemId);
        if (hiddenSourceBranch) hiddenSourceBranch.value = String(selectedBranchId || '');

        const supplierSelect = document.getElementById('supplierSelect');
        if (supplierSelect) {
            supplierSelect.required = false;
            supplierSelect.disabled = true;
        }

        const firstRow = tbody.querySelector('tr');
        if (firstRow) {
            const qtyInput = firstRow.querySelector('input[name$="[quantity]"]');
            const descInput = firstRow.querySelector('input[name$="[description]"]');
            if (qtyInput && transferQty > 0) qtyInput.value = transferQty.toFixed(2);
            if (descInput && transferReason) descInput.value = transferReason;

            if (transferDestinationItemId) {
                const itemIdInput = firstRow.querySelector('input[name$="[item_id]"]');
                const allocInput = firstRow.querySelector('input[name$="[allocated_to]"]');
                const badge = firstRow.querySelector('.dest-label');
                if (itemIdInput) itemIdInput.value = String(transferDestinationItemId);
                if (allocInput) allocInput.value = 'inventory';

                const matched = itemsData.find(i => parseInt(i.id) === transferDestinationItemId);
                const label = matched ? matched.name : ('#' + transferDestinationItemId);
                if (badge) {
                    badge.textContent = `Inventory (${label})`;
                    showDestination(firstRow, 'inventory');
                }

                // If we can, auto-fill price: destination unit_price × qty
                if (matched && matched.unit_price !== null) {
                    const qty = parseFloat(qtyInput?.value || '') || 0;
                    const unitPrice = parseFloat(matched.unit_price);
                    const priceInput = firstRow.querySelector('input[name$="[price]"]');
                    if (priceInput && qty > 0 && !isNaN(unitPrice) && unitPrice > 0) {
                        priceInput.value = (unitPrice * qty).toFixed(2);
                    }
                }
            }
        }
    }
    addBtn.addEventListener('click', addRow);

    document.getElementById('delivery-form').addEventListener('submit', function (e) {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Please add at least one item to the delivery.');
            return;
        }

        const missingDescriptions = [];
        let hasError = false;
        rows.forEach((row, i) => {
            const alloc = row.querySelector('.row-allocated').value;
            const itemId = row.querySelector('.row-item-id').value;
            
            if (!alloc) {
                hasError = true;
                missingDescriptions.push(row.querySelector('input[name$="[description]"]').value.trim() || `Item ${i + 1}`);
                markDestinationError(row, 'Select a destination.');
            } else if (alloc === 'inventory' && !itemId) {
                hasError = true;
                missingDescriptions.push(row.querySelector('input[name$="[description]"]').value.trim() || `Item ${i + 1}`);
                markDestinationError(row, 'Select an inventory item.');
            } else {
                markDestinationError(row, '');
            }
        });

        if (hasError) {
            e.preventDefault();
            alert(`The following items do not have a destination selected: ${missingDescriptions.join(', ')}. Please select a destination before proceeding.`);
        }
    });

    tbody.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.btn-remove');
        if (removeBtn) {
            if (tbody.querySelectorAll('tr').length > 1) {
                removeBtn.closest('tr').remove();
                reindex();
            }
            return;
        }

        const destBtn = e.target.closest('.btn-dest');
        if (destBtn) {
            activeRow = destBtn.closest('tr');
            openModal();
            return;
        }

        const clearBtn = e.target.closest('.btn-clear-dest');
        if (clearBtn) {
            clearDestination(clearBtn.closest('tr'));
        }
    });

    function reindex() {
        Array.from(tbody.querySelectorAll('tr')).forEach((row, i) => {
            row.dataset.rowIndex = i;
            row.querySelector('input[name$="[description]"]').name  = `items[${i}][description]`;
            row.querySelector('input[name$="[quantity]"]').name     = `items[${i}][quantity]`;
            row.querySelector('select[name$="[unit]"]').name        = `items[${i}][unit]`;
            row.querySelector('input[name$="[price]"]').name        = `items[${i}][price]`;
            row.querySelector('input[name$="[item_id]"]').name      = `items[${i}][item_id]`;
            row.querySelector('input[name$="[allocated_to]"]').name = `items[${i}][allocated_to]`;
        });
        rowCount = tbody.querySelectorAll('tr').length;
    }

    // ── Destination modal ─────────────────────────────────────────────────────
    const stepChoose    = document.getElementById('stepChoose');
    const stepSearch    = document.getElementById('stepSearch');
    const searchInput   = document.getElementById('searchInput');
    const itemsTableBody= document.getElementById('itemsTableBody');
    const noResultsMsg  = document.getElementById('noResultsMsg');

    let pendingItemId   = '';
    let pendingItemName = '';

    function showStep(step) {
        stepChoose.classList.toggle('d-none', step !== 'choose');
        stepSearch.classList.toggle('d-none', step !== 'search');
        if (typeof feather !== 'undefined') feather.replace();
    }

    const destModalEl = document.getElementById('destinationModal');
    const destModal = new bootstrap.Modal(destModalEl);

    function openModal() {
        pendingItemId   = '';
        pendingItemName = '';
        searchInput.value = '';
        filterItems('');
        showStep('choose');
        destModal.show();
        destModalEl.addEventListener('shown.bs.modal', () => {
            if (typeof feather !== 'undefined') feather.replace();
        }, { once: true });
    }

    // Step 1 — Production (save immediately, no item needed)
    document.getElementById('chooseProduction').addEventListener('click', function () {
        if (!activeRow) return;
        activeRow.querySelector('input[name$="[item_id]"]').value      = '';
        activeRow.querySelector('input[name$="[allocated_to]"]').value = 'production';
        const badge = activeRow.querySelector('.dest-label');
        badge.textContent = 'Production';
        showDestination(activeRow, 'production');
        destModal.hide();
    });

    // Step 1 — Inventory Storage → go to search
    document.getElementById('chooseInventory').addEventListener('click', function () {
        showStep('search');
        searchInput.focus();
    });

    document.getElementById('backToChoose').addEventListener('click', function () {
        showStep('choose');
    });

    searchInput.addEventListener('input', function () {
        filterItems(this.value);
    });

    function filterItems(query) {
        const q            = (query || '').toLowerCase().trim();
        const destBranchId = selectedBranchId || 0;

        let filtered = itemsData;
        if (q) {
            filtered = filtered.filter(i =>
                i.name.toLowerCase().includes(q) || String(i.id).includes(q)
            );
        }
        if (destBranchId) {
            filtered = filtered.filter(i => i.branch_id === destBranchId);
        }

        itemsTableBody.innerHTML = filtered.length
            ? filtered.map(item => `
                <tr>
                    <td class="text-muted small">#${item.id}</td>
                    <td>${esc(item.name)}</td>
                    <td><span class="badge bg-light text-dark">${esc(item.unit)}</span></td>
                    <td>${item.quantity.toFixed(2)}</td>
                    <td>${esc(item.location)}</td>
                    <td>${esc(item.category)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-pick-item"
                                    data-item-id="${item.id}" data-item-name="${esc(item.name)}"
                                    data-unit-price="${item.unit_price !== null ? item.unit_price : ''}">
                            Select
                        </button>
                    </td>
                </tr>`).join('')
            : `<tr><td colspan="7" class="text-center text-muted py-3">No items found.</td></tr>`;

        noResultsMsg.style.display = (q || destBranchId) ? 'none' : 'block';
    }

    // Step 2 — pick an inventory item and save
    itemsTableBody.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-pick-item');
        if (!btn || !activeRow) return;
        pendingItemId   = btn.dataset.itemId;
        pendingItemName = btn.dataset.itemName;

        const selectedItem = itemsData.find(item => String(item.id) === String(pendingItemId));
        const deliveryUnit = activeRow.querySelector('.row-unit').value;
        if (selectedItem && selectedItem.unit_key !== normalizeUnit(deliveryUnit)) {
            alert(`Unit mismatch: the delivery item uses ${deliveryUnit}, but ${selectedItem.name} uses ${selectedItem.unit}. Select a matching inventory item or update the delivery unit.`);
            return;
        }

        activeRow.querySelector('input[name$="[item_id]"]').value      = pendingItemId;
        activeRow.querySelector('input[name$="[allocated_to]"]').value = 'inventory';

            // Auto-fill price: unit_price × qty entered by user
            const unitPrice = parseFloat(btn.dataset.unitPrice);
            if (!isNaN(unitPrice) && unitPrice > 0) {
                const qtyInput   = activeRow.querySelector('input[name$="[quantity]"]');
                const priceInput = activeRow.querySelector('input[name$="[price]"]');
                const qty        = parseFloat(qtyInput.value) || 1;
                priceInput.value = (unitPrice * qty).toFixed(2);
            }

        const badge = activeRow.querySelector('.dest-label');
        badge.textContent = `Inventory (${pendingItemName})`;
        showDestination(activeRow, 'inventory');

        destModal.hide();
    });

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function normalizeUnit(unit) {
        const aliases = {
            pc: 'pcs', piece: 'pcs', pieces: 'pcs',
            boxes: 'box', packs: 'pack', bottles: 'bottle', cans: 'can',
            rolls: 'roll', sets: 'set', pairs: 'pair', dozens: 'dozen',
            trays: 'tray', tanks: 'tank', bags: 'bag', cups: 'cup',
            liter: 'l', liters: 'l', litre: 'l', litres: 'l',
            gallon: 'gal', gallons: 'gal', lb: 'lbs', pound: 'lbs', pounds: 'lbs'
        };
        const normalized = String(unit || '').trim().toLowerCase();
        return aliases[normalized] || normalized;
    }

    function showDestination(row, type) {
        row.querySelector('.btn-dest').classList.add('d-none');
        row.querySelector('.dest-selection').classList.remove('d-none');
        row.querySelector('.dest-selection').classList.toggle('destination-production', type === 'production');
        markDestinationError(row, '');
    }

    function clearDestination(row) {
        row.querySelector('.row-item-id').value = '';
        row.querySelector('.row-allocated').value = '';
        row.querySelector('.dest-label').textContent = '';
        row.querySelector('.dest-selection').classList.add('d-none');
        row.querySelector('.dest-selection').classList.remove('destination-production');
        row.querySelector('.btn-dest').classList.remove('d-none');
    }

    function markDestinationError(row, message) {
        const error = row.querySelector('.destination-error');
        error.textContent = message;
        error.classList.toggle('d-none', !message);
        row.classList.toggle('table-danger', Boolean(message));
    }
});
</script>
@endpush

@push('styles')
<style>
.delivery-items-table th { white-space: nowrap; }
.delivery-items-table td { padding: .85rem; }
.destination-control { min-width: 220px; }
.destination-control svg { width: 14px; height: 14px; }
.dest-selection {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .45rem .55rem .45rem .75rem;
    border: 1px solid rgba(0, 97, 242, .25);
    border-radius: .5rem;
    color: #0061f2;
    background: rgba(0, 97, 242, .08);
    font-size: .85rem;
    font-weight: 600;
}
.dest-selection.destination-production {
    color: #855400;
    border-color: rgba(245, 158, 11, .35);
    background: rgba(245, 158, 11, .12);
}
.btn-clear-dest {
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: inherit;
    font-size: 1.25rem;
    line-height: 1;
}
</style>
@endpush
