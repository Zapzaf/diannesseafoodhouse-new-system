@extends('layouts.app')

@section('page_title', 'Log Delivery - Dianne Seafood House')

@section('content')
    <x-page-header title="Log Incoming Delivery" subtitle="Record a delivery from an external supplier" icon="truck">
        <a href="{{ route('deliveries.index') }}" class="btn btn-secondary text-white">
            <i data-lucide="arrow-left" class="me-1"></i> All Deliveries
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-4 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-primary">
                            <i data-lucide="truck" style="width: 20px; height: 20px;"></i>
                            <span>Delivery Details</span>
                        </h5>
                        <div class="small text-muted">Add each delivered item, then choose where it should go.</div>
                    </div>
                    <span class="badge bg-primary px-3 py-2">Destination required per row</span>
                </div>
                <form method="POST" action="{{ route('deliveries.store') }}" id="delivery-form">
                    @csrf

                    {{-- Hidden source branch (used by controller for branch-transfer flow) --}}
                    <input type="hidden" name="source_branch_id" id="hiddenSourceBranchId" value="{{ old('source_branch_id', request('source_branch_id', '')) }}">
                    <input type="hidden" name="source_item_id" id="hiddenSourceItemId" value="{{ old('source_item_id', request('source_item_id', '')) }}">

                    <div class="row g-3">
                        @php($requiresBranchSelection = auth()->user()?->isAdmin() && empty($selectedBranchId))

                        {{-- Supplier --}}
                        <div class="{{ $requiresBranchSelection ? 'col-md-4' : 'col-md-8' }}">
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

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Delivery Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   name="delivery_date"
                                   class="form-control"
                                   value="{{ old('delivery_date', now()->toDateString()) }}"
                                   required>
                            <div class="form-text">Use the actual date the items were received.</div>
                            @error('delivery_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        @if($requiresBranchSelection)
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Delivery Branch <span class="text-danger">*</span></label>
                            <select name="destination_branch_id" class="form-select" id="destinationBranchSelect" required>
                                <option value="">Select Branch</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('destination_branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text">Choose the branch before selecting item destinations.</div>
                            @error('destination_branch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        @else
                        {{-- Non-admin or Admin with an active branch: branch is fixed to selected branch --}}
                        <input type="hidden" name="destination_branch_id" id="destinationBranchSelect" value="{{ old('destination_branch_id', $selectedBranchId ?? '') }}">
                        @error('destination_branch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        @endif
                    </div>

                    {{-- Items Table --}}
                    <div class="mt-4 d-flex justify-content-between align-items-center border-top pt-4">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2 text-primary">
                            <i data-lucide="package" style="width: 20px; height: 20px;"></i>
                            <span>Delivery Items</span>
                        </h5>
                        <button type="button" class="btn btn-sm btn-primary text-white" id="add-row">
                            <i data-lucide="plus"></i> Add Item
                        </button>
                    </div>
                    @error('items') <div class="alert alert-danger py-2 mt-3 mb-0">{{ $message }}</div> @enderror

                    <div class="table-responsive mt-3">
                        <table class="table table-hover align-middle delivery-items-table" id="items-table">
                            <thead>
                                <tr>
                                    <th style="min-width:200px">Item Description</th>
                                    <th style="min-width:90px">Quantity</th>
                                    <th style="min-width:120px">Unit</th>
                                    <th style="min-width:110px">Price</th>
                                    <th style="min-width:170px">Destination</th>
                                    <th class="table-actions-head" style="min-width:80px">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="items-body">
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-3">
                        <a href="{{ route('deliveries.index') }}" class="btn btn-secondary text-white px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            <span>Save Delivery</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Destination Modal --}}
    <div class="modal fade destination-picker-modal" id="destinationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="destinationModalTitle">Select Destination</h5>
                        <div class="small text-muted">Choose where this delivery item should be allocated.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- Step 1: Choose Production or Inventory Storage --}}
                    <div id="stepChoose">
                        <p class="text-muted mb-3">Where should this delivery item go?</p>
                        <div class="destination-choice-grid">
                            <button type="button" id="chooseInventory"
                                    class="btn btn-outline-primary destination-choice-btn">
                                <i data-lucide="archive" style="width:32px;height:32px;"></i>
                                <span class="fw-semibold">Inventory Storage</span>
                                <span class="small text-muted">Add to stock inventory</span>
                            </button>
                            <button type="button" id="chooseProduction"
                                    class="btn btn-outline-secondary destination-choice-btn">
                                <i data-lucide="settings" style="width:32px;height:32px;"></i>
                                <span class="fw-semibold">Production</span>
                                <span class="small text-muted">Send items to production</span>
                            </button>
                        </div>
                    </div>

                    {{-- Step 2: Search & pick inventory item (only shown when Inventory Storage chosen) --}}
                    <div id="stepSearch" class="d-none">
                        <div class="destination-search-header">
                            <button type="button" class="btn btn-sm btn-secondary text-white" id="backToChoose">
                                <i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Back
                            </button>
                            <div>
                                <div class="fw-semibold">Search Inventory Items</div>
                                <div class="small text-muted">Only items from the selected branch are shown.</div>
                            </div>
                        </div>
                        <div class="destination-search-wrap">
                            <i data-lucide="search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by item name or ID...">
                        </div>

                        <div class="table-responsive destination-table-scroll">
                            <table class="table table-sm table-hover mb-0 destination-items-table">
                                <colgroup>
                                    <col class="destination-col-id">
                                    <col class="destination-col-name">
                                    <col class="destination-col-unit">
                                    <col class="destination-col-qty">
                                    <col class="destination-col-location">
                                    <col class="destination-col-category">
                                    <col class="destination-col-action">
                                </colgroup>
                                <thead>
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
    const branchSelect = document.getElementById('destinationBranchSelect');
    let selectedBranchId = parseInt(branchSelect ? branchSelect.value : @json($selectedBranchId ?? 0)) || 0;
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
                    <button type="button" class="btn btn-sm btn-primary text-white btn-dest w-100">
                        <i data-lucide="map-pin"></i> Select Destination
                    </button>
                    <div class="dest-selection d-none">
                        <span class="dest-label"></span>
                        <button type="button" class="btn-clear-dest" aria-label="Clear destination">&times;</button>
                    </div>
                    <div class="destination-error small text-danger mt-1 d-none"></div>
                </div>
            </td>
            <td class="table-actions-cell">
                <button type="button" class="btn btn-sm btn-danger btn-remove text-white" title="Remove"><i data-lucide="trash-2"></i></button>
            </td>
        `;
        return tr;
    }

    function addRow() {
        const row = buildRow(rowCount++);
        tbody.appendChild(row);
        if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    }

    addRow();

    if (branchSelect) {
        branchSelect.addEventListener('change', function () {
            const nextBranchId = parseInt(this.value) || 0;
            if (nextBranchId === selectedBranchId) {
                return;
            }

            selectedBranchId = nextBranchId;
            tbody.querySelectorAll('tr').forEach(clearDestination);
            filterItems('');
        });
    }

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
        selectedBranchId = parseInt(branchSelect ? branchSelect.value : selectedBranchId) || 0;
        if (!selectedBranchId) {
            e.preventDefault();
            alert('Please select a branch before proceeding.');
            if (branchSelect && typeof branchSelect.focus === 'function') {
                branchSelect.focus();
            }
            return;
        }

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
            selectedBranchId = parseInt(branchSelect ? branchSelect.value : selectedBranchId) || 0;
            if (!selectedBranchId) {
                alert('Please select a branch before choosing a destination.');
                if (branchSelect && typeof branchSelect.focus === 'function') {
                    branchSelect.focus();
                }
                return;
            }

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
        if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
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
            if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
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
                    <td><span class="destination-cell-truncate" title="${esc(item.location)}">${esc(item.location)}</span></td>
                    <td><span class="destination-cell-truncate" title="${esc(item.category)}">${esc(item.category)}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary btn-pick-item"
                                    data-item-id="${item.id}" data-item-name="${esc(item.name)}"
                                    data-unit-price="${item.unit_price !== null ? item.unit_price : ''}">
                            <i data-lucide="check"></i>
                            <span>Select</span>
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
.destination-picker-modal .modal-dialog {
    max-width: min(1040px, calc(100vw - 2rem));
}

.destination-picker-modal .modal-content {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    background: var(--bg-card);
    color: var(--text-main);
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
}

.destination-picker-modal .modal-header {
    align-items: flex-start;
    padding: 1.15rem 1.35rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-card);
}

.destination-picker-modal .modal-title {
    color: var(--text-main);
    font-weight: 800;
}

.destination-picker-modal .modal-body {
    padding: 1.25rem 1.35rem 1.35rem;
}

.destination-choice-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.destination-choice-btn {
    min-height: 9rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    border-radius: 10px;
}

.destination-search-header {
    display: flex;
    align-items: center;
    gap: .85rem;
    margin-bottom: 1rem;
}

.destination-search-wrap {
    position: relative;
    margin-bottom: 1rem;
}

.destination-search-wrap svg {
    position: absolute;
    left: .95rem;
    top: 50%;
    width: 1rem;
    height: 1rem;
    color: var(--text-muted);
    transform: translateY(-50%);
    pointer-events: none;
}

.destination-search-wrap .form-control {
    min-height: 2.75rem;
    padding-left: 2.65rem;
    border-radius: 10px;
    background: var(--bg-main);
}

.destination-table-scroll {
    max-height: min(58vh, 560px);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    overflow: auto;
    background: var(--bg-card);
}

.destination-items-table {
    min-width: 850px;
    table-layout: fixed;
}

.destination-items-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    padding: .85rem .8rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-main);
    color: var(--text-muted);
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    white-space: nowrap;
}

.destination-items-table tbody td {
    padding: .85rem .8rem;
    vertical-align: middle;
    border-color: var(--border-color);
    color: var(--text-main);
}

.destination-items-table .destination-col-id { width: 98px; }
.destination-items-table .destination-col-name { width: 230px; }
.destination-items-table .destination-col-unit { width: 82px; }
.destination-items-table .destination-col-qty { width: 112px; }
.destination-items-table .destination-col-location { width: 150px; }
.destination-items-table .destination-col-category { width: 150px; }
.destination-items-table .destination-col-action { width: 120px; }

.destination-cell-truncate {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.destination-items-table .btn-pick-item {
    width: 5.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    padding-inline: .65rem;
    white-space: nowrap;
}

.destination-items-table .btn-pick-item svg {
    width: .9rem;
    height: .9rem;
}

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
    border: 1px solid rgba(var(--primary-color-rgb), .25);
    border-radius: .5rem;
    color: var(--primary-color);
    background: rgba(var(--primary-color-rgb), .08);
    font-size: .85rem;
    font-weight: 600;
}
.dest-selection.destination-production {
    color: var(--bs-warning, #f59e0b);
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

[data-bs-theme="dark"] .destination-picker-modal .modal-content,
[data-bs-theme="dark"] .destination-picker-modal .modal-header,
[data-bs-theme="dark"] .destination-table-scroll {
    background: var(--bg-card);
}

[data-bs-theme="dark"] .destination-items-table thead th,
[data-bs-theme="dark"] .destination-search-wrap .form-control {
    background: rgba(255, 255, 255, .035);
}

@media (max-width: 767.98px) {
    .destination-picker-modal .modal-dialog {
        max-width: calc(100vw - 1rem);
        margin: .5rem auto;
    }

    .destination-picker-modal .modal-header,
    .destination-picker-modal .modal-body {
        padding-inline: 1rem;
    }

    .destination-choice-grid {
        grid-template-columns: 1fr;
    }

    .destination-search-header {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
@endpush
