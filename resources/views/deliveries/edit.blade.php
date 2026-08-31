@extends('layouts.app')

@section('page_title', 'Edit Delivery - Dianne Seafood House')

@section('content')
    <x-page-header title="Edit Delivery" subtitle="Fix the encoded delivery before it goes to review" icon="truck">
        <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-secondary text-white">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Delivery
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
                        <div class="small text-muted">{{ $delivery->reference_number }} — editable while Pending Review only.</div>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2">Pending Review</span>
                </div>
                <form method="POST" action="{{ route('deliveries.update', $delivery) }}" id="delivery-form">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="source_branch_id" id="hiddenSourceBranchId" value="{{ old('source_branch_id', $delivery->source_branch_id) }}">
                    <input type="hidden" name="source_item_id" id="hiddenSourceItemId" value="">

                    <div class="row g-3">
                        @php
                            $requiresBranchSelection = auth()->user()?->isAdmin() && empty($selectedBranchId);
                        @endphp

                        <div class="{{ $requiresBranchSelection ? 'col-md-4' : 'col-md-8' }}">
                            <label class="form-label fw-semibold">Supplier @if(!$delivery->source_branch_id)<span class="text-danger">*</span>@endif</label>
                            <select name="supplier_id" class="form-select" id="supplierSelect" {{ $delivery->source_branch_id ? 'disabled' : 'required' }}>
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" data-tin="{{ e($s->tin) }}" data-address="{{ e($s->address) }}" {{ old('supplier_id', $delivery->supplier_id) == $s->id ? 'selected' : '' }}>
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
                                   value="{{ old('delivery_date', $delivery->delivery_date?->toDateString()) }}"
                                   required>
                            @error('delivery_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        @if($requiresBranchSelection)
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Delivery Branch <span class="text-danger">*</span></label>
                            <select name="destination_branch_id" class="form-select" id="destinationBranchSelect" required>
                                <option value="">Select Branch</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('destination_branch_id', $delivery->destination_branch_id) == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('destination_branch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        @else
                        <input type="hidden" name="destination_branch_id" id="destinationBranchSelect" value="{{ old('destination_branch_id', $selectedBranchId ?? $delivery->destination_branch_id) }}">
                        @error('destination_branch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        @endif
                    </div>

                    {{-- BIR / Tax Details --}}
                    <div class="mt-4 border-top pt-4">
                        <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-primary">
                            <i data-lucide="receipt" style="width: 20px; height: 20px;"></i>
                            <span>BIR / Tax Details</span>
                        </h5>
                        <div class="small text-muted mb-3">Encode the supplier invoice/receipt tax detail now so this delivery no longer needs a separate APV entry.</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Supplier TIN</label>
                                <input type="text" name="tin" id="tinInput" class="form-control" value="{{ old('tin', $delivery->tin) }}">
                                @error('tin') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Supplier Address</label>
                                <input type="text" name="address" id="addressInput" class="form-control" value="{{ old('address', $delivery->address) }}">
                                @error('address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Invoice / Receipt No.</label>
                                <input type="text" name="si_no" class="form-control" value="{{ old('si_no', $delivery->si_no) }}">
                                @error('si_no') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Amount w/ VAT</label>
                                <input type="number" step="0.01" min="0" name="amount_w_vat" id="amountWVat" class="form-control" value="{{ old('amount_w_vat', $delivery->amount_w_vat) }}">
                                @error('amount_w_vat') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">EWT Rate</label>
                                @php
                                    $ewtRateOld = old('ewt_rate', rtrim(rtrim(number_format((float) $delivery->ewt_rate, 2, '.', ''), '0'), '.') ?: '0');
                                @endphp
                                <select name="ewt_rate" id="ewtRate" class="form-select">
                                    <option value="0" {{ (float) $ewtRateOld === 0.0 ? 'selected' : '' }}>None</option>
                                    <option value="0.01" {{ (float) $ewtRateOld === 0.01 ? 'selected' : '' }}>1%</option>
                                    <option value="0.02" {{ (float) $ewtRateOld === 0.02 ? 'selected' : '' }}>2%</option>
                                    <option value="0.05" {{ (float) $ewtRateOld === 0.05 ? 'selected' : '' }}>5%</option>
                                    <option value="0.10" {{ (float) $ewtRateOld === 0.10 ? 'selected' : '' }}>10%</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">VAT-Exempt Amount</label>
                                <input type="number" step="0.01" min="0" name="vat_exempt" id="vatExempt" class="form-control" value="{{ old('vat_exempt', $delivery->vat_exempt) }}">
                                @error('vat_exempt') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Non-VAT Purchase</label>
                                <input type="number" step="0.01" min="0" name="non_vat_purchase" id="nonVatPurchase" class="form-control" value="{{ old('non_vat_purchase', $delivery->non_vat_purchase) }}">
                                @error('non_vat_purchase') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <div class="small text-muted">Net Purchases</div>
                                <div class="fw-bold" id="summaryNetPurchases">₱0.00</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">VAT (12%)</div>
                                <div class="fw-bold" id="summaryVat">₱0.00</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">EWT Amount</div>
                                <div class="fw-bold" id="summaryEwt">₱0.00</div>
                            </div>
                        </div>
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
                        <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-secondary text-white px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            <span>Save Changes</span>
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

    @php
        $existingDeliveryItems = $delivery->items->map(fn ($i) => [
            'description' => $i->description,
            'quantity' => (float) $i->quantity,
            'unit' => $i->unit,
            'price' => $i->price !== null ? (float) $i->price : null,
            'item_id' => $i->item_id,
            'allocated_to' => $i->allocated_to,
            'item_name' => $i->item?->name,
        ]);
    @endphp
    const UNITS = @json($deliveryUnitOptions);
    const itemsData = @json($itemsForModal);
    const existingItems = @json($existingDeliveryItems);

    const branchSelect = document.getElementById('destinationBranchSelect');
    let selectedBranchId = parseInt(branchSelect ? branchSelect.value : @json($selectedBranchId ?? 0)) || 0;

    function unitOptions(selected) {
        return '<option value="">Select Unit</option>'
            + Object.entries(UNITS).map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`).join('');
    }

    function buildRow(index, prefill) {
        prefill = prefill || {};
        const tr = document.createElement('tr');
        tr.dataset.rowIndex = index;
        tr.innerHTML = `
            <td>
                <input type="text" name="items[${index}][description]"
                       class="form-control" placeholder="e.g. Tuna in brine 185g" value="${esc(prefill.description || '')}" required>
            </td>
            <td>
                <input type="number" name="items[${index}][quantity]"
                       class="form-control" step="0.01" min="0.01" placeholder="0.00" value="${prefill.quantity != null ? prefill.quantity : ''}" required>
            </td>
            <td>
                <select name="items[${index}][unit]" class="form-select row-unit" required>
                    ${unitOptions(prefill.unit || '')}
                </select>
            </td>
            <td>
                <input type="number" name="items[${index}][price]"
                       class="form-control" step="0.01" min="0" placeholder="0.00" value="${prefill.price != null ? prefill.price : ''}">
            </td>
            <td>
                <input type="hidden" name="items[${index}][item_id]" class="row-item-id" value="${prefill.item_id || ''}">
                <input type="hidden" name="items[${index}][allocated_to]" class="row-allocated" value="${prefill.allocated_to || ''}">
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

        if (prefill.allocated_to) {
            const badge = tr.querySelector('.dest-label');
            if (prefill.allocated_to === 'inventory') {
                badge.textContent = `Inventory (${prefill.item_name || ('#' + prefill.item_id)})`;
            } else {
                badge.textContent = 'Production';
            }
            queueMicrotask(() => showDestination(tr, prefill.allocated_to));
        }

        return tr;
    }

    function addRow(prefill) {
        const row = buildRow(rowCount++, prefill);
        tbody.appendChild(row);
        if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    }

    if (existingItems.length) {
        existingItems.forEach(item => addRow(item));
    } else {
        addRow();
    }

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

    addBtn.addEventListener('click', function () { addRow(); });

    // ── BIR / Tax section ───────────────────────────────────────────────────
    const supplierSelectForBir = document.getElementById('supplierSelect');
    const tinInput = document.getElementById('tinInput');
    const addressInput = document.getElementById('addressInput');
    if (supplierSelectForBir && tinInput && addressInput) {
        supplierSelectForBir.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt) return;
            if (!tinInput.value) tinInput.value = opt.dataset.tin || '';
            if (!addressInput.value) addressInput.value = opt.dataset.address || '';
        });
    }

    const amountWVatInput = document.getElementById('amountWVat');
    const ewtRateSelect = document.getElementById('ewtRate');
    const summaryNetPurchases = document.getElementById('summaryNetPurchases');
    const summaryVat = document.getElementById('summaryVat');
    const summaryEwt = document.getElementById('summaryEwt');

    function peso(n) {
        return '₱' + (isNaN(n) ? 0 : n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recomputeBirSummary() {
        const amountWVat = parseFloat(amountWVatInput?.value) || 0;
        const net = Math.round((amountWVat / 1.12) * 100) / 100;
        const vat = Math.round((amountWVat - net) * 100) / 100;
        const ewtRate = parseFloat(ewtRateSelect?.value) || 0;
        const ewt = Math.round(net * ewtRate * 100) / 100;

        if (summaryNetPurchases) summaryNetPurchases.textContent = peso(net);
        if (summaryVat) summaryVat.textContent = peso(vat);
        if (summaryEwt) summaryEwt.textContent = peso(ewt);
    }

    if (amountWVatInput) amountWVatInput.addEventListener('input', recomputeBirSummary);
    if (ewtRateSelect) ewtRateSelect.addEventListener('change', recomputeBirSummary);
    recomputeBirSummary();

    document.getElementById('delivery-form').addEventListener('submit', function (e) {
        selectedBranchId = parseInt(branchSelect ? branchSelect.value : selectedBranchId) || 0;
        if (!selectedBranchId) {
            e.preventDefault();
            alert('Please select a branch before proceeding.');
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

    document.getElementById('chooseProduction').addEventListener('click', function () {
        if (!activeRow) return;
        activeRow.querySelector('input[name$="[item_id]"]').value      = '';
        activeRow.querySelector('input[name$="[allocated_to]"]').value = 'production';
        const badge = activeRow.querySelector('.dest-label');
        badge.textContent = 'Production';
        showDestination(activeRow, 'production');
        destModal.hide();
    });

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
