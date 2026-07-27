@extends('layouts.app')

@php
    $order = $menuOrder;
    $discountIds = json_decode($order->discount_id_number ?? '', true) ?: [];
    $discountNames = json_decode($order->discount_name ?? '', true) ?: [];
    $existingPwdIds = $discountIds['pwd'] ?? [];
    $existingPwdNames = $discountNames['pwd'] ?? [];
    $existingSrIds = $discountIds['senior'] ?? [];
    $existingSrNames = $discountNames['senior'] ?? [];
    $existingAdditionalCharges = old('additional_charges', $order->additionalChargesList());
    $finalBranchId = old('branch_id', $order->branch_id);
    $selectedBranch = $branches->firstWhere('id', (int) $finalBranchId) ?? $order->branch;
    $canModifyItems = (string) $order->status === 'open' && $order->payments->isEmpty();
@endphp

@section('page_title', 'Edit Menu Order ' . $order->orderNumber() . ' - Dianne\'s Seafood House')

@section('content')
    <x-page-header :title="'Edit Menu Order ' . $order->orderNumber()" subtitle="Update order details, and add, adjust, or remove menu items." icon="edit">
        <a class="btn btn-primary" href="{{ route('menu-orders.show', $order) }}">
            <i class="me-1" data-lucide="arrow-left"></i> Back to Order
        </a>
    </x-page-header>

    <div class="container-xl px-4 menu-order-workspace menu-order-edit">
        @include('layouts.alerts')

        <form action="{{ route('menu-orders.update', $order) }}" method="POST" id="menuOrderEditForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="branch_id" value="{{ $order->branch_id }}">
            <input type="hidden" name="discount_type" id="discountType" value="{{ old('discount_type', $order->discount_type ?? 'none') }}">

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card mb-4 menu-order-form-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Order Items</span>
                            @if($canModifyItems)
                            <button type="button" class="btn btn-primary btn-sm text-white" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                                <i data-lucide="plus-circle" class="me-1"></i> Add Menu
                            </button>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Menu Item</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Subtotal</th>
                                            @if($canModifyItems)
                                            <th class="text-center">Action</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($order->items as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item->menu->name ?? 'N/A' }}</td>
                                            <td class="text-end">
                                                @if($canModifyItems)
                                                <form action="{{ route('menu-orders.items.update', [$order, $item]) }}" method="POST" class="qty-edit-form d-flex align-items-center justify-content-end gap-1">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="return_to" value="edit">
                                                    <input type="number" name="quantity" class="form-control form-control-sm qty-input text-end" style="width:64px;" value="{{ $item->quantity }}" min="1" max="999" data-original="{{ $item->quantity }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary qty-save-btn d-none" title="Save quantity"><i data-lucide="check" style="width:14px;height:14px;"></i></button>
                                                </form>
                                                @else
                                                <span class="fw-semibold">{{ $item->quantity }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td class="text-end fw-semibold">₱{{ number_format((float) $item->subtotal, 2) }}</td>
                                            @if($canModifyItems)
                                            <td class="text-center">
                                                @if($order->items->count() > 1)
                                                <form action="{{ route('menu-orders.items.destroy', [$order, $item]) }}" method="POST" onsubmit="return confirm('Delete this item and replenish its deducted inventory?')" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="return_to" value="edit">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete item">
                                                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                                    </button>
                                                </form>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            @endif
                                        </tr>
                                        @empty
                                        <tr><td colspan="{{ $canModifyItems ? 5 : 4 }}" class="text-center text-muted py-4">No items on this order.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if(!$canModifyItems)
                            <p class="small text-muted mb-0 mt-2">Items can no longer be changed on this order.</p>
                            @endif
                        </div>
                    </div>

                    <div class="card mb-4 menu-order-form-card">
                        <div class="card-header fw-semibold">Order Details</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Branch</label>
                                    <input type="text" class="form-control" value="{{ $order->branch->name ?? '-' }}" disabled>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Customer Name</label>
                                    <input type="text" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name', $order->customer_name ?? '') }}" maxlength="120" placeholder="Walk-in customer name">
                                    @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Table Assignment</label>
                                    <select name="table_id" class="form-select @error('table_id') is-invalid @enderror">
                                        <option value="">No table selected</option>
                                        @foreach($tables as $tableOption)
                                            <option value="{{ $tableOption->id }}" {{ (string) old('table_id', optional($order->table)->id ?? '') === (string) $tableOption->id ? 'selected' : '' }}>
                                                {{ $tableOption->table_number }} ({{ ucfirst($tableOption->status) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('table_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4 menu-order-form-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Additional Charges</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addAdditionalChargeBtn">
                                <i data-lucide="plus" class="me-1"></i> Add Charge
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="additionalChargesList" class="d-flex flex-column gap-2">
                                @foreach($existingAdditionalCharges as $chargeIndex => $charge)
                                <div class="additional-charge-row border rounded p-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-lg-5">
                                            <label class="form-label fw-semibold small mb-1">Label / Description</label>
                                            <input type="text" name="additional_charges[{{ $chargeIndex }}][label]" class="form-control additional-charge-label @error('additional_charges.' . $chargeIndex . '.label') is-invalid @enderror" value="{{ $charge['label'] ?? '' }}" maxlength="120" required>
                                            @error('additional_charges.' . $chargeIndex . '.label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-lg-3">
                                            <label class="form-label fw-semibold small mb-1">Charge Type</label>
                                            <select name="additional_charges[{{ $chargeIndex }}][type]" class="form-select additional-charge-type @error('additional_charges.' . $chargeIndex . '.type') is-invalid @enderror" required>
                                                <option value="fixed" {{ ($charge['type'] ?? 'fixed') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                                <option value="percentage" {{ ($charge['type'] ?? '') === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                            </select>
                                            @error('additional_charges.' . $chargeIndex . '.type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-lg-3">
                                            <label class="form-label fw-semibold small mb-1">Value</label>
                                            <div class="input-group">
                                                <span class="input-group-text additional-charge-prefix">{{ ($charge['type'] ?? 'fixed') === 'percentage' ? '%' : 'PHP' }}</span>
                                                <input type="number" name="additional_charges[{{ $chargeIndex }}][value]" class="form-control additional-charge-value @error('additional_charges.' . $chargeIndex . '.value') is-invalid @enderror" value="{{ number_format((float) ($charge['value'] ?? 0), 2, '.', '') }}" min="0.01" step="0.01" required>
                                                @error('additional_charges.' . $chargeIndex . '.value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-1 d-grid">
                                            <button type="button" class="btn btn-outline-danger remove-additional-charge" title="Remove charge">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div id="additionalChargeEmptyState" class="small text-muted {{ count($existingAdditionalCharges) ? 'd-none' : '' }}">No additional charges yet.</div>
                        </div>
                    </div>

                    <div class="card mb-4 menu-order-form-card">
                        <div class="card-header fw-semibold">Pax Breakdown</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Regular Pax</label>
                                    <input type="number" min="0" max="999" name="regular_pax" id="regularPax" class="form-control @error('regular_pax') is-invalid @enderror" value="{{ old('regular_pax', $order->regular_pax ?? 1) }}" required>
                                    @error('regular_pax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4 mb-3" id="pwdPaxGroup">
                                    <label class="form-label fw-bold">PWD Pax</label>
                                    <input type="number" min="0" max="999" name="pwd_pax" id="pwdPax" class="form-control @error('pwd_pax') is-invalid @enderror" value="{{ old('pwd_pax', $order->pwd_pax ?? 0) }}" required>
                                    @error('pwd_pax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4 mb-3" id="seniorPaxGroup">
                                    <label class="form-label fw-bold">Senior Pax</label>
                                    <input type="number" min="0" max="999" name="senior_pax" id="seniorPax" class="form-control @error('senior_pax') is-invalid @enderror" value="{{ old('senior_pax', $order->senior_pax ?? 0) }}" required>
                                    @error('senior_pax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div id="discountDetailsSection" class="border rounded p-3 mt-2" style="display:none;">
                                <div id="pwdDiscountRows"></div>
                                <div id="seniorDiscountRows"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4 menu-order-form-card">
                        <div class="card-header fw-semibold">Notes</div>
                        <div class="card-body">
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4">{{ old('notes', $order->notes) }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4 menu-order-form-card menu-order-sticky">
                        <div class="card-header fw-semibold">Billing Preview</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr><td>Menu Subtotal</td><td class="text-end fw-semibold" id="previewSubtotal">₱{{ number_format((float) $order->subtotal, 2) }}</td></tr>
                                        <tr id="previewAdditionalRow"><td>Additional Charges</td><td class="text-end" id="previewAdditional">₱{{ number_format((float) $order->additional_charge_amount, 2) }}</td></tr>
                                        <tr id="previewDiscountRow"><td>Discount</td><td class="text-end text-danger" id="previewDiscount">₱{{ number_format((float) $order->discount_amount, 2) }}</td></tr>
                                        <tr id="previewVatRow"><td>VAT</td><td class="text-end" id="previewVat">₱{{ number_format((float) $order->vat_amount, 2) }}</td></tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold"><td>Total</td><td class="text-end" id="previewTotal">₱{{ number_format((float) $order->total_amount, 2) }}</td></tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary text-white" id="menuOrderSubmitBtn">
                            <i class="me-1" data-lucide="save"></i> Save Changes
                        </button>
                        <a href="{{ route('menu-orders.show', $order) }}" class="btn btn-secondary text-white">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @if($canModifyItems)
        @include('menu-orders.partials.add-menu-modal', [
            'menus' => $menus,
            'formAction' => route('menu-orders.items.store', $order),
            'formId' => 'addMenuItemsForm',
            'fieldsContainerId' => 'selectedMenuItemsFields',
            'returnTo' => 'edit',
        ])
    @endif
@endsection

@push('scripts')
@if($canModifyItems)
<script>
(function () {
    var modalSearch = document.getElementById('modalSearch');
    var modalGrid = document.getElementById('modalMenuGrid');
    var modalError = document.getElementById('modalError');
    var addMenuModal = document.getElementById('addMenuModal');
    var addSelectedItemsBtn = document.getElementById('addSelectedItemsBtn');
    var form = document.getElementById('addMenuItemsForm');
    var fields = document.getElementById('selectedMenuItemsFields');
    var branchId = {{ (int) $order->branch_id }};

    function updateCardStyle(card, qty) {
        if (!card) return;
        if (qty > 0) {
            card.classList.add('border-primary');
            card.classList.remove('border-0');
            card.style.boxShadow = '0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.25)';
        } else {
            card.classList.remove('border-primary');
            card.classList.add('border-0');
            card.style.boxShadow = '';
        }
    }

    function filterModal() {
        if (!modalGrid) return;
        var term = modalSearch ? modalSearch.value.toLowerCase() : '';
        modalGrid.querySelectorAll('.menu-modal-card').forEach(function (card) {
            var name = (card.dataset.menuName || '').toLowerCase();
            var cardBranch = Number(card.dataset.menuBranch || 0);
            var visible = cardBranch === branchId && name.indexOf(term) !== -1;
            card.parentElement.style.display = visible ? '' : 'none';
        });
    }

    if (modalSearch) {
        modalSearch.addEventListener('input', filterModal);
    }

    if (addMenuModal) {
        addMenuModal.addEventListener('show.bs.modal', function () {
            if (modalError) modalError.classList.add('d-none');
            if (modalSearch) modalSearch.value = '';
            if (fields) fields.innerHTML = '';
            if (!modalGrid) return;
            modalGrid.querySelectorAll('.modal-item-qty').forEach(function (input) {
                input.value = '0';
                updateCardStyle(input.closest('.menu-modal-card'), 0);
            });
            filterModal();
        });
    }

    if (modalGrid) {
        modalGrid.addEventListener('click', function (e) {
            var btnPlus = e.target.closest('.btn-qty-plus');
            var btnMinus = e.target.closest('.btn-qty-minus');
            var imgWrapper = e.target.closest('.menu-card-img-wrapper');

            if (btnPlus || imgWrapper) {
                var card = e.target.closest('.menu-modal-card');
                var input = card.querySelector('.modal-item-qty');
                var val = parseInt(input.value, 10) || 0;
                if (val < 999) {
                    input.value = val + 1;
                    updateCardStyle(card, val + 1);
                }
            } else if (btnMinus) {
                var card = e.target.closest('.menu-modal-card');
                var input = card.querySelector('.modal-item-qty');
                var val = parseInt(input.value, 10) || 0;
                if (val > 0) {
                    input.value = val - 1;
                    updateCardStyle(card, val - 1);
                }
            }
        });

        modalGrid.addEventListener('input', function (e) {
            if (!e.target.classList.contains('modal-item-qty')) return;
            var val = parseInt(e.target.value, 10) || 0;
            if (val < 0) val = 0;
            if (val > 999) val = 999;
            e.target.value = val;
            updateCardStyle(e.target.closest('.menu-modal-card'), val);
        });
    }

    if (addSelectedItemsBtn) {
        addSelectedItemsBtn.addEventListener('click', function (event) {
            if (!modalGrid || !form || !fields || !modalError) return;
            fields.innerHTML = '';
            var index = 0;

            modalGrid.querySelectorAll('.menu-modal-card').forEach(function (card) {
                var qty = parseInt(card.querySelector('.modal-item-qty').value, 10) || 0;
                if (qty < 1) return;
                fields.insertAdjacentHTML('beforeend',
                    '<input type="hidden" name="items[' + index + '][menu_id]" value="' + card.dataset.menuId + '">' +
                    '<input type="hidden" name="items[' + index + '][quantity]" value="' + qty + '">'
                );
                index++;
            });

            if (index === 0) {
                event.preventDefault();
                modalError.classList.remove('d-none');
            }
        });
    }

    document.querySelectorAll('.qty-edit-form').forEach(function (qtyForm) {
        var input = qtyForm.querySelector('.qty-input');
        var saveBtn = qtyForm.querySelector('.qty-save-btn');
        if (!input || !saveBtn) return;

        input.addEventListener('input', function () {
            var original = parseInt(input.dataset.original, 10);
            var current = parseInt(input.value, 10);
            saveBtn.classList.toggle('d-none', !current || current === original);
        });
    });
})();
</script>
@endif
<script>
(function () {
    var branchRules = {
        vatEnabled: {{ (int) ($selectedBranch->vat_enabled ?? 1) }},
        vatPercentage: {{ number_format((float) ($selectedBranch->vat_percentage ?? 12), 2, '.', '') }},
        pwdEnabled: {{ (int) ($selectedBranch->pwd_discount_enabled ?? 1) }},
        seniorEnabled: {{ (int) ($selectedBranch->senior_discount_enabled ?? 1) }}
    };
    var subtotal = {{ number_format((float) $order->items->sum('subtotal'), 2, '.', '') }};
    var additionalChargesList = document.getElementById('additionalChargesList');
    var addAdditionalChargeBtn = document.getElementById('addAdditionalChargeBtn');
    var additionalChargeEmptyState = document.getElementById('additionalChargeEmptyState');
    var additionalChargeRowIndex = additionalChargesList ? additionalChargesList.querySelectorAll('.additional-charge-row').length : 0;
    var regularPaxInput = document.getElementById('regularPax');
    var pwdPaxInput = document.getElementById('pwdPax');
    var seniorPaxInput = document.getElementById('seniorPax');
    var discountTypeInput = document.getElementById('discountType');
    var existingPwdIds = @json($existingPwdIds);
    var existingPwdNames = @json($existingPwdNames);
    var existingSrIds = @json($existingSrIds);
    var existingSrNames = @json($existingSrNames);

    function formatMoney(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateAdditionalChargeEmptyState() {
        additionalChargeEmptyState.classList.toggle('d-none', additionalChargesList.querySelectorAll('.additional-charge-row').length > 0);
    }

    function bindAdditionalChargeRow(row) {
        var typeSelect = row.querySelector('.additional-charge-type');
        var prefix = row.querySelector('.additional-charge-prefix');
        var removeBtn = row.querySelector('.remove-additional-charge');
        var inputs = row.querySelectorAll('input, select');

        function syncPrefix() {
            prefix.textContent = typeSelect.value === 'percentage' ? '%' : '₱';
            recalcPreview();
        }

        typeSelect.addEventListener('change', syncPrefix);
        inputs.forEach(function (input) { input.addEventListener('input', recalcPreview); });
        removeBtn.addEventListener('click', function () {
            row.remove();
            updateAdditionalChargeEmptyState();
            recalcPreview();
        });
        syncPrefix();
    }

    function createAdditionalChargeRow() {
        var index = additionalChargeRowIndex++;
        var row = document.createElement('div');
        row.className = 'additional-charge-row border rounded p-3';
        row.innerHTML =
            '<div class="row g-2 align-items-end">' +
            '<div class="col-lg-5"><label class="form-label fw-semibold small mb-1">Label / Description</label><input type="text" name="additional_charges[' + index + '][label]" class="form-control additional-charge-label" maxlength="120" required></div>' +
            '<div class="col-lg-3"><label class="form-label fw-semibold small mb-1">Charge Type</label><select name="additional_charges[' + index + '][type]" class="form-select additional-charge-type" required><option value="fixed">Fixed Amount</option><option value="percentage">Percentage</option></select></div>' +
            '<div class="col-lg-3"><label class="form-label fw-semibold small mb-1">Value</label><div class="input-group"><span class="input-group-text additional-charge-prefix">₱</span><input type="number" name="additional_charges[' + index + '][value]" class="form-control additional-charge-value" min="0.01" step="0.01" required></div></div>' +
            '<div class="col-lg-1 d-grid"><button type="button" class="btn btn-outline-danger remove-additional-charge" title="Remove charge"><i data-lucide="trash-2"></i></button></div>' +
            '</div>';
        additionalChargesList.appendChild(row);
        bindAdditionalChargeRow(row);
        updateAdditionalChargeEmptyState();
        if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    }

    function collectAdditionalCharges() {
        var total = 0;
        additionalChargesList.querySelectorAll('.additional-charge-row').forEach(function (row) {
            var label = row.querySelector('.additional-charge-label').value.trim();
            var type = row.querySelector('.additional-charge-type').value;
            var value = Number(row.querySelector('.additional-charge-value').value || 0);
            if (!label || value <= 0) return;
            total += type === 'percentage' ? subtotal * (value / 100) : value;
        });
        return Math.round(total * 100) / 100;
    }

    function computeDiscountType(pwd, senior) {
        if (pwd > 0 && senior > 0) return 'mixed';
        if (pwd > 0) return 'pwd';
        if (senior > 0) return 'senior';
        return 'none';
    }

    function buildDiscountRows() {
        var pwd = branchRules.pwdEnabled ? Math.max(0, Number(pwdPaxInput.value || 0)) : 0;
        var senior = branchRules.seniorEnabled ? Math.max(0, Number(seniorPaxInput.value || 0)) : 0;
        var section = document.getElementById('discountDetailsSection');
        var pwdRows = document.getElementById('pwdDiscountRows');
        var seniorRows = document.getElementById('seniorDiscountRows');

        pwdPaxInput.disabled = !branchRules.pwdEnabled;
        seniorPaxInput.disabled = !branchRules.seniorEnabled;
        document.getElementById('pwdPaxGroup').style.display = branchRules.pwdEnabled ? '' : 'none';
        document.getElementById('seniorPaxGroup').style.display = branchRules.seniorEnabled ? '' : 'none';
        discountTypeInput.value = computeDiscountType(pwd, senior);

        pwdRows.innerHTML = '';
        for (var i = 0; i < pwd; i++) {
            pwdRows.innerHTML += '<div class="row mb-2 align-items-center g-2"><div class="col-auto"><span class="badge bg-primary">PWD #' + (i + 1) + '</span></div><div class="col"><input type="text" name="pwd_ids[]" class="form-control form-control-sm" placeholder="PWD ID Number" value="' + (existingPwdIds[i] || '') + '"></div><div class="col"><input type="text" name="pwd_names[]" class="form-control form-control-sm" placeholder="Name on PWD ID" value="' + (existingPwdNames[i] || '') + '"></div></div>';
        }

        seniorRows.innerHTML = '';
        for (var j = 0; j < senior; j++) {
            seniorRows.innerHTML += '<div class="row mb-2 align-items-center g-2"><div class="col-auto"><span class="badge bg-success">Senior #' + (j + 1) + '</span></div><div class="col"><input type="text" name="senior_ids[]" class="form-control form-control-sm" placeholder="Senior Citizen ID" value="' + (existingSrIds[j] || '') + '"></div><div class="col"><input type="text" name="senior_names[]" class="form-control form-control-sm" placeholder="Name on Senior ID" value="' + (existingSrNames[j] || '') + '"></div></div>';
        }

        section.style.display = (pwd + senior) > 0 ? '' : 'none';
    }

    function recalcPreview() {
        var additional = collectAdditionalCharges();
        var regular = Math.max(0, Number(regularPaxInput.value || 0));
        var pwd = branchRules.pwdEnabled ? Math.max(0, Number(pwdPaxInput.value || 0)) : 0;
        var senior = branchRules.seniorEnabled ? Math.max(0, Number(seniorPaxInput.value || 0)) : 0;
        var totalPax = Math.max(1, regular + pwd + senior);
        var discountedPax = pwd + senior;
        var gross = subtotal + additional;
        var perPaxGross = gross / totalPax;
        var discountBase = perPaxGross * discountedPax;
        var discount = Math.round(discountBase * 0.20 * 100) / 100;
        var vat = branchRules.vatEnabled ? Math.round(Math.max(0, gross - discountBase) * (branchRules.vatPercentage / (100 + branchRules.vatPercentage)) * 100) / 100 : 0;
        var total = Math.max(0, gross - discount);

        document.getElementById('previewSubtotal').textContent = formatMoney(subtotal);
        document.getElementById('previewAdditional').textContent = formatMoney(additional);
        document.getElementById('previewDiscount').textContent = formatMoney(discount);
        document.getElementById('previewVat').textContent = formatMoney(vat);
        document.getElementById('previewTotal').textContent = formatMoney(total);
        document.getElementById('previewAdditionalRow').style.display = additional > 0 ? '' : 'none';
        document.getElementById('previewDiscountRow').style.display = discountedPax > 0 ? '' : 'none';
        document.getElementById('previewVatRow').style.display = branchRules.vatEnabled ? '' : 'none';
    }

    additionalChargesList.querySelectorAll('.additional-charge-row').forEach(bindAdditionalChargeRow);
    addAdditionalChargeBtn.addEventListener('click', function () {
        createAdditionalChargeRow();
        recalcPreview();
    });
    [regularPaxInput, pwdPaxInput, seniorPaxInput].forEach(function (input) {
        input.addEventListener('input', function () {
            buildDiscountRows();
            recalcPreview();
        });
    });

    buildDiscountRows();
    updateAdditionalChargeEmptyState();
    recalcPreview();
})();
</script>
@endpush
