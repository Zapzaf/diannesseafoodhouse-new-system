@extends('layouts.app')

@section('page_title', 'Record Payment - Dianne\'s Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="credit-card"></i></div>
                            Record Payment
                        </h1>
                        <div class="page-header-subtitle">Post payment for a menu order</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a class="btn btn-light text-primary" href="{{ route('payments.index') }}">
                            <i class="me-1" data-feather="arrow-left"></i> Back to Payments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-feather="dollar-sign"></i> Payment Details</div>
            <div class="card-body">
                <form action="{{ route('payments.store') }}" method="POST" onsubmit="return validatePaymentForm()">
                    @csrf

                    @if(isset($menuOrder))
                        {{-- Direct payment for a specific order --}}
                        <input type="hidden" name="menu_order_id" value="{{ $menuOrder->id }}">

                        <div class="alert alert-info mb-4">
                            <strong>Order {{ $menuOrder->orderNumber() }}</strong> &mdash; {{ $menuOrder->customerDisplayName() }}
                            <br>
                            Branch: {{ $menuOrder->branch->name ?? '—' }} &middot;
                            Total: ₱{{ number_format((float) $menuOrder->total_amount, 2) }}
                            &middot; Balance: ₱{{ number_format((float) $menuOrder->balance, 2) }}
                        </div>
                    @else
                        {{-- No order pre-selected — let user pick from pending orders --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Order <span class="text-danger">*</span></label>
                            <select class="form-select @error('menu_order_id') is-invalid @enderror" name="menu_order_id" id="orderSelect" required>
                                <option value="">-- Select an Order --</option>
                                @foreach($pendingOrders as $order)
                                <option value="{{ $order->id }}"
                                    data-balance="{{ number_format((float) $order->balance, 2, '.', '') }}"
                                    {{ (string) old('menu_order_id') === (string) $order->id ? 'selected' : '' }}>
                                    Order {{ $order->orderNumber() }} - {{ $order->customerDisplayName() }}
                                    | {{ $order->branch->name ?? '—' }}
                                    | Total: ₱{{ number_format((float) $order->total_amount, 2) }}
                                    | Balance: ₱{{ number_format((float) $order->balance, 2) }}
                                </option>
                                @endforeach
                            </select>
                            @error('menu_order_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">PHP</span>
                                <input type="number" class="form-control @error('amount') is-invalid @enderror" name="amount" id="amountInput"
                                    value="{{ old('amount', isset($menuOrder) ? number_format((float) $menuOrder->balance, 2, '.', '') : '') }}"
                                    step="0.01" min="0.01"
                                    max="{{ isset($menuOrder) ? number_format((float) $menuOrder->balance, 2, '.', '') : '999999.99' }}" required>
                            </div>
                            @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="invalid-feedback d-block" id="amountError" style="display:none;"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Method <span class="text-danger">*</span></label>
                            <select class="form-select @error('method') is-invalid @enderror" name="method" required>
                                @foreach(['cash' => 'Cash', 'gcash' => 'GCash', 'card' => 'Card', 'bank' => 'Bank Transfer'] as $value => $label)
                                <option value="{{ $value }}" {{ old('method', 'cash') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('payment_date') is-invalid @enderror" name="payment_date"
                                value="{{ old('payment_date', date('Y-m-d')) }}" required>
                            @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reference Number</label>
                        <input type="text" class="form-control" name="reference_number" value="{{ old('reference_number') }}" placeholder="Optional reference for non-cash payments" maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea class="form-control" name="notes" rows="3">{{ old('notes') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ isset($menuOrder) ? route('menu-orders.show', $menuOrder) : route('payments.index') }}" class="btn btn-secondary text-light">Cancel</a>
                        <button type="submit" id="savePaymentBtn" class="btn btn-primary"><i class="me-1" data-feather="save"></i> Save Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
@if(!isset($menuOrder))
const orderSelect = document.getElementById('orderSelect');
const balanceInfo = document.getElementById('balanceInfo');
const amountInput = document.getElementById('amountInput');
const amountErr = document.getElementById('amountError');
const savePaymentBtn = document.getElementById('savePaymentBtn');

function effectiveBalance() {
    const option = orderSelect.options[orderSelect.selectedIndex];
    if (!option || !orderSelect.value) return 0;
    return Number(option.dataset.balance || 0);
}

function updateAmount() {
    if (!orderSelect.value) {
        amountInput.value = '';
        amountInput.max = '999999.99';
        return;
    }
    const bal = effectiveBalance();
    amountInput.max = bal.toFixed(2);
    if (!amountInput.value || Number(amountInput.value) > bal) {
        amountInput.value = bal.toFixed(2);
    }
}

orderSelect.addEventListener('change', updateAmount);
@endif

function validatePaymentForm() {
    const amount = Number(document.getElementById('amountInput').value || 0);
    const max = Number(document.getElementById('amountInput').max || 0);
    const amountErrEl = document.getElementById('amountError');

    document.getElementById('amountInput').classList.remove('is-invalid');
    if (amountErrEl) {
        amountErrEl.style.display = 'none';
        amountErrEl.textContent = '';
    }

    @if(isset($menuOrder))
    if (amount <= 0) {
        document.getElementById('amountInput').classList.add('is-invalid');
        if (amountErrEl) {
            amountErrEl.style.display = '';
            amountErrEl.textContent = 'Payment amount must be greater than zero.';
        }
        return false;
    }
    if (amount > max) {
        document.getElementById('amountInput').classList.add('is-invalid');
        if (amountErrEl) {
            amountErrEl.style.display = '';
            amountErrEl.textContent = 'Payment amount cannot exceed the outstanding balance.';
        }
        return false;
    }
    @else
    const orderId = document.getElementById('orderSelect').value;
    if (!orderId) {
        alert('Please select an order.');
        return false;
    }
    if (amount <= 0) {
        document.getElementById('amountInput').classList.add('is-invalid');
        if (amountErrEl) {
            amountErrEl.style.display = '';
            amountErrEl.textContent = 'Payment amount must be greater than zero.';
        }
        return false;
    }
    if (amount > max) {
        document.getElementById('amountInput').classList.add('is-invalid');
        if (amountErrEl) {
            amountErrEl.style.display = '';
            amountErrEl.textContent = 'Payment amount cannot exceed the outstanding balance.';
        }
        return false;
    }
    @endif
    return true;
}
</script>
@endpush
