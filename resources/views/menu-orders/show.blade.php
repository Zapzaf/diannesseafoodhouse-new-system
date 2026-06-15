@extends('layouts.app')

@section('page_title', 'Menu Order ' . $menuOrder->orderNumber() . ' - Dianne\'s Seafood House')

@php
    $additionalCharges = $menuOrder->additionalChargesList();
    $showDiscountSummary = (float) $menuOrder->discount_amount > 0 || (int) ($menuOrder->pwd_pax ?? 0) > 0 || (int) ($menuOrder->senior_pax ?? 0) > 0;
    $showVatSummary = (float) $menuOrder->vat_rate > 0 || (float) $menuOrder->vat_amount > 0;
    $canModifyItems = (string) $menuOrder->status === 'open' && $menuOrder->payments->isEmpty();
    $canRecordPayment = (string) $menuOrder->status === 'open' && (string) $menuOrder->payment_status !== 'paid' && (float) $menuOrder->balance > 0;
    $canEditOrder = (string) $menuOrder->status === 'open' && $menuOrder->payments->isEmpty();
    $canDeleteOrder = $menuOrder->payments->isEmpty() && in_array((string) $menuOrder->status, ['open', 'cancelled'], true);
    $canVoidOrder = auth()->user()->isBranchManager() && ! in_array((string) $menuOrder->status, ['voided', 'cancelled'], true) && $menuOrder->payments->isEmpty();
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
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-start justify-content-between g-3">
                    <div class="col-xl-6 mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="shopping-bag"></i></div>
                            Menu Order {{ $menuOrder->orderNumber() }}
                        </h1>
                        <div class="page-header-subtitle">
                            {{ $menuOrder->customerDisplayName() }} - {{ $menuOrder->branch->name ?? '-' }}
                        </div>
                    </div>
                    <div class="col-xl-6 mt-4">
                        <div class="order-action-bar">
                            <a class="btn btn-light text-primary" href="{{ route('menu-orders.billing', $menuOrder) }}" target="_blank">
                                <i class="me-1" data-feather="printer"></i> Billing
                            </a>
                            @if($canRecordPayment)
                            <a class="btn btn-success text-white" href="{{ route('payments.create', ['menu_order_id' => $menuOrder->id]) }}">
                                <i class="me-1" data-feather="credit-card"></i> Pay
                            </a>
                            @endif
                            @if($canEditOrder)
                            <a class="btn btn-light text-primary" href="{{ route('menu-orders.edit', $menuOrder) }}">
                                <i class="me-1" data-feather="edit"></i> Edit
                            </a>
                            @endif
                            @if($canEditOrder)
                            <form action="{{ route('menu-orders.cancel', $menuOrder) }}" method="POST" onsubmit="return confirm('Cancel this order?')">
                                @csrf
                                <button type="submit" class="btn btn-light text-warning">
                                    <i class="me-1" data-feather="x-circle"></i> Cancel
                                </button>
                            </form>
                            @endif
                            @if($canVoidOrder)
                            <button type="button" class="btn btn-light text-warning" data-bs-toggle="modal" data-bs-target="#voidModal">
                                <i class="me-1" data-feather="slash"></i> Void
                            </button>
                            @endif
                            @if($canDeleteOrder)
                            <form action="{{ route('menu-orders.destroy', $menuOrder) }}" method="POST" onsubmit="return confirm('Delete this menu order?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light text-danger">
                                    <i class="me-1" data-feather="trash-2"></i> Delete
                                </button>
                            </form>
                            @endif
                            <a class="btn btn-light text-primary" href="{{ route('menu-orders.index') }}">
                                <i class="me-1" data-feather="arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card kpi-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Order Status</div>
                            <div class="stat-value stat-value-sm"><span class="badge-status badge-{{ strtolower((string) $menuOrder->status) }}">{{ ucfirst((string) $menuOrder->status) }}</span></div>
                        </div>
                        <div class="icon-circle"><i data-feather="shopping-bag"></i></div>
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
                        <div class="icon-circle"><i data-feather="credit-card"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kpi-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Balance</div>
                            <div class="stat-value {{ (float) $menuOrder->balance > 0 ? 'text-danger' : 'text-success' }}">PHP {{ number_format((float) $menuOrder->balance, 2) }}</div>
                        </div>
                        <div class="icon-circle"><i data-feather="dollar-sign"></i></div>
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
                        <div class="icon-circle"><i data-feather="list"></i></div>
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
                <section class="card shadow-sm order-tab-card">
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
                            <i data-feather="plus-circle" class="me-1"></i> Add Menu
                        </button>
                        @endif
                    </div>
                    <div class="tab-content" id="orderDetailTabsContent">
                        <div class="tab-pane fade show active" id="ordered-items-pane" role="tabpanel" aria-labelledby="ordered-items-tab" tabindex="0" style = "padding: 20px;">
                            <div class="tab-pane-summary">
                                <span>{{ $menuOrder->items->count() }} line item(s)</span>
                                <span>{{ number_format($itemCount) }} total quantity</span>
                            </div>
                            <div class="table-responsive">
                            <table class="table table-hover align-middle order-items-table">
                                <thead>
                                    <tr>
                                        <th>Menu Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                        <th class="text-end">COGS</th>
                                        <th class="text-end">Profit</th>
                                        <th>Status</th>
                                        @if($canModifyItems)
                                        <th class="text-center">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($menuOrder->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->menu->name ?? 'N/A' }}</div>
                                            <div class="small text-muted">Line #{{ $loop->iteration }}</div>
                                        </td>
                                        <td class="text-end fw-semibold">{{ $item->quantity }}</td>
                                        <td class="text-end">PHP {{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end fw-semibold">PHP {{ number_format((float) $item->subtotal, 2) }}</td>
                                        <td class="text-end text-muted">PHP {{ number_format((float) $item->cost, 2) }}</td>
                                        <td class="text-end {{ (float) $item->profit >= 0 ? 'text-success' : 'text-danger' }}">PHP {{ number_format((float) $item->profit, 2) }}</td>
                                        <td>
                                            @if($item->inventory_deducted)
                                                <span class="badge bg-success">
                                                    <i data-feather="check" style="width:11px;height:11px;"></i> Deducted
                                                </span>
                                            @elseif($item->menu && $item->menu->items->isEmpty())
                                                <span class="badge bg-warning text-dark">No Recipe</span>
                                            @else
                                                <span class="badge bg-secondary text-white">Pending</span>
                                            @endif
                                        </td>
                                        @if($canModifyItems)
                                        <td class="text-center">
                                            @if($menuOrder->items->count() > 1)
                                            <form action="{{ route('menu-orders.items.destroy', [$menuOrder, $item]) }}" method="POST" onsubmit="return confirm('Delete this item and replenish its deducted inventory?')" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete item">
                                                    <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                                </button>
                                            </form>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        @endif
                                    </tr>
                                    @empty
                                    <tr><td colspan="{{ $canModifyItems ? 8 : 7 }}" class="text-center text-muted py-4">No items on this order.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="payment-history-pane" role="tabpanel" aria-labelledby="payment-history-tab" tabindex="0" style = "padding: 20px;">
                            <div class="tab-pane-summary">
                                <span>{{ $paymentCount }} payment record(s)</span>
                                <span>Paid PHP {{ number_format((float) $menuOrder->amount_paid, 2) }}</span>
                            </div>
                            <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end">Applied</th>
                                        <th class="text-end">Tendered</th>
                                        <th class="text-end">Change</th>
                                        <th>Reference</th>
                                        <th>OR No.</th>
                                        <th class="text-center">Receipt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($menuOrder->payments->sortByDesc('created_at') as $payment)
                                    <tr>
                                        <td>{{ $payment->payment_date?->format('M d, Y') }}</td>
                                        <td><span class="badge bg-secondary text-white">{{ ucfirst((string) $payment->method) }}</span></td>
                                        <td class="text-end text-success fw-semibold">PHP {{ number_format((float) $payment->amount, 2) }}</td>
                                        <td class="text-end">PHP {{ number_format((float) $payment->amount_tendered, 2) }}</td>
                                        <td class="text-end text-danger">PHP {{ number_format((float) $payment->change_amount, 2) }}</td>
                                        <td>{{ $payment->reference_number ?: '-' }}</td>
                                        <td class="fw-semibold">{{ $payment->or_number ?: '-' }}</td>
                                        <td class="text-center">
                                            <a href="{{ route('menu-orders.payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print receipt">
                                                <i data-feather="printer" style="width:14px;height:14px;"></i>
                                            </a>
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
                                <strong>PHP {{ number_format((float) $menuOrder->subtotal, 2) }}</strong>
                            </div>
                            @if(!empty($additionalCharges))
                            <div class="billing-row">
                                <span>Additional Charges</span>
                                <strong>PHP {{ number_format((float) $menuOrder->additional_charge_amount, 2) }}</strong>
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
                                    <span>PHP {{ number_format((float) ($charge['amount'] ?? 0), 2) }}</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                            @if($showDiscountSummary)
                            <div class="billing-row text-danger">
                                <span>Discount</span>
                                <strong>- PHP {{ number_format((float) $menuOrder->discount_amount, 2) }}</strong>
                            </div>
                            @endif
                            @if($showVatSummary)
                            <div class="billing-row">
                                <span>VAT ({{ number_format((float) $menuOrder->vat_rate, 2) }}%)</span>
                                <strong>PHP {{ number_format((float) $menuOrder->vat_amount, 2) }}</strong>
                            </div>
                            @endif
                            <div class="billing-total">
                                <span>Total</span>
                                <strong>PHP {{ number_format((float) $menuOrder->total_amount, 2) }}</strong>
                            </div>
                            <div class="billing-row text-success">
                                <span>Amount Paid</span>
                                <strong>PHP {{ number_format((float) $menuOrder->amount_paid, 2) }}</strong>
                            </div>
                            <div class="billing-balance {{ (float) $menuOrder->balance > 0 ? 'is-due' : 'is-paid' }}">
                                <span>Balance</span>
                                <strong>PHP {{ number_format((float) $menuOrder->balance, 2) }}</strong>
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
                                        <span class="input-group-text">PHP</span>
                                        <input type="number" name="amount_tendered" id="amountTendered" class="form-control @error('amount_tendered') is-invalid @enderror" value="{{ old('amount_tendered', $balance) }}" min="0.01" step="0.01" required data-balance="{{ $balance }}">
                                    </div>
                                    @error('amount_tendered')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <input type="hidden" name="amount" id="amountApplied" value="{{ $balance }}">
                                <div class="change-preview mb-3" id="changeDisplay" style="display:none;">
                                    <div class="d-flex justify-content-between">
                                        <span>Balance Due</span>
                                        <strong>PHP {{ number_format((float) $menuOrder->balance, 2) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span>Change</span>
                                        <strong id="changeAmt">PHP 0.00</strong>
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
                                    <i class="me-1" data-feather="save"></i> Save Payment
                                </button>
                            </form>
                        </div>
                    </section>
                    @endif
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('menu-orders.void', $menuOrder) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i data-feather="alert-triangle"></i> Void Menu Order</h5>
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

@if($canModifyItems)
    @include('menu-orders.partials.add-menu-modal', [
        'menus' => $menus,
        'formAction' => route('menu-orders.items.store', $menuOrder),
        'formId' => 'addMenuItemsForm',
        'fieldsContainerId' => 'selectedMenuItemsFields',
    ])
@endif

@endsection

@push('styles')
<style>
.order-action-bar {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: .5rem;
}

.order-action-bar form {
    margin: 0;
}

.kpi-card {
    border-left: 4px solid var(--bs-primary);
}

.kpi-card .icon-circle {
    align-items: center;
    background: rgba(162, 44, 41, .1);
    border-radius: 50%;
    display: flex;
    height: 3rem;
    justify-content: center;
    width: 3rem;
}

.kpi-card .icon-circle svg {
    color: #a22c29;
    stroke: #a22c29;
}

.stat-label {
    color: #6b7280;
    font-size: .76rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.stat-value {
    color: #111827;
    font-size: 1.6rem;
    font-weight: 800;
}

.stat-value-sm {
    font-size: 1rem;
    margin-top: .25rem;
}

.order-tab-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.order-tab-card .nav-tabs {
    border-bottom: 0;
}

.order-tab-card .nav-link {
    align-items: center;
    display: flex;
    font-weight: 700;
    gap: .25rem;
}

.tab-pane-summary {
    align-items: center;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    font-size: .85rem;
    gap: 1rem;
    justify-content: space-between;
    padding: .75rem 1rem;
}

.order-items-table th,
.order-items-table td {
    white-space: nowrap;
}

.order-items-table th:first-child,
.order-items-table td:first-child {
    min-width: 180px;
    white-space: normal;
}

.order-sidebar {
    position: sticky;
    top: 1rem;
}

.billing-row,
.billing-total,
.billing-balance {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .55rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.billing-row span,
.billing-breakdown {
    color: #64748b;
}

.billing-breakdown {
    border-bottom: 1px solid #e5e7eb;
    font-size: .82rem;
    padding: 0 0 .55rem 1rem;
}

.billing-total {
    align-items: center;
    border-bottom: 0;
    color: #0f172a;
    font-size: 1.05rem;
    padding-top: .85rem;
}

.billing-balance {
    border-bottom: 0;
    border-radius: .4rem;
    margin-top: .5rem;
    padding: .75rem;
}

.billing-balance.is-due {
    background: #fef2f2;
    color: #991b1b;
}

.billing-balance.is-paid {
    background: #f0fdf4;
    color: #166534;
}

.profile-list {
    display: grid;
    gap: .75rem;
}

.profile-list div {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.profile-list span {
    color: #64748b;
}

.profile-list strong {
    text-align: right;
}

.discount-id-row {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    margin-bottom: .4rem;
}

.change-preview {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: .5rem;
    color: #166534;
    padding: .75rem;
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

.modal-item-qty::-webkit-inner-spin-button,
.modal-item-qty::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.modal-item-qty {
    -moz-appearance: textfield;
}

@media (max-width: 1199.98px) {
    .order-action-bar {
        justify-content: flex-start;
    }

    .order-sidebar {
        position: static;
    }
}

@media (max-width: 575.98px) {
    .order-tab-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .order-tab-card .nav-tabs {
        width: 100%;
    }

    .order-action-bar .btn,
    .order-action-bar form {
        width: 100%;
    }

    .order-action-bar form .btn {
        width: 100%;
    }
}
</style>
@endpush

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
            changeAmt.textContent = 'PHP ' + change.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            changeDisplay.style.display = 'none';
        }
    }

    inp.addEventListener('input', update);
    update();
})();
</script>
@endif

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
            card.style.boxShadow = '0 0 0 0.2rem rgba(162, 44, 41, 0.25)';
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
@endpush
