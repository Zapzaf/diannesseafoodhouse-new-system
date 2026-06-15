@extends('layouts.app')

@php
    $isEdit = (bool) ($isEdit ?? false);
    $order = $menuOrder ?? null;
    $user = isset($user) ? $user : auth()->user();
    $menuOptionsJson = $menus->map(fn($m) => [
        'id'        => $m->id,
        'name'      => $m->name,
        'price'     => number_format((float) $m->selling_price, 2, '.', ''),
        'branch_id' => (int) $m->branch_id,
        'image'     => $m->image ? asset('storage/' . $m->image) : '',
        'has_recipe' => $m->items->isNotEmpty(),
        'ingredients' => $m->items->map(fn($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'unit' => $item->unit,
            'available' => (float) $item->quantity,
            'required' => (float) $item->pivot->quantity_required,
        ])->values()->toArray(),
    ])->values()->toArray();
    $existingPwdIds    = $isEdit ? (json_decode($order->discount_id_number ?? '', true)['pwd']    ?? []) : [];
    $existingPwdNames  = $isEdit ? (json_decode($order->discount_name      ?? '', true)['pwd']    ?? []) : [];
    $existingSrIds     = $isEdit ? (json_decode($order->discount_id_number ?? '', true)['senior'] ?? []) : [];
    $existingSrNames   = $isEdit ? (json_decode($order->discount_name      ?? '', true)['senior'] ?? []) : [];
    $existingAdditionalCharges = old('additional_charges', $isEdit ? $order->additionalChargesList() : []);
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
                        @php
                            $hasGlobalBranch = !empty(session('selected_branch_id'));
                            $finalBranchId = old('branch_id', $order->branch_id ?? session('selected_branch_id') ?? $selectedBranchId);
                        @endphp
                        <div class="col-md-6 mb-3 {{ $hasGlobalBranch ? 'd-none' : '' }}">
                            <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" id="branchId" class="form-select @error('branch_id') is-invalid @enderror" required {{ (!$user->isAdmin() || $hasGlobalBranch) ? 'disabled' : '' }}>
                                @foreach($branches as $branch)
                                <option
                                    value="{{ $branch->id }}"
                                    data-vat-enabled="{{ (int) $branch->vat_enabled }}"
                                    data-vat-percentage="{{ number_format((float) ($branch->vat_percentage ?? 12), 2, '.', '') }}"
                                    data-pwd-enabled="{{ (int) ($branch->pwd_discount_enabled ?? 1) }}"
                                    data-senior-enabled="{{ (int) ($branch->senior_discount_enabled ?? 1) }}"
                                    {{ (string) $finalBranchId === (string) $branch->id ? 'selected' : '' }}
                                >
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                            @if(!$user->isAdmin() || $hasGlobalBranch)
                            <input type="hidden" name="branch_id" value="{{ $finalBranchId }}">
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

                    <div id="formValidationAlert" class="alert alert-danger d-none mb-4"></div>
                    <div class="alert alert-light border d-flex align-items-start gap-3 mb-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                            <i data-feather="shield" style="width:16px;height:16px;"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Order protection is enabled</div>
                            <div class="small text-muted mb-0">
                                The system checks branch rules, validates ingredient availability, and records stock deductions only after the full order passes validation.
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 align-items-start">
                        <div class="col-xl-8">

                    {{-- ===================== MENU ITEMS ===================== --}}
                    <fieldset class="border rounded p-3 mb-3 menu-order-section">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Menu Items</legend>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div class="small text-muted">Build the order first. Ingredient availability is validated again before saving.</div>
                            <button type="button" class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#addMenuModal">
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
                                        <th style="width:132px;">Qty</th>
                                        <th style="width:110px;">Price</th>
                                        <th style="width:120px;">Total</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="orderItemsBody">
                                    @php
                                        $orderItems = isset($order) ? $order->items->map(fn($item) => ['id' => $item->id, 'menu_id' => $item->menu_id, 'quantity' => $item->quantity])->toArray() : [];
                                        $oldItems = old('items', $orderItems);
                                    @endphp
                                    @if(count($oldItems) === 0)
                                    @php $oldItems = [['menu_id' => '', 'quantity' => 1]]; @endphp
                                    @endif
                                    @foreach($oldItems as $index => $oldItem)
                                    @php
                                        $existingItemId = (int) ($oldItem['id'] ?? 0);
                                        $selMenu = $menus->firstWhere('id', (int) ($oldItem['menu_id'] ?? 0));
                                    @endphp
                                    <tr class="item-row" data-index="{{ $index }}" data-existing-id="{{ $existingItemId ?: '' }}" data-menu-id="{{ $selMenu->id ?? '' }}" data-price="{{ $selMenu ? number_format((float) $selMenu->selling_price, 2, '.', '') : '0' }}">
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
                                            @if($existingItemId)
                                            <span class="badge bg-secondary ms-2">Locked</span>
                                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $existingItemId }}">
                                            @endif
                                            <input type="hidden" name="items[{{ $index }}][menu_id]" value="{{ $selMenu->id }}" data-price="{{ number_format((float) $selMenu->selling_price, 2, '.', '') }}">
                                            @else
                                            <span class="text-muted">&mdash;</span>
                                            <input type="hidden" name="items[{{ $index }}][menu_id]" value="" data-price="0">
                                            @endif
                                        </td>
                                        <td class="item-qty-cell">
                                            <input type="number" name="items[{{ $index }}][quantity]" class="form-control item-qty"
                                                   min="1" max="999" value="{{ (int) ($oldItem['quantity'] ?? 1) }}" required {{ $existingItemId ? 'readonly' : '' }}>
                                        </td>
                                        <td class="text-end item-unit-price">&#x20B1;0.00</td>
                                        <td class="text-end fw-semibold item-line-total">&#x20B1;0.00</td>
                                        <td class="text-center">
                                            @if($existingItemId)
                                            <span class="text-muted" title="Existing order rows cannot be removed here. Admins can delete rows from the order details page.">&mdash;</span>
                                            @else
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-item-row">&times;</button>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>

                    <fieldset class="border rounded p-3 mb-3 menu-order-section">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Additional Charges</legend>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div class="small text-muted">Add any fixed fees or percentage-based charges. Percentage charges are computed from the current menu subtotal.</div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addAdditionalChargeBtn">
                                <i data-feather="plus" class="me-1"></i> Add Charge
                            </button>
                        </div>

                        <div id="additionalChargesList" class="d-flex flex-column gap-2">
                            @foreach($existingAdditionalCharges as $chargeIndex => $charge)
                            <div class="additional-charge-row border rounded p-3">
                                <div class="row g-2 align-items-end">
                                    <div class="col-lg-5">
                                        <label class="form-label fw-semibold small mb-1">Label / Description</label>
                                        <input type="text" name="additional_charges[{{ $chargeIndex }}][label]" class="form-control additional-charge-label @error('additional_charges.' . $chargeIndex . '.label') is-invalid @enderror" value="{{ $charge['label'] ?? '' }}" maxlength="120" placeholder="e.g. Service Charge" required>
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
                                            <i data-feather="trash-2" class = "text-white"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div id="additionalChargeEmptyState" class="small text-muted {{ count($existingAdditionalCharges) ? 'd-none' : '' }}">
                            No additional charges yet.
                        </div>
                    </fieldset>
                    <input type="hidden" name="discount_type" id="discountType" value="{{ old('discount_type', $order->discount_type ?? 'none') }}">

                    <fieldset class="border rounded p-3 mb-3 menu-order-section">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Pax Breakdown (Discount Per Person)</legend>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Regular Pax</label>
                                <input type="number" min="0" max="999" name="regular_pax" id="regularPax" class="form-control @error('regular_pax') is-invalid @enderror" value="{{ old('regular_pax', $order->regular_pax ?? 1) }}" required>
                            </div>
                            <div class="col-md-4 mb-3" id="pwdPaxGroup">
                                <label class="form-label fw-bold">PWD Pax</label>
                                <input type="number" min="0" max="999" name="pwd_pax" id="pwdPax" class="form-control @error('pwd_pax') is-invalid @enderror" value="{{ old('pwd_pax', $order->pwd_pax ?? 0) }}" required>
                            </div>
                            <div class="col-md-4 mb-3" id="seniorPaxGroup">
                                <label class="form-label fw-bold">Senior Pax</label>
                                <input type="number" min="0" max="999" name="senior_pax" id="seniorPax" class="form-control @error('senior_pax') is-invalid @enderror" value="{{ old('senior_pax', $order->senior_pax ?? 0) }}" required>
                            </div>
                        </div>
                    </fieldset>

                    <div id="discountDetailsSection" style="display:none;">
                        <fieldset class="border rounded p-3 mb-3 menu-order-section">
                            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Discount ID Numbers &amp; Names</legend>
                            <div class="text-muted small mb-3">Enter the government-issued ID number and full name for each discounted customer.</div>
                            <div id="pwdDiscountRows"></div>
                            <div id="seniorDiscountRows"></div>
                        </fieldset>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="Optional kitchen or order notes">{{ old('notes', $order->notes ?? '') }}</textarea>
                    </div>
                        </div>

                        <div class="col-xl-4">
                    <fieldset class="border rounded p-3 mb-3 menu-order-section menu-order-sticky" id="billingPreviewSection">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Billing Preview</legend>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td>Menu Subtotal</td><td class="text-end" id="previewSubtotal">&#x20B1;0.00</td></tr>
                                <tr id="previewAdditionalRow"><td>Additional Charges</td><td class="text-end" id="previewAdditional">&#x20B1;0.00</td></tr>
                                <tr id="previewAdditionalBreakdownRow" class="d-none">
                                    <td colspan="2" class="pt-0">
                                        <div id="previewAdditionalBreakdown" class="small text-muted"></div>
                                    </td>
                                </tr>
                                <tr id="previewDiscountRow"><td>Discount</td><td class="text-end text-danger" id="previewDiscount">&#x20B1;0.00</td></tr>
                                <tr id="previewVatRow"><td>VAT (included)</td><td class="text-end" id="previewVat">&#x20B1;0.00</td></tr>
                                <tr class="fw-bold"><td>Total</td><td class="text-end" id="previewTotal">&#x20B1;0.00</td></tr>
                            </tbody>
                        </table>
                    </fieldset>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header fw-semibold">Inventory Check</div>
                        <div class="card-body">
                            <div class="small text-muted mb-3">The required ingredients below are calculated from the current order and selected branch.</div>
                            <div id="inventoryStatusSummary" class="small text-muted">Add menu items to see ingredient usage.</div>
                            <div id="inventoryIssueList" class="mt-3 d-none"></div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary text-white" id="menuOrderSubmitBtn">
                                    <i class="me-1" data-feather="save"></i> {{ $isEdit ? 'Update Menu Order' : 'Save Menu Order' }}
                                </button>
                                <a href="{{ route('menu-orders.index') }}" class="btn btn-secondary text-white">
                                    <i class="me-1" data-feather="x"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                        </div>
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
            <div class="modal-body p-0">
                <div class="p-3 border-bottom bg-light sticky-top" style="z-index: 10;">
                    <div class="row">
                        <div class="col-12 col-md-8 col-lg-6 mx-auto">
                            <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden">
                                <span class="input-group-text bg-white border-end-0 pe-1 text-muted"><i data-feather="search"></i></span>
                                <input type="text" id="modalSearch" class="form-control border-start-0 ps-2" placeholder="Search menu items by name..." style="box-shadow: none;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 p-md-4" id="modalMenuGrid" style="max-height: 60vh; overflow-y: auto;">
                    <div class="row g-3">
                        @foreach($menus as $menu)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card menu-modal-card h-100 border-0 shadow-sm" data-menu-id="{{ $menu->id }}"
                                 data-menu-name="{{ $menu->name }}"
                                 data-menu-price="{{ number_format((float) $menu->selling_price, 2, '.', '') }}"
                                 data-menu-branch="{{ $menu->branch_id }}"
                                 data-menu-image="{{ $menu->image ? asset('storage/' . $menu->image) : '' }}">
                                
                                <div class="position-relative menu-card-img-wrapper" style="cursor: pointer; height: 140px; background: #f1f5f9; border-radius: 0.5rem 0.5rem 0 0; overflow: hidden; display: flex; align-items: center; justify-content: center;" title="Click to add to order">
                                    @if($menu->image)
                                    <img src="{{ asset('storage/' . $menu->image) }}" style="width: 100%; height: 100%; object-fit: cover;">
                                    @else
                                    <div class="text-center text-muted">
                                        <i data-feather="image" style="width:32px; height:32px; opacity: 0.5; margin-bottom: 0.5rem;"></i>
                                        <div class="small fw-medium">No Image</div>
                                    </div>
                                    @endif
                                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-0 hover-overlay" style="transition: opacity 0.2s;"></div>
                                </div>

                                <div class="card-body p-3 d-flex flex-column">
                                    <div class="fw-bold text-dark text-truncate mb-1" title="{{ $menu->name }}" style="font-size: 0.95rem;">{{ $menu->name }}</div>
                                    <div class="text-primary fw-bold mb-2">&#x20B1;{{ number_format((float) $menu->selling_price, 2) }}</div>
                                    
                                    <div class="mb-3">
                                        @if($menu->items->isNotEmpty())
                                        <span class="badge bg-success-subtle text-success menu-availability-badge border border-success-subtle" data-menu-badge="{{ $menu->id }}">Recipe ready</span>
                                        @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">No recipe</span>
                                        @endif
                                    </div>

                                    <div class="mt-auto">
                                        <div class="input-group input-group-sm">
                                            <button class="btn btn-outline-secondary px-2 btn-qty-minus" type="button"><i data-feather="minus" style="width:14px; height:14px;"></i></button>
                                            <input type="number" class="form-control modal-item-qty text-center fw-bold" value="0" min="0" max="999" style="font-size: 1rem;">
                                            <button class="btn btn-outline-primary px-2 btn-qty-plus" type="button"><i data-feather="plus" style="width:14px; height:14px;"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <div id="modalError" class="alert alert-danger d-none mb-0">
                        <i data-feather="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>Please enter a quantity of at least 1 for the selected items.
                    </div>
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

