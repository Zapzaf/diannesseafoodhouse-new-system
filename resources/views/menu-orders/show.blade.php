@extends('layouts.app')

@section('page_title', 'Menu Order ' . $menuOrder->orderNumber() . ' - Dianne\'s Seafood House')

@php
    $additionalCharges = $menuOrder->additionalChargesList();
    $showDiscountSummary = (float) $menuOrder->discount_amount > 0 || (int) ($menuOrder->pwd_pax ?? 0) > 0 || (int) ($menuOrder->senior_pax ?? 0) > 0;
    $showVatSummary = (float) $menuOrder->vat_rate > 0 || (float) $menuOrder->vat_amount > 0;
    $itemsUnlocked = $menuOrder->payments->isEmpty() || $menuOrder->is_reactivated || auth()->user()->isAdmin();
    $canModifyItems = (string) $menuOrder->status === 'open' && $itemsUnlocked;
    $canRecordPayment = (string) $menuOrder->status === 'open' && (string) $menuOrder->payment_status !== 'paid' && (float) $menuOrder->balance > 0;
    $canEditOrder = (string) $menuOrder->status === 'open' && $itemsUnlocked;
    $canDeleteOrder = $menuOrder->payments->isEmpty() && in_array((string) $menuOrder->status, ['open', 'cancelled'], true);
    $canVoidOrder = auth()->user()->isBranchManager() && ! in_array((string) $menuOrder->status, ['voided', 'cancelled'], true) && $menuOrder->payments->isEmpty();
    $canReactivateOrder = auth()->user()->isAdmin() && in_array((string) $menuOrder->status, ['voided', 'cancelled', 'completed'], true);
    $itemCount = (int) $menuOrder->items->sum('quantity');
    $paymentCount = $menuOrder->payments->count();
    $rPax = (int) ($menuOrder->regular_pax ?? 0);
    $pPax = (int) ($menuOrder->pwd_pax ?? 0);
    $sPax = (int) ($menuOrder->senior_pax ?? 0);
    $discountIds = json_decode($menuOrder->discount_id_number ?? '', true) ?? [];
    $discountNames = json_decode($menuOrder->discount_name ?? '', true) ?? [];
    $hasPwdDetails = !empty($discountIds['pwd']) || !empty($discountNames['pwd']);
    $hasSeniorDetails = !empty($discountIds['senior']) || !empty($discountNames['senior']);
@endphp

