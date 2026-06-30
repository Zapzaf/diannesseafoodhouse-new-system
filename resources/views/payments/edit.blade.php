@extends('layouts.app')

@section('page_title', 'Edit Payment #' . $payment->payment_id . ' - Welheim Insurance')

@section('content')
    <x-page-header :title="'Edit Payment #' . $payment->payment_id" subtitle="Update payment and remittance breakdown details" icon="edit">
        <a class="btn btn-primary" href="{{ route('payments.show', $payment) }}">
            <i class="me-1" data-lucide="arrow-left"></i> Back to Payment
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="info"></i> Payment Details</div>
            <div class="card-body">
                <form action="{{ route('payments.update', $payment) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" for="policy_id">Policy <span class="text-danger">*</span></label>
                            <select class="form-select" id="policy_id" name="policy_id" required>
                                <option value="">-- Select Policy --</option>
                                @foreach($policies as $policy)
                                    <option value="{{ $policy->policy_id }}"
                                        data-premium="{{ $policy->premium_amount }}"
                                        data-paid="{{ $policy->total_paid }}"
                                        data-balance="{{ $policy->balance }}"
                                        {{ old('policy_id', $payment->policy_id) == $policy->policy_id ? 'selected' : '' }}>
                                        {{ $policy->provider_policy_number }} - {{ $policy->client->full_name }} (₱{{ number_format($policy->balance, 2) }} balance)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" for="payment_date">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" value="{{ old('payment_date', optional($payment->payment_date)->format('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div id="policyInfo" class="alert alert-info d-none mb-3">
                        <div class="row">
                            <div class="col-md-4"><strong>Premium:</strong> <span id="infoPremium">-</span></div>
                            <div class="col-md-4"><strong>Total Paid:</strong> <span id="infoPaid">-</span></div>
                            <div class="col-md-4"><strong>Balance:</strong> <span id="infoBalance">-</span></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold" for="total_payment">Total Payment (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control calc-input" id="total_payment" name="total_payment" value="{{ old('total_payment', $payment->total_payment ?? $payment->amount_paid) }}" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold" for="payment_method">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" id="payment_method" name="payment_method" disabled>
                                <option value="cash" {{ old('payment_method', $payment->payment_method) == 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="check" {{ old('payment_method', $payment->payment_method) == 'check' ? 'selected' : '' }}>Check</option>
                                <option value="bank_transfer" {{ old('payment_method', $payment->payment_method) == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="gcash" {{ old('payment_method', $payment->payment_method) == 'gcash' ? 'selected' : '' }}>GCash</option>
                                <option value="maya" {{ old('payment_method', $payment->payment_method) == 'maya' ? 'selected' : '' }}>Maya</option>
                                <option value="credit_card" {{ old('payment_method', $payment->payment_method) == 'credit_card' ? 'selected' : '' }}>Credit Card</option>
                                <option value="debt" {{ old('payment_method', $payment->payment_method) == 'debt' ? 'selected' : '' }}>Debt</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold" for="reference_number">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" value="{{ old('reference_number', $payment->reference_number) }}" placeholder="Check/Transaction #">
                        </div>
                    </div>

                    <div class="card border mb-3">
                        <div class="card-header"><i class="me-1" data-lucide="list"></i> Payment Breakdown</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold" for="lto_remittance">LTO Remittance (₱)</label>
                                    <input type="number" class="form-control calc-input" id="lto_remittance" name="lto_remittance" value="{{ old('lto_remittance', $payment->lto_remittance ?? 0) }}" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold" for="smoke_test_remittance">Smoke Test Remittance (₱)</label>
                                    <input type="number" class="form-control calc-input" id="smoke_test_remittance" name="smoke_test_remittance" value="{{ old('smoke_test_remittance', $payment->smoke_test_remittance ?? 0) }}" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold" for="insurance_remittance">Insurance Remittance (₱)</label>
                                    <input type="number" class="form-control calc-input" id="insurance_remittance" name="insurance_remittance" value="{{ old('insurance_remittance', $payment->insurance_remittance ?? 0) }}" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold" for="loyalty_discount">Loyalty Discount (₱)</label>
                                    <input type="number" class="form-control calc-input" id="loyalty_discount" name="loyalty_discount" value="{{ old('loyalty_discount', $payment->loyalty_discount ?? 0) }}" step="0.01" min="0" required>
                                </div>
                            </div>

                            <div class="row align-items-end">
                                <div class="col-md-8">
                                    <div class="small text-muted mb-1">
                                        Formula: Net Income = Total Payment - (LTO + Smoke Test + Insurance + Loyalty Discount)
                                    </div>
                                    <div class="small text-muted">
                                        Total Deductions: <strong id="totalDeductions">₱0.00</strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold" for="net_income">Net Income (₱)</label>
                                    <input type="text" class="form-control" id="net_income" name="net_income" value="{{ old('net_income', $payment->net_income ?? '0.00') }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="remarks">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Optional notes about this payment update...">{{ old('remarks', $payment->remarks) }}</textarea>
                    </div>

                    <div id="incomeWarning" class="alert alert-danger d-none mb-3">
                        Net income cannot be negative. Please adjust remittances or discount values.
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('payments.show', $payment) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="me-1" data-lucide="save"></i> Update Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const policySelect = document.getElementById('policy_id');
    const policyInfo = document.getElementById('policyInfo');
    const totalPaymentInput = document.getElementById('total_payment');
    const ltoInput = document.getElementById('lto_remittance');
    const smokeTestInput = document.getElementById('smoke_test_remittance');
    const insuranceInput = document.getElementById('insurance_remittance');
    const loyaltyInput = document.getElementById('loyalty_discount');
    const netIncomeInput = document.getElementById('net_income');
    const totalDeductionsEl = document.getElementById('totalDeductions');
    const incomeWarning = document.getElementById('incomeWarning');

    policySelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (this.value) {
            document.getElementById('infoPremium').textContent = '₱' + parseFloat(selected.dataset.premium).toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('infoPaid').textContent = '₱' + parseFloat(selected.dataset.paid).toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('infoBalance').textContent = '₱' + parseFloat(selected.dataset.balance).toLocaleString('en-US', { minimumFractionDigits: 2 });
            policyInfo.classList.remove('d-none');
        } else {
            policyInfo.classList.add('d-none');
        }
    });

    if (policySelect.value) {
        policySelect.dispatchEvent(new Event('change'));
    }

    const asNumber = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const formatMoney = (value) => {
        return '₱' + value.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    function computeNetIncome() {
        const totalPayment = asNumber(totalPaymentInput.value);
        const lto = asNumber(ltoInput.value);
        const smoke = asNumber(smokeTestInput.value);
        const insurance = asNumber(insuranceInput.value);
        const discount = asNumber(loyaltyInput.value);

        const deductions = lto + smoke + insurance + discount;
        const netIncome = totalPayment - deductions;

        totalDeductionsEl.textContent = formatMoney(deductions);
        netIncomeInput.value = netIncome.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        incomeWarning.classList.toggle('d-none', netIncome >= 0);
    }

    document.querySelectorAll('.calc-input').forEach((input) => {
        input.addEventListener('input', computeNetIncome);
    });

    computeNetIncome();
});
</script>
@endpush