@push('styles')
<style>
.menu-order-section {
    background: #fff;
    border-color: #dbe4f0 !important;
}

.menu-order-section legend {
    color: #0f172a;
}

.menu-order-sticky {
    position: sticky;
    top: 1.5rem;
}

.menu-modal-card {
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    border: 1px solid transparent !important;
}

.menu-modal-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    border-color: rgba(162, 44, 41, 0.2) !important;
}

.menu-modal-card.border-primary {
    border-color: #a22c29 !important;
}

.menu-modal-card .hover-overlay {
    background: rgba(162, 44, 41, 0.08);
}

.menu-modal-card:hover .hover-overlay {
    opacity: 1 !important;
}

/* Hide arrows from number input */
.modal-item-qty::-webkit-inner-spin-button,
.modal-item-qty::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.modal-item-qty {
    -moz-appearance: textfield;
}

#orderItemsTable th:nth-child(3),
#orderItemsTable td.item-qty-cell {
    min-width: 132px;
}

#orderItemsTable .item-qty {
    min-width: 96px;
    text-align: center;
    font-weight: 600;
}

#inventoryIssueList .inventory-issue {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
    border-radius: .5rem;
    padding: .65rem .75rem;
    margin-bottom: .5rem;
}

#inventoryIssueList .inventory-ok {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
    border-radius: .5rem;
    padding: .65rem .75rem;
}

