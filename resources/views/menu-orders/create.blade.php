@extends('layouts.app')

@php
    $isEdit = (bool) ($isEdit ?? false);
    $order = $menuOrder ?? null;
    $menuOptionsJson = $menus->map(fn($m) => [
        'id'        => $m->id,
        'name'      => $m->name,
        'price'     => number_format((float) $m->selling_price, 2, '.', ''),
        'branch_id' => (int) $m->branch_id,
        'image'     => $m->image ? asset('storage/' . $m->image) : '',
    ])->values()->toArray();
    $existingPwdIds    = $isEdit ? (json_decode($order->discount_id_number ?? '', true)['pwd']    ?? []) : [];
    $existingPwdNames  = $isEdit ? (json_decode($order->discount_name      ?? '', true)['pwd']    ?? []) : [];
    $existingSrIds     = $isEdit ? (json_decode($order->discount_id_number ?? '', true)['senior'] ?? []) : [];
    $existingSrNames   = $isEdit ? (json_decode($order->discount_name      ?? '', true)['senior'] ?? []) : [];
@endphp

@section('page_title')
    {{ $isEdit ? 'Edit Menu Order' : 'New Menu Order' }} - Dianne's Seafood House
@endsection

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="shopping-bag"></i></div>
                            {{ $isEdit ? 'Edit Menu Order' : 'New Menu Order' }}
                        </h1>
                        <div class="page-header-subtitle">{{ $isEdit ? 'Update this menu order' : 'Create a food order' }}</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a class="btn btn-light text-primary" href="{{ route('menu-orders.index') }}">
                            <i class="me-1" data-feather="arrow-left"></i> Back to Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-feather="edit-3"></i> Order Details</div>
            <div class="card-body">
                <form action="{{ $isEdit ? route('menu-orders.update', $order) : route('menu-orders.store') }}" method="POST" id="menuOrderForm">
                    @csrf
                    @if($isEdit)
                    @method('PUT')
                    @endif

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" id="branchId" class="form-select @error('branch_id') is-invalid @enderror" required {{ auth()->user()->isAdmin() ? '' : 'disabled' }}>
                                @foreach($branches as $branch)
                                <option
                                    value="{{ $branch->id }}"
                                    data-vat-enabled="{{ (int) $branch->vat_enabled }}"
                                    data-vat-percentage="{{ number_format((float) ($branch->vat_percentage ?? 12), 2, '.', '') }}"
                                    data-pwd-enabled="{{ (int) ($branch->pwd_discount_enabled ?? 1) }}"
                                    data-senior-enabled="{{ (int) ($branch->senior_discount_enabled ?? 1) }}"
                                    {{ (string) old('branch_id', $order->branch_id ?? $selectedBranchId) === (string) $branch->id ? 'selected' : '' }}
                                >
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                            @if(!auth()->user()->isAdmin())
                            <input type="hidden" name="branch_id" value="{{ old('branch_id', $order->branch_id ?? $selectedBranchId) }}">
                            @endif
                            @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Customer Name</label>
                            <input type="text" name="customer_name" id="customerName" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name', $order->customer_name ?? '') }}" maxlength="120" placeholder="Walk-in customer name">
                            @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Table Assignment</label>
                            <select name="table_id" id="tableId" class="form-select @error('table_id') is-invalid @enderror">
                                <option value="">No table selected</option>
                                @foreach($tables as $tableOption)
                                    <option
                                        value="{{ $tableOption->id }}"
                                        data-branch-id="{{ $tableOption->branch_id }}"
                                        {{ (string) old('table_id', optional($order->table)->id ?? '') === (string) $tableOption->id ? 'selected' : '' }}
                                    >
                                        {{ $tableOption->branch->name }} — {{ $tableOption->table_number }} ({{ ucfirst($tableOption->status) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('table_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Only available tables can be assigned to a new order.</div>
                        </div>
                    </div>

                    {{-- ===================== MENU ITEMS ===================== --}}
                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Menu Items</legend>
                        <div class="mb-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                                <i data-feather="plus-circle" class="me-1"></i> Add Menu
                            </button>
                        </div>

                        {{-- Order item list (table) --}}
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="orderItemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:60px;">Image</th>
                                        <th>Item</th>
                                        <th style="width:80px;">Qty</th>
                                        <th style="width:110px;">Price</th>
                                        <th style="width:120px;">Total</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="orderItemsBody">
                                    @php
                                        $orderItems = isset($order) ? $order->items->map(fn($item) => ['menu_id' => $item->menu_id, 'quantity' => $item->quantity])->toArray() : [];
                                        $oldItems = old('items', $orderItems);
                                    @endphp
                                    @if(count($oldItems) === 0)
                                    @php $oldItems = [['menu_id' => '', 'quantity' => 1]]; @endphp
                                    @endif
                                    @foreach($oldItems as $index => $oldItem)
                                    @php $selMenu = $menus->firstWhere('id', (int) ($oldItem['menu_id'] ?? 0)); @endphp
                                    <tr class="item-row" data-index="{{ $index }}" data-menu-id="{{ $selMenu->id ?? '' }}" data-price="{{ $selMenu ? number_format((float) $selMenu->selling_price, 2, '.', '') : '0' }}">
                                        <td class="text-center">
                                            <div style="width:50px;height:50px;margin:0 auto;display:flex;align-items:center;justify-content:center;">
                                                @if($selMenu && $selMenu->image)
                                                <img src="{{ asset('storage/' . $selMenu->image) }}" style="max-width:50px;max-height:50px;border-radius:4px;">
                                                @else
                                                <span class="text-muted" style="font-size:10px;">No img</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="item-menu-name">
                                            @if($selMenu)
                                            {{ $selMenu->name }}
                                            <input type="hidden" name="items[{{ $index }}][menu_id]" value="{{ $selMenu->id }}" data-price="{{ number_format((float) $selMenu->selling_price, 2, '.', '') }}">
                                            @else
                                            <span class="text-muted">&mdash;</span>
                                            <input type="hidden" name="items[{{ $index }}][menu_id]" value="" data-price="0">
                                            @endif
                                        </td>
                                        <td>
                                            <input type="number" name="items[{{ $index }}][quantity]" class="form-control form-control-sm item-qty"
                                                   min="1" max="999" value="{{ (int) ($oldItem['quantity'] ?? 1) }}" required>
                                        </td>
                                        <td class="text-end item-unit-price">&#x20B1;0.00</td>
                                        <td class="text-end fw-semibold item-line-total">&#x20B1;0.00</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-item-row">&times;</button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Additional Charge Label</label>
                            <input type="text" name="additional_charge_label" class="form-control @error('additional_charge_label') is-invalid @enderror" value="{{ old('additional_charge_label', $order->additional_charge_label ?? '') }}" maxlength="120" placeholder="e.g. Delivery Fee">
                            @error('additional_charge_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Additional Charge Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">PHP</span>
                                <input type="number" name="additional_charge_amount" id="additionalChargeAmount" class="form-control @error('additional_charge_amount') is-invalid @enderror" value="{{ old('additional_charge_amount', number_format((float) ($order->additional_charge_amount ?? 0), 2, '.', '')) }}" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="discount_type" id="discountType" value="{{ old('discount_type', $order->discount_type ?? 'none') }}">

                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Pax Breakdown (Discount Per Person)</legend>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Regular Pax</label>
                                <input type="number" min="0" max="999" name="regular_pax" id="regularPax" class="form-control @error('regular_pax') is-invalid @enderror" value="{{ old('regular_pax', $order->regular_pax ?? 1) }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">PWD Pax</label>
                                <input type="number" min="0" max="999" name="pwd_pax" id="pwdPax" class="form-control @error('pwd_pax') is-invalid @enderror" value="{{ old('pwd_pax', $order->pwd_pax ?? 0) }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Senior Pax</label>
                                <input type="number" min="0" max="999" name="senior_pax" id="seniorPax" class="form-control @error('senior_pax') is-invalid @enderror" value="{{ old('senior_pax', $order->senior_pax ?? 0) }}" required>
                            </div>
                        </div>
                    </fieldset>

                    <div id="discountDetailsSection" style="display:none;">
                        <fieldset class="border rounded p-3 mb-3">
                            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Discount ID Numbers &amp; Names</legend>
                            <div class="text-muted small mb-3">Enter the government-issued ID number and full name for each discounted customer.</div>
                            <div id="pwdDiscountRows"></div>
                            <div id="seniorDiscountRows"></div>
                        </fieldset>
                    </div>

                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Billing Preview</legend>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td>Menu Subtotal</td><td class="text-end" id="previewSubtotal">&#x20B1;0.00</td></tr>
                                <tr><td>Additional Charges</td><td class="text-end" id="previewAdditional">&#x20B1;0.00</td></tr>
                                <tr><td>Discount</td><td class="text-end text-danger" id="previewDiscount">&#x20B1;0.00</td></tr>
                                <tr><td>VAT (included)</td><td class="text-end" id="previewVat">&#x20B1;0.00</td></tr>
                                <tr class="fw-bold"><td>Total</td><td class="text-end" id="previewTotal">&#x20B1;0.00</td></tr>
                            </tbody>
                        </table>
                    </fieldset>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $order->notes ?? '') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('menu-orders.index') }}" class="btn btn-secondary text-light">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="me-1" data-feather="save"></i> {{ $isEdit ? 'Update Menu Order' : 'Save Menu Order' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

{{-- ===================== ADD MENU MODAL ===================== --}}
<div class="modal fade" id="addMenuModal" tabindex="-1" aria-labelledby="addMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMenuModalLabel"><i data-feather="plus-circle" class="me-1"></i> Add Menu Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="modalSearch" class="form-control" placeholder="Search menu items by name...">
                    </div>
                </div>
                <div class="row" id="modalMenuGrid" style="max-height:450px;overflow-y:auto;">
                    @foreach($menus as $menu)
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="card menu-modal-card h-100 border" data-menu-id="{{ $menu->id }}"
                             data-menu-name="{{ $menu->name }}"
                             data-menu-price="{{ number_format((float) $menu->selling_price, 2, '.', '') }}"
                             data-menu-branch="{{ $menu->branch_id }}"
                             data-menu-image="{{ $menu->image ? asset('storage/' . $menu->image) : '' }}">
                            <div class="card-body p-2">
                                <div class="text-center mb-2" style="height:100px;display:flex;align-items:center;justify-content:center;background:#f8f9fa;border-radius:6px;">
                                    @if($menu->image)
                                    <img src="{{ asset('storage/' . $menu->image) }}" style="max-height:100px;max-width:100%;object-fit:cover;border-radius:4px;">
                                    @else
                                    <span class="text-muted small">No Image</span>
                                    @endif
                                </div>
                                <div class="fw-semibold small text-truncate">{{ $menu->name }}</div>
                                <div class="text-primary fw-bold mb-2">&#x20B1;{{ number_format((float) $menu->selling_price, 2) }}</div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Qty</span>
                                    <input type="number" class="form-control modal-item-qty" value="0" min="0" max="999" style="width:60px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div id="modalError" class="alert alert-danger d-none mt-3 mb-0">
                    Please enter a quantity of at least 1 for the selected items.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary text-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="addSelectedItemsBtn">
                    <i data-feather="check-circle" class="me-1"></i> Add to Order
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var branchSelect = document.getElementById('branchId');
    var orderItemsBody = document.getElementById('orderItemsBody');
    var additionalChargeInput = document.getElementById('additionalChargeAmount');
    var discountTypeInput = document.getElementById('discountType');
    var regularPaxInput = document.getElementById('regularPax');
    var pwdPaxInput = document.getElementById('pwdPax');
    var seniorPaxInput = document.getElementById('seniorPax');
    var tableSelect = document.getElementById('tableId');

    var subtotalEl = document.getElementById('previewSubtotal');
    var additionalEl = document.getElementById('previewAdditional');
    var discountEl = document.getElementById('previewDiscount');
    var vatEl = document.getElementById('previewVat');
    var totalEl = document.getElementById('previewTotal');

    var masterMenuOptions = @json($menuOptionsJson);
    var existingPwdIds   = @json($existingPwdIds);
    var existingPwdNames = @json($existingPwdNames);
    var existingSrIds    = @json($existingSrIds);
    var existingSrNames  = @json($existingSrNames);

    function formatMoney(value) {
        return '\u20B1' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function selectedBranchId() {
        return Number(branchSelect.value || 0);
    }

    function branchVatEnabled() {
        var opt = branchSelect.options[branchSelect.selectedIndex];
        return opt && Number(opt.dataset.vatEnabled) === 1;
    }

    function branchVatPercentage() {
        var opt = branchSelect.options[branchSelect.selectedIndex];
        return opt ? Number(opt.dataset.vatPercentage || 12) : 12;
    }

    function branchPwdEnabled() {
        var opt = branchSelect.options[branchSelect.selectedIndex];
        return opt && Number(opt.dataset.pwdEnabled) === 1;
    }

    function branchSeniorEnabled() {
        var opt = branchSelect.options[branchSelect.selectedIndex];
        return opt && Number(opt.dataset.seniorEnabled) === 1;
    }

    function getRowData(row) {
        var qtyInput = row.querySelector('.item-qty');
        var menuId = row.getAttribute('data-menu-id') || '';
        var price = Number(row.getAttribute('data-price') || 0);
        var qty = Number(qtyInput ? qtyInput.value : 0);
        return { menuId: menuId, price: price, qty: qty };
    }

    function recalcRow(row) {
        var data = getRowData(row);
        var total = data.price * data.qty;
        var priceCell = row.querySelector('.item-unit-price');
        var totalCell = row.querySelector('.item-line-total');
        if (priceCell) priceCell.textContent = formatMoney(data.price);
        if (totalCell) totalCell.textContent = formatMoney(total);
        return total;
    }

    function recalcPreview() {
        var subtotal = 0;
        orderItemsBody.querySelectorAll('.item-row').forEach(function(row) {
            subtotal += recalcRow(row);
        });
        var additional = Number(additionalChargeInput.value || 0);
        var gross = subtotal + additional;
        var regularPax = Number(regularPaxInput.value || 0);
        var pwdPax = Number(pwdPaxInput.value || 0);
        var seniorPax = Number(seniorPaxInput.value || 0);
        var totalPax = Math.max(1, regularPax + pwdPax + seniorPax);
        var discountedPax = branchVatEnabled() ? (pwdPax + seniorPax) : 0;
        var perPaxGross = gross / totalPax;
        var vatExempt = perPaxGross * discountedPax;
        var discount = vatExempt * 0.20;
        var total = Math.max(0, gross - discount);
        var vatPercent = branchVatPercentage();
        var vatBase = Math.max(0, gross - vatExempt);
        var vat = branchVatEnabled() && vatPercent > 0 ? vatBase * (vatPercent / (100 + vatPercent)) : 0;

        subtotalEl.textContent = formatMoney(subtotal);
        additionalEl.textContent = formatMoney(additional);
        discountEl.textContent = formatMoney(discount);
        vatEl.textContent = formatMoney(vat);
        totalEl.textContent = formatMoney(total);
    }

    function computeDiscountType() {
        var pwd = Number(pwdPaxInput.value || 0);
        var senior = Number(seniorPaxInput.value || 0);
        if (pwd > 0 && senior > 0) return 'mixed';
        if (pwd > 0) return 'pwd';
        if (senior > 0) return 'senior';
        return 'none';
    }

    function syncDiscountControls() {
        var vatOn = branchVatEnabled();
        pwdPaxInput.disabled = !vatOn || !branchPwdEnabled();
        seniorPaxInput.disabled = !vatOn || !branchSeniorEnabled();
        if (!vatOn || !branchPwdEnabled()) pwdPaxInput.value = '0';
        if (!vatOn || !branchSeniorEnabled()) seniorPaxInput.value = '0';
        var dt = vatOn ? computeDiscountType() : 'none';
        discountTypeInput.value = dt;
    }

    function filterTableOptions() {
        if (!tableSelect) {
            return;
        }

        var branchId = selectedBranchId();
        tableSelect.querySelectorAll('option').forEach(function(option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            var optionBranchId = Number(option.dataset.branchId || 0);
            option.hidden = branchId ? optionBranchId !== branchId : false;
        });

        if (tableSelect.options[tableSelect.selectedIndex] && tableSelect.options[tableSelect.selectedIndex].hidden) {
            tableSelect.value = '';
        }
    }

    function buildDiscountRows() {
        var vatOn = branchVatEnabled();
        var pwdCount  = vatOn && branchPwdEnabled()    ? Math.max(0, Number(pwdPaxInput.value || 0))    : 0;
        var seniorCount = vatOn && branchSeniorEnabled() ? Math.max(0, Number(seniorPaxInput.value || 0)) : 0;
        var section = document.getElementById('discountDetailsSection');
        var pwdContainer    = document.getElementById('pwdDiscountRows');
        var seniorContainer = document.getElementById('seniorDiscountRows');
        pwdContainer.innerHTML = '';
        for (var i = 0; i < pwdCount; i++) {
            var d = document.createElement('div');
            d.className = 'row mb-2 align-items-center g-2';
            d.innerHTML =
                '<div class="col-auto"><span class="badge bg-primary">PWD #' + (i + 1) + '</span></div>' +
                '<div class="col"><input type="text" name="pwd_ids[]" class="form-control form-control-sm" placeholder="PWD ID Number" value="' + (existingPwdIds[i] || '') + '"></div>' +
                '<div class="col"><input type="text" name="pwd_names[]" class="form-control form-control-sm" placeholder="Name on PWD ID" value="' + (existingPwdNames[i] || '') + '"></div>';
            pwdContainer.appendChild(d);
        }
        seniorContainer.innerHTML = '';
        for (var j = 0; j < seniorCount; j++) {
            var d2 = document.createElement('div');
            d2.className = 'row mb-2 align-items-center g-2';
            d2.innerHTML =
                '<div class="col-auto"><span class="badge bg-success">Senior #' + (j + 1) + '</span></div>' +
                '<div class="col"><input type="text" name="senior_ids[]" class="form-control form-control-sm" placeholder="Senior Citizen ID" value="' + (existingSrIds[j] || '') + '"></div>' +
                '<div class="col"><input type="text" name="senior_names[]" class="form-control form-control-sm" placeholder="Name on Senior ID" value="' + (existingSrNames[j] || '') + '"></div>';
            seniorContainer.appendChild(d2);
        }
        section.style.display = (pwdCount + seniorCount > 0) ? '' : 'none';
    }

    function addEmptyRow() {
        var row = document.createElement('tr');
        row.className = 'item-row';
        row.setAttribute('data-index', '0');
        row.setAttribute('data-menu-id', '');
        row.setAttribute('data-price', '0');
        row.innerHTML =
            '<td class="text-center">' +
            '<div style="width:50px;height:50px;margin:0 auto;display:flex;align-items:center;justify-content:center;">' +
            '<span class="text-muted" style="font-size:10px;">No img</span>' +
            '</div>' +
            '</td>' +
            '<td class="item-menu-name">' +
            '<span class="text-muted">&mdash;</span>' +
            '<input type="hidden" name="items[0][menu_id]" value="" data-price="0">' +
            '</td>' +
            '<td>' +
            '<input type="number" name="items[0][quantity]" class="form-control form-control-sm item-qty" min="1" max="999" value="1" required>' +
            '</td>' +
            '<td class="text-end item-unit-price">&#x20B1;0.00</td>' +
            '<td class="text-end fw-semibold item-line-total">&#x20B1;0.00</td>' +
            '<td class="text-center">' +
            '<button type="button" class="btn btn-sm btn-outline-danger remove-item-row">&times;</button>' +
            '</td>';
        orderItemsBody.appendChild(row);
        bindRowEvents(row);
    }

    function bindRowEvents(row) {
        var qtyInput = row.querySelector('.item-qty');
        var removeBtn = row.querySelector('.remove-item-row');
        if (qtyInput) qtyInput.addEventListener('input', recalcPreview);
        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                row.remove();
                if (orderItemsBody.querySelectorAll('.item-row').length === 0) {
                    addEmptyRow();
                }
                recalcPreview();
                if (typeof feather !== 'undefined') { setTimeout(function() { feather.replace(); }, 50); }
            });
        }
    }

    orderItemsBody.querySelectorAll('.item-row').forEach(bindRowEvents);

    branchSelect.addEventListener('change', function() {
        syncDiscountControls();
        buildDiscountRows();
        recalcPreview();
        filterModalByBranch();
        filterTableOptions();
    });

    additionalChargeInput.addEventListener('input', recalcPreview);
    [regularPaxInput, pwdPaxInput, seniorPaxInput].forEach(function(inp) {
        inp.addEventListener('input', function() {
            syncDiscountControls();
            buildDiscountRows();
            recalcPreview();
        });
    });

    syncDiscountControls();
    buildDiscountRows();
    recalcPreview();
    filterTableOptions();

    // ADD MENU MODAL
    var modalSearch = document.getElementById('modalSearch');
    var modalGrid = document.getElementById('modalMenuGrid');
    var modalError = document.getElementById('modalError');
    var addSelectedItemsBtn = document.getElementById('addSelectedItemsBtn');

    if (modalSearch) {
        modalSearch.addEventListener('input', function() {
            var term = this.value.toLowerCase();
            var branchId = selectedBranchId();
            modalGrid.querySelectorAll('.menu-modal-card').forEach(function(card) {
                var name = (card.dataset.menuName || '').toLowerCase();
                var cardBranch = Number(card.dataset.menuBranch || 0);
                var matchesSearch = name.indexOf(term) !== -1;
                var matchesBranch = !branchId || cardBranch === branchId;
                card.parentElement.style.display = matchesSearch && matchesBranch ? '' : 'none';
            });
        });
    }

    function filterModalByBranch() {
        var branchId = selectedBranchId();
        modalGrid.querySelectorAll('.menu-modal-card').forEach(function(card) {
            var cardBranch = Number(card.dataset.menuBranch || 0);
            card.parentElement.style.display = !branchId || cardBranch === branchId ? '' : 'none';
        });
    }
    branchSelect.addEventListener('change', filterModalByBranch);

    document.getElementById('addMenuModal').addEventListener('show.bs.modal', function() {
        modalError.classList.add('d-none');
        filterModalByBranch();
        if (modalSearch) modalSearch.value = '';
        modalGrid.querySelectorAll('.menu-modal-card').forEach(function(card) {
            card.parentElement.style.display = '';
        });
        filterModalByBranch();
        modalGrid.querySelectorAll('.modal-item-qty').forEach(function(input) { input.value = '0'; });
    });

    addSelectedItemsBtn.addEventListener('click', function() {
        var selectedCount = 0;
        var rowsToAdd = [];

        modalGrid.querySelectorAll('.menu-modal-card').forEach(function(card) {
            var qtyInput = card.querySelector('.modal-item-qty');
            var qty = parseInt(qtyInput.value, 10);
            if (!qty || qty < 1) return;

            var menuId = card.dataset.menuId;
            var menuName = card.dataset.menuName;
            var price = card.dataset.menuPrice;
            var image = card.dataset.menuImage;

            rowsToAdd.push({ menuId: menuId, menuName: menuName, price: price, image: image, qty: qty });
            selectedCount++;
        });

        if (selectedCount === 0) {
            modalError.classList.remove('d-none');
            return;
        }

        var firstRow = orderItemsBody.querySelector('.item-row');
        if (firstRow) {
            var firstHidden = firstRow.querySelector('input[type="hidden"][name*="[menu_id]"]');
            if (firstHidden && !firstHidden.value) {
                firstRow.remove();
            }
        }

        rowsToAdd.forEach(function(item, idx) {
            var index = orderItemsBody.querySelectorAll('.item-row').length + idx;
            var imgHtml = item.image
                ? '<img src="' + item.image + '" style="max-width:50px;max-height:50px;border-radius:4px;">'
                : '<span class="text-muted" style="font-size:10px;">No img</span>';

            var row = document.createElement('tr');
            row.className = 'item-row';
            row.setAttribute('data-index', String(index));
            row.setAttribute('data-menu-id', item.menuId);
            row.setAttribute('data-price', item.price);
            row.innerHTML =
                '<td class="text-center">' +
                '<div style="width:50px;height:50px;margin:0 auto;display:flex;align-items:center;justify-content:center;">' + imgHtml + '</div>' +
                '</td>' +
                '<td class="item-menu-name">' +
                item.menuName +
                '<input type="hidden" name="items[' + index + '][menu_id]" value="' + item.menuId + '" data-price="' + item.price + '">' +
                '</td>' +
                '<td>' +
                '<input type="number" name="items[' + index + '][quantity]" class="form-control form-control-sm item-qty" min="1" max="999" value="' + item.qty + '" required>' +
                '</td>' +
                '<td class="text-end item-unit-price">\u20B1' + Number(item.price).toFixed(2) + '</td>' +
                '<td class="text-end fw-semibold item-line-total">\u20B1' + (Number(item.price) * item.qty).toFixed(2) + '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-item-row">&times;</button>' +
                '</td>';
            orderItemsBody.appendChild(row);
            bindRowEvents(row);
        });

        recalcPreview();

        var modalEl = document.getElementById('addMenuModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        if (typeof feather !== 'undefined') {
            setTimeout(function() { feather.replace(); }, 100);
        }
    });
})();
</script>
@endpush