@section('content')
    <x-page-header :title="'Menu Order ' . $menuOrder->orderNumber()" :subtitle="$menuOrder->customerDisplayName() . ' - ' . ($menuOrder->branch->name ?? '-')" icon="shopping-bag">
        <a class="btn btn-outline-primary" href="{{ route('menu-orders.billing', $menuOrder) }}" target="_blank">
            <i class="me-1" data-lucide="printer"></i> Billing
        </a>
        @if($canRecordPayment)
        <a class="btn btn-success text-white" href="{{ route('payments.create', ['menu_order_id' => $menuOrder->id]) }}">
            <i class="me-1" data-lucide="credit-card"></i> Pay
        </a>
        @endif
        @if($canEditOrder)
        <a class="btn btn-outline-primary" href="{{ route('menu-orders.edit', $menuOrder) }}">
            <i class="me-1" data-lucide="edit"></i> Edit
        </a>
        @endif
        @if($canEditOrder)
        <form action="{{ route('menu-orders.cancel', $menuOrder) }}" method="POST" onsubmit="return confirm('Cancel this order?')">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="me-1" data-lucide="x-circle"></i> Cancel
            </button>
        </form>
        @endif
        @if($canVoidOrder)
        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#voidModal">
            <i class="me-1" data-lucide="slash"></i> Void
        </button>
        @endif
        @if($canReactivateOrder)
        @php
            $reactivateConfirm = (string) $menuOrder->status === 'completed'
                ? 'Reopen this completed order? Items and payments can be added again.'
                : 'Reactivate this order and set it back to Open? Table assignment, promo redemption, and inventory will not be automatically restored.';
        @endphp
        <form action="{{ route('menu-orders.reactivate', $menuOrder) }}" method="POST" onsubmit="return confirm('{{ $reactivateConfirm }}')">
            @csrf
            <button type="submit" class="btn btn-outline-success">
                <i class="me-1" data-lucide="rotate-ccw"></i> Reactivate
            </button>
        </form>
        @endif
        @if($canDeleteOrder)
        <form action="{{ route('menu-orders.destroy', $menuOrder) }}" method="POST" onsubmit="return confirm('Delete this menu order?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">
                <i class="me-1" data-lucide="trash-2"></i> Delete
            </button>
        </form>
        @endif
        <a class="btn btn-outline-primary" href="{{ route('menu-orders.index') }}">
            <i class="me-1" data-lucide="arrow-left"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4 menu-order-workspace menu-order-show">
        @include('layouts.alerts')

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card kpi-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Order Status</div>
                            <div class="stat-value stat-value-sm"><span class="badge-status badge-{{ strtolower((string) $menuOrder->status) }}">{{ ucfirst((string) $menuOrder->status) }}</span></div>
                        </div>
                        <div class="icon-circle"><i data-lucide="shopping-bag"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kpi-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Payment Status</div>
                            <div class="stat-value stat-value-sm"><span class="badge-status badge-{{ strtolower((string) $menuOrder->payment_status) }}">{{ ucfirst((string) $menuOrder->payment_status) }}</span></div>
                        </div>
                        <div class="icon-circle"><i data-lucide="credit-card"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kpi-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Balance</div>
                             <div class="stat-value {{ (float) $menuOrder->balance > 0 ? 'text-danger' : 'text-success' }}">₱{{ number_format((float) $menuOrder->balance, 2) }}</div>
                        </div>
                        <div class="icon-circle"><i data-lucide="dollar-sign"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kpi-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Items</div>
                            <div class="stat-value">{{ number_format($itemCount) }}</div>
                        </div>
                        <div class="icon-circle"><i data-lucide="list"></i></div>
                    </div>
                </div>
            </div>
        </div>

        @if((string) $menuOrder->status === 'voided')
        <div class="alert alert-warning border-warning-subtle mb-4">
            <div class="fw-semibold">This order was voided.</div>
            <div class="small">Reason: {{ $menuOrder->void_reason ?: 'No reason recorded' }}</div>
            <div class="small">Voided by {{ $menuOrder->voidedBy->name ?? 'Unknown' }}{{ $menuOrder->voided_at ? ' on ' . \Carbon\Carbon::parse($menuOrder->voided_at)->format('M d, Y h:i A') : '' }}.</div>
        </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="card shadow-sm order-tab-card menu-order-detail-card">
                    <div class="card-header order-tab-header">
                        <ul class="nav nav-tabs card-header-tabs" id="orderDetailTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="ordered-items-tab" data-bs-toggle="tab" data-bs-target="#ordered-items-pane" type="button" role="tab" aria-controls="ordered-items-pane" aria-selected="true">
                                    Ordered Items
                                    <span class="badge bg-primary text-white ms-1">{{ $menuOrder->items->count() }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payment-history-tab" data-bs-toggle="tab" data-bs-target="#payment-history-pane" type="button" role="tab" aria-controls="payment-history-pane" aria-selected="false">
                                    Payment History
                                    <span class="badge bg-secondary text-white ms-1">{{ $paymentCount }}</span>
                                </button>
                            </li>
                        </ul>
                        @if($canModifyItems)
                        <button type="button" class="btn btn-primary btn-sm text-white" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                            <i data-lucide="plus-circle" class="me-1"></i> Add Menu
                        </button>
                        @endif
                    </div>
                    <div class="tab-content" id="orderDetailTabsContent">
                        <div class="tab-pane fade show active menu-order-tab-pane" id="ordered-items-pane" role="tabpanel" aria-labelledby="ordered-items-tab" tabindex="0">
                            <div class="tab-pane-summary">
                                <span>{{ $menuOrder->items->count() }} line item(s)</span>
                                <span>{{ number_format($itemCount) }} total quantity</span>
                            </div>
                            <div class="table-responsive">
                            <table class="table table-hover align-middle order-items-table menu-order-items-table" data-no-table-enhance="1">
                                <thead>
                                    <tr>
                                        <th>Menu Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                        <th class="text-end">COGS</th>
                                        <th class="text-end">Profit</th>
                                        <th>Status</th>
                                        <x-table.th-actions :show="$canModifyItems" />
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($menuOrder->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->menu->name ?? 'N/A' }}</div>
                                            <div class="small text-muted">Line #{{ $loop->iteration }}</div>
                                        </td>
                                        <td class="text-end">
                                            @if($canModifyItems)
                                            <form action="{{ route('menu-orders.items.update', [$menuOrder, $item]) }}" method="POST" class="qty-edit-form d-flex align-items-center justify-content-end gap-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="number" name="quantity" class="form-control form-control-sm qty-input text-end" style="width:64px;" value="{{ $item->quantity }}" min="1" max="999" data-original="{{ $item->quantity }}">
                                                <button type="submit" class="btn btn-sm btn-outline-primary qty-save-btn d-none" title="Save quantity"><i data-lucide="check" style="width:14px;height:14px;"></i></button>
                                            </form>
                                            @else
                                            <span class="fw-semibold">{{ $item->quantity }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end fw-semibold">₱{{ number_format((float) $item->subtotal, 2) }}</td>
                                        <td class="text-end text-muted">₱{{ number_format((float) $item->cost, 2) }}</td>
                                        <td class="text-end {{ (float) $item->profit >= 0 ? 'text-success' : 'text-danger' }}">₱{{ number_format((float) $item->profit, 2) }}</td>
                                        <td>
                                            @if($item->inventory_deducted)
                                                <span class="badge bg-success">
                                                    <i data-lucide="check" style="width:11px;height:11px;"></i> Deducted
                                                </span>
                                            @elseif($item->menu && $item->menu->items->isEmpty())
                                                <span class="badge bg-warning text-white">No Recipe</span>
                                            @else
                                                <span class="badge bg-secondary text-white">Pending</span>
                                            @endif
                                        </td>
                                        <x-table.td-actions :show="$canModifyItems">
                                            @if($menuOrder->items->count() > 1)
                                            <form action="{{ route('menu-orders.items.destroy', [$menuOrder, $item]) }}" method="POST" onsubmit="return confirm('Delete this item and replenish its deducted inventory?')" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete item">
                                                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                                </button>
                                            </form>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </x-table.td-actions>
                                    </tr>
                                    @empty
                                    <tr><td colspan="{{ $canModifyItems ? 8 : 7 }}" class="text-center text-muted py-4">No items on this order.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                        </div>
                        <div class="tab-pane fade menu-order-tab-pane" id="payment-history-pane" role="tabpanel" aria-labelledby="payment-history-tab" tabindex="0">
                            <div class="tab-pane-summary">
                                <span>{{ $paymentCount }} payment record(s)</span>
                                <span>Paid ₱{{ number_format((float) $menuOrder->amount_paid, 2) }}</span>
                            </div>
                            <div class="table-responsive">
                            <table class="table table-hover align-middle" data-no-table-enhance="1">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end">Applied</th>
                                        <th class="text-end">Tendered</th>
                                        <th class="text-end">Change</th>
                                        <th>Reference</th>
                                        <th>OR No.</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($menuOrder->payments->sortByDesc('created_at') as $payment)
                                    <tr>
                                        <td>{{ $payment->payment_date?->format('M d, Y') }}</td>
                                        <td><span class="badge bg-secondary text-white">{{ ucfirst((string) $payment->method) }}</span></td>
                                        <td class="text-end text-success fw-semibold">₱{{ number_format((float) $payment->amount, 2) }}</td>
                                        <td class="text-end">₱{{ number_format((float) $payment->amount_tendered, 2) }}</td>
                                        <td class="text-end text-danger">₱{{ number_format((float) $payment->change_amount, 2) }}</td>
                                        <td>{{ $payment->reference_number ?: '-' }}</td>
                                        <td class="fw-semibold">{{ $payment->or_number ?: '-' }}</td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a href="{{ route('menu-orders.payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print receipt">
                                                    <i data-lucide="printer" style="width:14px;height:14px;"></i>
                                                </a>
                                                @if(auth()->user()->isAdmin())
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-payment-btn"
                                                    data-action="{{ route('menu-orders.payments.update', $payment) }}"
                                                    data-amount="{{ number_format((float) $payment->amount, 2, '.', '') }}"
                                                    data-tendered="{{ number_format((float) $payment->amount_tendered, 2, '.', '') }}"
                                                    data-method="{{ $payment->method }}"
                                                    data-reference="{{ $payment->reference_number }}"
                                                    data-notes="{{ $payment->notes }}"
                                                    title="Edit payment">
                                                    <i data-lucide="pencil" style="width:14px;height:14px;"></i>
                                                </button>
                                                <form action="{{ route('menu-orders.payments.destroy', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this payment? The order balance and status will be recomputed.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete payment">
                                                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="8" class="text-center text-muted py-4">No payments yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <div class="order-sidebar">
                    <section class="card shadow-sm mb-4">
                        <div class="card-header fw-semibold">Billing Summary</div>
                        <div class="card-body">
                            <div class="billing-row">
                                <span>Menu Subtotal</span>
                                <strong>₱{{ number_format((float) $menuOrder->subtotal, 2) }}</strong>
                            </div>
                            @if(!empty($additionalCharges))
                            <div class="billing-row">
                                <span>Additional Charges</span>
                                <strong>₱{{ number_format((float) $menuOrder->additional_charge_amount, 2) }}</strong>
                            </div>
                            <div class="billing-breakdown">
                                @foreach($additionalCharges as $charge)
                                <div class="d-flex justify-content-between gap-3">
                                    <span>
                                        {{ $charge['label'] }}
                                        @if(($charge['type'] ?? 'fixed') === 'percentage')
                                            ({{ number_format((float) ($charge['value'] ?? 0), 2) }}%)
                                        @endif
                                    </span>
                                    <span>₱{{ number_format((float) ($charge['amount'] ?? 0), 2) }}</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                            @if((float) $menuOrder->promo_discount_amount > 0)
                            <div class="billing-row text-danger">
                                <span>Promo Discount ({{ $menuOrder->promo_discount_label ?? ucfirst($menuOrder->promo_discount_source) }})</span>
                                <strong>- ₱{{ number_format((float) $menuOrder->promo_discount_amount, 2) }}</strong>
                            </div>
                            @endif
                            @if($showDiscountSummary)
                            <div class="billing-row text-danger">
                                <span>PWD/Senior Discount</span>
                                <strong>- ₱{{ number_format((float) $menuOrder->discount_amount, 2) }}</strong>
                            </div>
                            @endif
                            @if($showVatSummary)
                            <div class="billing-row">
                                <span>VAT ({{ number_format((float) $menuOrder->vat_rate, 2) }}%)</span>
                                <strong>₱{{ number_format((float) $menuOrder->vat_amount, 2) }}</strong>
                            </div>
                            @endif
                            <div class="billing-total">
                                <span>Total</span>
                                <strong>₱{{ number_format((float) $menuOrder->total_amount, 2) }}</strong>
                            </div>
                            <div class="billing-row text-success">
                                <span>Amount Paid</span>
                                <strong>₱{{ number_format((float) $menuOrder->amount_paid, 2) }}</strong>
                            </div>
                            <div class="billing-balance {{ (float) $menuOrder->balance > 0 ? 'is-due' : 'is-paid' }}">
                                <span>Balance</span>
                                <strong>₱{{ number_format((float) $menuOrder->balance, 2) }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="card shadow-sm mb-4">
                        <div class="card-header fw-semibold">Order Profile</div>
                        <div class="card-body">
                            <div class="profile-list">
                                <div><span>Customer</span><strong>{{ $menuOrder->customerDisplayName() }}</strong></div>
                                <div><span>Branch</span><strong>{{ $menuOrder->branch->name ?? '-' }}</strong></div>
                                <div><span>Created</span><strong>{{ $menuOrder->created_at?->format('M d, Y h:i A') }}</strong></div>
                                <div><span>Table</span><strong>{{ $menuOrder->table?->table_number ?? 'No table selected' }}</strong></div>
                            </div>
                            @if($rPax + $pPax + $sPax > 0)
                            <div class="mt-3 pt-3 border-top">
                                <div class="small text-muted mb-2">Customers</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @if($rPax > 0)<span class="badge bg-secondary text-white">{{ $rPax }} Regular</span>@endif
                                    @if($pPax > 0)<span class="badge bg-primary text-white">{{ $pPax }} PWD</span>@endif
                                    @if($sPax > 0)<span class="badge bg-success">{{ $sPax }} Senior</span>@endif
                                </div>
                            </div>
                            @endif
                            @if($hasPwdDetails || $hasSeniorDetails)
                            <div class="mt-3 pt-3 border-top">
                                <div class="small text-muted mb-2">Discount IDs</div>
                                @foreach(['pwd' => 'PWD', 'senior' => 'Senior'] as $type => $label)
                                    @php
                                        $ids = $discountIds[$type] ?? [];
                                        $names = $discountNames[$type] ?? [];
                                        $count = max(count($ids), count($names));
                                    @endphp
                                    @for($i = 0; $i < $count; $i++)
                                    <div class="discount-id-row">
                                        <span class="badge {{ $type === 'pwd' ? 'bg-primary text-white' : 'bg-success' }}">{{ $label }} #{{ $i + 1 }}</span>
                                        <span class="fw-semibold">{{ $ids[$i] ?? '-' }}</span>
                                        @if(!empty($names[$i]))<span class="text-muted">({{ $names[$i] }})</span>@endif
                                    </div>
                                    @endfor
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </section>

                    @if($canRecordPayment)
                    <section class="card shadow-sm">
                        <div class="card-header fw-semibold">Quick Payment</div>
                        <div class="card-body">
                            <form action="{{ route('menu-orders.payments.store', $menuOrder) }}" method="POST">
                                @csrf
                                @php $balance = number_format((float) $menuOrder->balance, 2, '.', ''); @endphp
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Amount Tendered <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" name="amount_tendered" id="amountTendered" class="form-control @error('amount_tendered') is-invalid @enderror" value="{{ old('amount_tendered', $balance) }}" min="0.01" step="0.01" required data-balance="{{ $balance }}">
                                    </div>
                                    @error('amount_tendered')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <input type="hidden" name="amount" id="amountApplied" value="{{ $balance }}">
                                <div class="change-preview mb-3" id="changeDisplay" style="display:none;">
                                    <div class="d-flex justify-content-between">
                                        <span>Balance Due</span>
                                        <strong>₱{{ number_format((float) $menuOrder->balance, 2) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span>Change</span>
                                        <strong id="changeAmt">₱0.00</strong>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Method <span class="text-danger">*</span></label>
                                    <select name="method" class="form-select @error('method') is-invalid @enderror" required>
                                        @foreach(['cash' => 'Cash', 'gcash' => 'GCash', 'card' => 'Card', 'bank' => 'Bank Transfer'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('method', 'cash') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                    @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Reference Number</label>
                                    <input type="text" name="reference_number" class="form-control" value="{{ old('reference_number') }}" maxlength="100">
                                </div>
                                <button type="submit" class="btn btn-primary w-100 text-white">
                                    <i class="me-1" data-lucide="save"></i> Save Payment
                                </button>
                            </form>
                        </div>
                    </section>
                    @endif
                </div>
            </div>
        </div>
    </div>

<div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('menu-orders.void', $menuOrder) }}" method="POST">
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

@if(auth()->user()->isAdmin())
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="editPaymentForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i data-lucide="pencil" class="me-1"></i> Edit Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">Editing a recorded payment will recompute this order's balance and status. This is only possible while the business day is not yet locked by a Z Reading.</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Amount Applied <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="editPaymentAmount" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Amount Tendered <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount_tendered" id="editPaymentTendered" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Method <span class="text-danger">*</span></label>
                            <select name="method" id="editPaymentMethod" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="card">Card</option>
                                <option value="bank">Bank</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Reference No.</label>
                            <input type="text" name="reference_number" id="editPaymentReference" class="form-control" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Notes</label>
                            <textarea name="notes" id="editPaymentNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@if($canModifyItems)
    @include('menu-orders.partials.add-menu-modal', [
        'menus' => $menus,
        'formAction' => route('menu-orders.items.store', $menuOrder),
        'formId' => 'addMenuItemsForm',
        'fieldsContainerId' => 'selectedMenuItemsFields',
    ])
@endif

@endsection

@push('scripts')
@if($canRecordPayment)
<script>
(function() {
    var inp = document.getElementById('amountTendered');
    var hiddenAmt = document.getElementById('amountApplied');
    var changeDisplay = document.getElementById('changeDisplay');
    var changeAmt = document.getElementById('changeAmt');
    if (!inp || !hiddenAmt || !changeDisplay || !changeAmt) return;

    var balance = parseFloat(inp.dataset.balance || 0);

    function update() {
        var tendered = parseFloat(inp.value || 0);
        var applied = Math.min(tendered, balance);
        var change = Math.max(0, tendered - balance);
        hiddenAmt.value = applied.toFixed(2);

        if (tendered > balance) {
            changeDisplay.style.display = '';
            changeAmt.textContent = '₱' + change.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            changeDisplay.style.display = 'none';
        }
    }

    inp.addEventListener('input', update);
    update();
})();
</script>
@endif

<script>
(function () {
    document.querySelectorAll('.qty-edit-form').forEach(function (form) {
        var input = form.querySelector('.qty-input');
        var saveBtn = form.querySelector('.qty-save-btn');
        if (!input || !saveBtn) return;

        input.addEventListener('input', function () {
            var original = parseInt(input.dataset.original, 10);
            var current = parseInt(input.value, 10);
            saveBtn.classList.toggle('d-none', !current || current === original);
        });
    });
})();
</script>

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
    var branchId = {{ (int) $menuOrder->branch_id }};

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
})();
</script>
@endif

@if(auth()->user()->isAdmin())
<script>
(function () {
    var editForm = document.getElementById('editPaymentForm');
    var amountInput = document.getElementById('editPaymentAmount');
    var tenderedInput = document.getElementById('editPaymentTendered');
    var methodSelect = document.getElementById('editPaymentMethod');
    var referenceInput = document.getElementById('editPaymentReference');
    var notesInput = document.getElementById('editPaymentNotes');

    document.querySelectorAll('.edit-payment-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!editForm) return;
            editForm.action = btn.dataset.action;
            amountInput.value = btn.dataset.amount || '';
            tenderedInput.value = btn.dataset.tendered || '';
            methodSelect.value = btn.dataset.method || 'cash';
            referenceInput.value = btn.dataset.reference || '';
            notesInput.value = btn.dataset.notes || '';

            var modalEl = document.getElementById('editPaymentModal');
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        });
    });
})();
</script>
@endif
@endpush