@media (max-width: 1199.98px) {
    .menu-order-sticky {
        position: static;
    }
}

@media (max-width: 575.98px) {
    #orderItemsTable th:nth-child(3),
    #orderItemsTable td.item-qty-cell {
        min-width: 116px;
    }

    #orderItemsTable .item-qty {
        min-width: 84px;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var branchSelect = document.getElementById('branchId');
    var orderItemsBody = document.getElementById('orderItemsBody');
    var additionalChargesList = document.getElementById('additionalChargesList');
    var addAdditionalChargeBtn = document.getElementById('addAdditionalChargeBtn');
    var additionalChargeEmptyState = document.getElementById('additionalChargeEmptyState');
    var discountTypeInput = document.getElementById('discountType');
    var regularPaxInput = document.getElementById('regularPax');
    var pwdPaxInput = document.getElementById('pwdPax');
    var seniorPaxInput = document.getElementById('seniorPax');
    var tableSelect = document.getElementById('tableId');
    var pwdPaxGroup = document.getElementById('pwdPaxGroup');
    var seniorPaxGroup = document.getElementById('seniorPaxGroup');
    var previewDiscountRow = document.getElementById('previewDiscountRow');
    var previewVatRow = document.getElementById('previewVatRow');
    var inventoryStatusSummary = document.getElementById('inventoryStatusSummary');
    var inventoryIssueList = document.getElementById('inventoryIssueList');
    var formValidationAlert = document.getElementById('formValidationAlert');
    var menuOrderForm = document.getElementById('menuOrderForm');
    var submitButton = document.getElementById('menuOrderSubmitBtn');

    var subtotalEl = document.getElementById('previewSubtotal');
    var additionalEl = document.getElementById('previewAdditional');
    var previewAdditionalRow = document.getElementById('previewAdditionalRow');
    var previewAdditionalBreakdownRow = document.getElementById('previewAdditionalBreakdownRow');
    var previewAdditionalBreakdown = document.getElementById('previewAdditionalBreakdown');
    var discountEl = document.getElementById('previewDiscount');
    var vatEl = document.getElementById('previewVat');
    var totalEl = document.getElementById('previewTotal');

    var masterMenuOptions = @json($menuOptionsJson);
    var menuOptionsById = {};
    var existingPwdIds   = @json($existingPwdIds);
    var existingPwdNames = @json($existingPwdNames);
    var existingSrIds    = @json($existingSrIds);
    var existingSrNames  = @json($existingSrNames);

    masterMenuOptions.forEach(function (menu) {
        menuOptionsById[String(menu.id)] = menu;
    });

    var additionalChargeRowIndex = additionalChargesList ? additionalChargesList.querySelectorAll('.additional-charge-row').length : 0;

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

    function updateAdditionalChargeEmptyState() {
        if (!additionalChargeEmptyState || !additionalChargesList) {
            return;
        }

        additionalChargeEmptyState.classList.toggle('d-none', additionalChargesList.querySelectorAll('.additional-charge-row').length > 0);
    }

    function syncAdditionalChargeRow(row) {
        if (!row) {
            return;
        }

        var typeSelect = row.querySelector('.additional-charge-type');
        var prefix = row.querySelector('.additional-charge-prefix');
        var valueInput = row.querySelector('.additional-charge-value');
        if (!typeSelect || !prefix || !valueInput) {
            return;
        }

        var isPercentage = typeSelect.value === 'percentage';
        prefix.textContent = isPercentage ? '%' : 'PHP';
        valueInput.max = isPercentage ? '100' : '';
        valueInput.placeholder = isPercentage ? 'e.g. 10' : 'e.g. 150.00';
    }

    function bindAdditionalChargeRow(row) {
        if (!row) {
            return;
        }

        syncAdditionalChargeRow(row);

        var typeSelect = row.querySelector('.additional-charge-type');
        var removeButton = row.querySelector('.remove-additional-charge');
        var valueInput = row.querySelector('.additional-charge-value');
        var labelInput = row.querySelector('.additional-charge-label');

        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                syncAdditionalChargeRow(row);
                recalcPreview();
            });
        }

        [valueInput, labelInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', recalcPreview);
            }
        });

        if (removeButton) {
            removeButton.addEventListener('click', function () {
                row.remove();
                updateAdditionalChargeEmptyState();
                recalcPreview();
            });
        }
    }

    function createAdditionalChargeRow(charge) {
        if (!additionalChargesList) {
            return;
        }

        var row = document.createElement('div');
        row.className = 'additional-charge-row border rounded p-3';
        row.innerHTML =
            '<div class="row g-2 align-items-end">' +
                '<div class="col-lg-5">' +
                    '<label class="form-label fw-semibold small mb-1">Label / Description</label>' +
                    '<input type="text" name="additional_charges[' + additionalChargeRowIndex + '][label]" class="form-control additional-charge-label" maxlength="120" placeholder="e.g. Service Charge" value="' + (charge && charge.label ? String(charge.label).replace(/"/g, '&quot;') : '') + '" required>' +
                '</div>' +
                '<div class="col-lg-3">' +
                    '<label class="form-label fw-semibold small mb-1">Charge Type</label>' +
                    '<select name="additional_charges[' + additionalChargeRowIndex + '][type]" class="form-select additional-charge-type" required>' +
                        '<option value="fixed">Fixed Amount</option>' +
                        '<option value="percentage">Percentage</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-lg-3">' +
                    '<label class="form-label fw-semibold small mb-1">Value</label>' +
                    '<div class="input-group">' +
                        '<span class="input-group-text additional-charge-prefix">PHP</span>' +
                        '<input type="number" name="additional_charges[' + additionalChargeRowIndex + '][value]" class="form-control additional-charge-value" min="0.01" step="0.01" value="' + (charge && charge.value ? Number(charge.value).toFixed(2) : '') + '" required>' +
                    '</div>' +
                '</div>' +
                '<div class="col-lg-1 d-grid">' +
                    '<button type="button" class="btn btn-outline-danger remove-additional-charge" title="Remove charge">' +
                        '<i data-feather="trash-2" class="text-white"></i>' +
                    '</button>' +
                '</div>' +
            '</div>';

        additionalChargesList.appendChild(row);
        additionalChargeRowIndex++;

        var typeSelect = row.querySelector('.additional-charge-type');
        if (typeSelect) {
            typeSelect.value = charge && charge.type === 'percentage' ? 'percentage' : 'fixed';
        }

        bindAdditionalChargeRow(row);
        updateAdditionalChargeEmptyState();

        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    function collectAdditionalCharges() {
        var charges = [];

        if (!additionalChargesList) {
            return { charges: charges, total: 0 };
        }

        additionalChargesList.querySelectorAll('.additional-charge-row').forEach(function (row) {
            var labelInput = row.querySelector('.additional-charge-label');
            var typeSelect = row.querySelector('.additional-charge-type');
            var valueInput = row.querySelector('.additional-charge-value');

            var label = labelInput ? String(labelInput.value || '').trim() : '';
            var type = typeSelect ? String(typeSelect.value || 'fixed') : 'fixed';
            var value = valueInput ? Number(valueInput.value || 0) : 0;

            if (!label && !value) {
                return;
            }

            var amount = type === 'percentage'
                ? roundCurrency(subtotalOnly() * (value / 100))
                : roundCurrency(value);

            charges.push({
                label: label,
                type: type,
                value: value,
                amount: amount
            });
        });

        return {
            charges: charges,
            total: roundCurrency(charges.reduce(function (sum, charge) {
                return sum + Number(charge.amount || 0);
            }, 0))
        };
    }

    function roundCurrency(value) {
        return Math.round(Number(value || 0) * 100) / 100;
    }

    function subtotalOnly() {
        var subtotal = 0;
        orderItemsBody.querySelectorAll('.item-row').forEach(function(row) {
            var data = getRowData(row);
            subtotal += data.price * data.qty;
        });

        return roundCurrency(subtotal);
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

    function collectInventoryRequirements() {
        var aggregate = {};

        orderItemsBody.querySelectorAll('.item-row').forEach(function (row) {
            var rowData = getRowData(row);
            if (!rowData.menuId || rowData.qty <= 0) {
                return;
            }

            var menu = menuOptionsById[String(rowData.menuId)];
            if (!menu || Number(menu.branch_id) !== selectedBranchId() || !menu.ingredients || !menu.ingredients.length) {
                return;
            }

            menu.ingredients.forEach(function (ingredient) {
                var key = String(ingredient.id);
                if (!aggregate[key]) {
                    aggregate[key] = {
                        name: ingredient.name,
                        unit: ingredient.unit || '',
                        available: Number(ingredient.available || 0),
                        required: 0,
                    };
                }

                aggregate[key].required += Number(ingredient.required || 0) * rowData.qty;
            });
        });

        return aggregate;
    }

    function renderInventoryStatus() {
        if (!inventoryStatusSummary || !inventoryIssueList) {
            return true;
        }

        var requirements = collectInventoryRequirements();
        var values = Object.values(requirements);

        if (!values.length) {
            inventoryStatusSummary.textContent = 'Add menu items to see ingredient usage.';
            inventoryIssueList.classList.add('d-none');
            inventoryIssueList.innerHTML = '';
            return true;
        }

        var insufficient = values.filter(function (item) {
            return item.required > item.available;
        });

        if (!insufficient.length) {
            inventoryStatusSummary.textContent = values.length + ' ingredient(s) ready for this order.';
            inventoryIssueList.classList.remove('d-none');
            inventoryIssueList.innerHTML = '<div class="inventory-ok">All required ingredients currently have enough stock for the selected quantities.</div>';
            return true;
        }

        inventoryStatusSummary.textContent = insufficient.length + ' ingredient(s) need attention before this order can be saved.';
        inventoryIssueList.classList.remove('d-none');
        inventoryIssueList.innerHTML = insufficient.map(function (item) {
            return '<div class="inventory-issue"><strong>' + item.name + '</strong><br>Available: '
                + item.available.toFixed(2) + ' ' + item.unit
                + ' | Required: ' + item.required.toFixed(2) + ' ' + item.unit + '</div>';
        }).join('');

        return false;
    }

    function showFormValidation(messages) {
        if (!formValidationAlert) {
            return;
        }

        if (!messages.length) {
            formValidationAlert.classList.add('d-none');
            formValidationAlert.innerHTML = '';
            return;
        }

        formValidationAlert.classList.remove('d-none');
        formValidationAlert.innerHTML = '<div class="fw-semibold mb-2">Please fix the following before saving:</div><ul class="mb-0">'
            + messages.map(function (message) { return '<li>' + message + '</li>'; }).join('')
            + '</ul>';
    }

    function recalcPreview() {
        showFormValidation([]);
        var subtotal = 0;
        orderItemsBody.querySelectorAll('.item-row').forEach(function(row) {
            subtotal += recalcRow(row);
        });
        subtotal = roundCurrency(subtotal);
        var chargeSummary = collectAdditionalCharges();
        var additional = chargeSummary.total;
        var gross = subtotal + additional;
        var regularPax = Number(regularPaxInput.value || 0);
        var pwdPax = branchPwdEnabled() ? Number(pwdPaxInput.value || 0) : 0;
        var seniorPax = branchSeniorEnabled() ? Number(seniorPaxInput.value || 0) : 0;
        var totalPax = Math.max(1, regularPax + pwdPax + seniorPax);
        var discountedPax = pwdPax + seniorPax;
        var perPaxGross = gross / totalPax;
        var discountBase = perPaxGross * discountedPax;
        var vatExempt = branchVatEnabled() ? discountBase : 0;
        var discount = discountBase * 0.20;
        var total = Math.max(0, gross - discount);
        var vatPercent = branchVatPercentage();
        var vatBase = Math.max(0, gross - vatExempt);
        var vat = branchVatEnabled() && vatPercent > 0 ? vatBase * (vatPercent / (100 + vatPercent)) : 0;

        subtotalEl.textContent = formatMoney(subtotal);
        additionalEl.textContent = formatMoney(additional);
        if (previewAdditionalRow) {
            previewAdditionalRow.style.display = additional > 0 ? '' : 'none';
        }
        if (previewAdditionalBreakdownRow && previewAdditionalBreakdown) {
            if (chargeSummary.charges.length) {
                previewAdditionalBreakdownRow.classList.remove('d-none');
                previewAdditionalBreakdown.innerHTML = chargeSummary.charges.map(function (charge) {
                    var suffix = charge.type === 'percentage'
                        ? ' (' + Number(charge.value || 0).toFixed(2) + '%)'
                        : '';
                    return '<div class="d-flex justify-content-between"><span>' + charge.label + suffix + '</span><span>' + formatMoney(charge.amount) + '</span></div>';
                }).join('');
            } else {
                previewAdditionalBreakdownRow.classList.add('d-none');
                previewAdditionalBreakdown.innerHTML = '';
            }
        }
        discountEl.textContent = formatMoney(discount);
        vatEl.textContent = formatMoney(vat);
        totalEl.textContent = formatMoney(total);
        renderInventoryStatus();
        refreshModalAvailabilityBadges();
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
        var pwdOn = branchPwdEnabled();
        var seniorOn = branchSeniorEnabled();

        pwdPaxInput.disabled = !pwdOn;
        seniorPaxInput.disabled = !seniorOn;
        if (!pwdOn) pwdPaxInput.value = '0';
        if (!seniorOn) seniorPaxInput.value = '0';
        if (pwdPaxGroup) pwdPaxGroup.style.display = pwdOn ? '' : 'none';
        if (seniorPaxGroup) seniorPaxGroup.style.display = seniorOn ? '' : 'none';
        if (previewDiscountRow) previewDiscountRow.style.display = (pwdOn || seniorOn) ? '' : 'none';
        if (previewVatRow) previewVatRow.style.display = branchVatEnabled() ? '' : 'none';

        var dt = (pwdOn || seniorOn) ? computeDiscountType() : 'none';
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

    function validateFormBeforeSubmit() {
        var messages = [];
        var validRows = Array.from(orderItemsBody.querySelectorAll('.item-row')).filter(function (row) {
            var data = getRowData(row);
            return data.menuId && data.qty > 0;
        });

        if (!selectedBranchId()) {
            messages.push('Please select a branch.');
        }

        if (!validRows.length) {
            messages.push('Please add at least one valid menu item.');
        }

        validRows.forEach(function (row) {
            var data = getRowData(row);
            if (data.qty < 1 || data.qty > 999) {
                messages.push('Each menu quantity must be between 1 and 999.');
            }
        });

        var totalPax = Number(regularPaxInput.value || 0) + Number(pwdPaxInput.value || 0) + Number(seniorPaxInput.value || 0);
        if (totalPax <= 0) {
            messages.push('At least one customer pax is required.');
        }

        var chargeSummary = collectAdditionalCharges();
        chargeSummary.charges.forEach(function (charge, index) {
            if (!charge.label) {
                messages.push('Additional charge #' + (index + 1) + ' needs a label.');
            }

            if (charge.value <= 0) {
                messages.push('Additional charge #' + (index + 1) + ' must be greater than zero.');
            }

            if (charge.type === 'percentage' && charge.value > 100) {
                messages.push('Percentage-based additional charges cannot exceed 100%.');
            }
        });

        if (!renderInventoryStatus()) {
            messages.push('Some required ingredients do not have enough stock for this order.');
        }

        showFormValidation(messages);
        return messages.length === 0;
    }

    function buildDiscountRows() {
        var pwdCount  = branchPwdEnabled() ? Math.max(0, Number(pwdPaxInput.value || 0)) : 0;
        var seniorCount = branchSeniorEnabled() ? Math.max(0, Number(seniorPaxInput.value || 0)) : 0;
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
            '<td class="item-qty-cell">' +
            '<input type="number" name="items[0][quantity]" class="form-control item-qty" min="1" max="999" value="1" required>' +
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
                if (orderItemsBody.querySelectorAll('.item-row').length === 1) {
                    alert('An order must have at least one menu item. Please add another item before removing this one.');
                    return;
                }
                row.remove();
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

    [regularPaxInput, pwdPaxInput, seniorPaxInput].forEach(function(inp) {
        inp.addEventListener('input', function() {
            syncDiscountControls();
            buildDiscountRows();
            recalcPreview();
        });
    });

    if (additionalChargesList) {
        additionalChargesList.querySelectorAll('.additional-charge-row').forEach(bindAdditionalChargeRow);
        updateAdditionalChargeEmptyState();
    }

    if (addAdditionalChargeBtn) {
        addAdditionalChargeBtn.addEventListener('click', function () {
            createAdditionalChargeRow({ type: 'fixed' });
            recalcPreview();
        });
    }

    // ADD MENU MODAL
    var modalSearch = document.getElementById('modalSearch');
    var modalGrid = document.getElementById('modalMenuGrid');
    var modalError = document.getElementById('modalError');
    var addSelectedItemsBtn = document.getElementById('addSelectedItemsBtn');

    syncDiscountControls();
    buildDiscountRows();
    recalcPreview();
    filterTableOptions();

    if (modalSearch) {
        modalSearch.addEventListener('input', function() {
            var term = this.value.toLowerCase();
            var branchId = selectedBranchId();
            if (!modalGrid) return;
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
        if (!modalGrid) return;
        var branchId = selectedBranchId();
        modalGrid.querySelectorAll('.menu-modal-card').forEach(function(card) {
            var cardBranch = Number(card.dataset.menuBranch || 0);
            card.parentElement.style.display = !branchId || cardBranch === branchId ? '' : 'none';
        });
    }

    function refreshModalAvailabilityBadges() {
        if (!modalGrid) return;
        var requirements = collectInventoryRequirements();

        modalGrid.querySelectorAll('.menu-modal-card').forEach(function(card) {
            var menuId = String(card.dataset.menuId || '');
            var badge = card.querySelector('[data-menu-badge]');
            var menu = menuOptionsById[menuId];
            if (!badge || !menu || !menu.ingredients || !menu.ingredients.length) {
                return;
            }

            var isInsufficient = menu.ingredients.some(function (ingredient) {
                var aggregate = requirements[String(ingredient.id)];
                var alreadyUsed = aggregate ? aggregate.required : 0;
                var projected = alreadyUsed + Number(ingredient.required || 0);
                return projected > Number(ingredient.available || 0);
            });

            badge.className = 'badge menu-availability-badge ' + (isInsufficient ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success');
            badge.textContent = isInsufficient ? 'Low stock risk' : 'Recipe ready';
        });
    }
    branchSelect.addEventListener('change', filterModalByBranch);

    var addMenuModal = document.getElementById('addMenuModal');
    if (addMenuModal) {
        addMenuModal.addEventListener('show.bs.modal', function() {
            if (!modalError || !modalGrid) return;
            modalError.classList.add('d-none');
            filterModalByBranch();
            if (modalSearch) modalSearch.value = '';
            modalGrid.querySelectorAll('.menu-modal-card').forEach(function(card) {
                card.parentElement.style.display = '';
            });
            filterModalByBranch();
            modalGrid.querySelectorAll('.modal-item-qty').forEach(function(input) { 
                input.value = '0'; 
                updateCardStyle(input.closest('.menu-modal-card'), 0);
            });
            refreshModalAvailabilityBadges();
        });

        // Add increment/decrement logic
        modalGrid.addEventListener('click', function(e) {
            var btnPlus = e.target.closest('.btn-qty-plus');
            var btnMinus = e.target.closest('.btn-qty-minus');
            var imgWrapper = e.target.closest('.menu-card-img-wrapper');
            
            if (btnPlus || imgWrapper) {
                var card = e.target.closest('.menu-modal-card');
                var input = card.querySelector('.modal-item-qty');
                var val = parseInt(input.value) || 0;
                if (val < 999) {
                    input.value = val + 1;
                    updateCardStyle(card, val + 1);
                }
            } else if (btnMinus) {
                var card = e.target.closest('.menu-modal-card');
                var input = card.querySelector('.modal-item-qty');
                var val = parseInt(input.value) || 0;
                if (val > 0) {
                    input.value = val - 1;
                    updateCardStyle(card, val - 1);
                }
            }
        });

        modalGrid.addEventListener('input', function(e) {
            if (e.target.classList.contains('modal-item-qty')) {
                var val = parseInt(e.target.value) || 0;
                if (val < 0) { e.target.value = 0; val = 0; }
                if (val > 999) { e.target.value = 999; val = 999; }
                updateCardStyle(e.target.closest('.menu-modal-card'), val);
            }
        });

        function updateCardStyle(card, qty) {
            if (qty > 0) {
                card.classList.add('border-primary');
                card.classList.remove('border-0');
                card.style.boxShadow = '0 0 0 0.2rem rgba(162, 44, 41, 0.25)';
            } else {
                card.classList.remove('border-primary');
                card.classList.add('border-0');
                card.style.boxShadow = '';
            }
        }
    }

    if (addSelectedItemsBtn) {
        addSelectedItemsBtn.addEventListener('click', function() {
            if (!modalGrid || !modalError) return;
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
                '<td class="item-qty-cell">' +
                '<input type="number" name="items[' + index + '][quantity]" class="form-control item-qty" min="1" max="999" value="' + item.qty + '" required>' +
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
    }

    menuOrderForm.addEventListener('submit', function (event) {
        if (!validateFormBeforeSubmit()) {
            event.preventDefault();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving Order...';
        }
    });
})();
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>
@endpush
