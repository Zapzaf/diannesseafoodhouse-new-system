@extends('layouts.app')
@section('page_title', 'New Check Voucher')
@php
    $prefilledPcvsData = $replenishPcvs->map(fn ($pcv) => [
        'id' => $pcv->id,
        'pcv_no' => $pcv->pcv_no,
        'date' => $pcv->date->toDateString(),
        'total' => $pcv->total,
    ])->values();
@endphp
@section('content')
    <x-page-header title="New Check Voucher (CV)" subtitle="Record a disbursement: PCF replenishment, APV settlement, or direct payment" icon="plus-circle">
        <a href="{{ route('check-vouchers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Check Vouchers
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('check-vouchers.store') }}" method="POST" id="cvForm">
            @csrf
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card p-4 shadow-sm h-100">
                        <div class="card-body p-0">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                                <i data-lucide="info" style="width: 20px; height: 20px;"></i>
                                <span>Disbursement Type</span>
                            </h5>

                            <div class="mb-3">
                                <select name="type" id="cvType" class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="">Select Type</option>
                                    <option value="pcf_replenishment" {{ old('type', $replenishPcvs->isNotEmpty() ? 'pcf_replenishment' : '') === 'pcf_replenishment' ? 'selected' : '' }}>Petty Cash Fund Replenishment</option>
                                    <option value="apv_payment" {{ old('type', $payApv ? 'apv_payment' : '') === 'apv_payment' ? 'selected' : '' }}>Pay an Unpaid APV</option>
                                    <option value="service_payment" {{ old('type', $payService ? 'service_payment' : '') === 'service_payment' ? 'selected' : '' }}>Pay an Unpaid Service</option>
                                    <option value="cod_purchase" {{ old('type') === 'cod_purchase' ? 'selected' : '' }}>COD Purchase</option>
                                    <option value="advance" {{ old('type') === 'advance' ? 'selected' : '' }}>Advance</option>
                                    <option value="other_disbursement" {{ old('type') === 'other_disbursement' ? 'selected' : '' }}>Other Disbursement</option>
                                </select>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', now()->toDateString()) }}" required>
                                    @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">CV # <span class="text-danger">*</span></label>
                                    <input type="text" name="cv_no" class="form-control @error('cv_no') is-invalid @enderror" value="{{ old('cv_no') }}" required>
                                    @error('cv_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @if(session('duplicate_cv'))
                                    <div class="small mt-1">
                                        Adding another receipt to this same payment?
                                        <a href="{{ route('check-vouchers.show', session('duplicate_cv')) }}" class="fw-semibold">Open CV {{ session('duplicate_cv')->cv_no }} and use "Add Receipt" there →</a>
                                    </div>
                                    @endif
                                </div>
                                <div class="col-md-4 cv-type-section" data-type="pcf_replenishment,apv_payment,">
                                    <label class="form-label fw-semibold">SI #</label>
                                    <input type="text" name="si_no" class="form-control" value="{{ old('si_no') }}">
                                </div>
                                @include('finance._branch-picker', [
                                    'voucher' => null,
                                    'colWidth' => 4,
                                    'pickerRequired' => false,
                                    'pickerHint' => 'Used for direct disbursements — APV payments and PCV replenishments inherit the branch of what they pay.',
                                ])
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Particulars <span class="text-danger">*</span></label>
                                <input type="text" name="particulars" class="form-control @error('particulars') is-invalid @enderror" value="{{ old('particulars') }}" required>
                                @error('particulars')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Bank Account</label>
                                    <select name="bank_account_id" class="form-select @error('bank_account_id') is-invalid @enderror">
                                        <option value="">Select Bank Account</option>
                                        @foreach($bankAccounts as $bankAccount)
                                        <option value="{{ $bankAccount->id }}" {{ (string) old('bank_account_id') === (string) $bankAccount->id ? 'selected' : '' }}>{{ $bankAccount->bank_name }} — {{ $bankAccount->account_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('bank_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                        @foreach(['cash' => 'Cash', 'check' => 'Check', 'bank_transfer' => 'Bank Transfer', 'online' => 'Online'] as $value => $label)
                                        <option value="{{ $value }}" {{ (string) old('payment_method', 'check') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Vendor / Supplier</label>
                                @include('partials.supplier-picker', [
                                    'name' => 'supplier_id',
                                    'id' => 'cvSupplier',
                                    'suppliers' => $suppliers,
                                    'selected' => old('supplier_id', $payApv?->vendor_id),
                                    'placeholder' => 'Search vendor / supplier...',
                                    'emptyLabel' => 'Select Supplier (optional — fills payee details below)',
                                    'selectClass' => (($errors ?? null)?->has('supplier_id')) ? 'is-invalid' : '',
                                    'optionAttrs' => fn ($supplier) => 'data-name="'.e($supplier->name).'" data-address="'.e($supplier->address).'" data-tin="'.e($supplier->tin).'"',
                                ])
                                @error('supplier_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payee <span class="text-danger">*</span></label>
                                    <input type="text" name="payee_name" id="payeeName" class="form-control @error('payee_name') is-invalid @enderror" value="{{ old('payee_name', $payApv?->vendor?->name) }}" required>
                                    @error('payee_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Address</label>
                                    <input type="text" name="address" id="payeeAddress" class="form-control" value="{{ old('address', $payApv?->vendor?->address) }}">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">TIN</label>
                                <input type="text" name="tin" id="payeeTin" class="form-control" value="{{ old('tin', $payApv?->vendor?->tin) }}" style="max-width: 260px;">
                            </div>

                            {{-- PCF Replenishment section --}}
                            <div class="cv-type-section" data-type="pcf_replenishment" style="display:none;">
                                <h6 class="fw-bold text-primary">Petty Cash Vouchers to Replenish</h6>
                                <p class="small text-muted">Select the un-replenished PCVs this check will reimburse. The CV amount must equal their combined sub-total.</p>
                                <div id="pcvChecklist" class="border rounded p-2 mb-3" style="max-height: 260px; overflow-y: auto;">
                                    <div class="text-muted small">Loading petty cash vouchers…</div>
                                </div>
                            </div>

                            {{-- APV Payment section --}}
                            <div class="cv-type-section" data-type="apv_payment" style="display:none;">
                                <h6 class="fw-bold text-primary">Purchase Voucher to Pay</h6>
                                <div class="mb-2">
                                    <input type="text" id="apvSearch" class="form-control" placeholder="Search unpaid APV # or vendor name..." value="{{ $payApv?->apv_no }}">
                                </div>
                                <div id="apvResults" class="border rounded p-2 mb-3" style="max-height: 220px; overflow-y: auto;"></div>
                                <div id="selectedApvInfo" class="alert alert-info small {{ $payApv ? '' : 'd-none' }}">
                                    @if($payApv)
                                        Paying APV <strong>{{ $payApv->apv_no }}</strong> — Remaining balance: <strong>₱{{ number_format($payApv->payable_total - $payApv->amount_paid, 2) }}</strong>
                                    @endif
                                </div>
                                <input type="hidden" name="purchase_voucher_id" id="purchaseVoucherId" value="{{ old('purchase_voucher_id', $payApv?->id) }}">
                            </div>

                            {{-- Service Payment section --}}
                            <div class="cv-type-section" data-type="service_payment" style="display:none;">
                                <h6 class="fw-bold text-primary">Service to Pay</h6>
                                <div class="mb-2">
                                    <input type="text" id="serviceSearch" class="form-control" placeholder="Search unpaid Service Ref # or supplier name..." value="{{ $payService?->ref_no }}">
                                </div>
                                <div id="serviceResults" class="border rounded p-2 mb-3" style="max-height: 220px; overflow-y: auto;"></div>
                                <div id="selectedServiceInfo" class="alert alert-info small {{ $payService ? '' : 'd-none' }}">
                                    @if($payService)
                                        Paying Service <strong>{{ $payService->ref_no }}</strong> — Remaining balance: <strong>₱{{ number_format($payService->payable_total - $payService->amount_paid, 2) }}</strong>
                                    @endif
                                </div>
                                <input type="hidden" name="service_id" id="serviceId" value="{{ old('service_id', $payService?->id) }}">
                            </div>

                            {{-- Advance section --}}
                            <div class="cv-type-section" data-type="advance" style="display:none;">
                                <h6 class="fw-bold text-primary">Advance Details</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Advance Account <span class="text-danger">*</span></label>
                                    <select name="advance_account_id" class="form-select">
                                        <option value="">Select Account</option>
                                        @foreach($advanceAccounts as $account)
                                        <option value="{{ $account->id }}" {{ (string) old('advance_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }} ({{ $account->type === 'debit_asset' ? 'Advances Asset' : 'Accounts Payable' }})</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text text-muted">Pick an Accounts Payable account when advancing a person (e.g. Ate Dinah) against future purchases, or an Advances (asset) account for a general cash advance (e.g. Advances – KDs).</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold d-block">VAT</label>
                                    <div class="btn-group" role="group" aria-label="Advance VAT type">
                                        <input type="radio" class="btn-check" name="advance_vat_type" id="advanceVatWithout" value="without_vat" autocomplete="off" {{ old('advance_vat_type', 'without_vat') === 'without_vat' ? 'checked' : '' }}>
                                        <label class="btn btn-outline-primary" for="advanceVatWithout">Without VAT</label>

                                        <input type="radio" class="btn-check" name="advance_vat_type" id="advanceVatWith" value="with_vat" autocomplete="off" {{ old('advance_vat_type') === 'with_vat' ? 'checked' : '' }}>
                                        <label class="btn btn-outline-primary" for="advanceVatWith">With VAT</label>
                                    </div>
                                    <div class="form-text text-muted">"With VAT" treats the amount below as VAT-inclusive and splits it into Net Purchases + VAT (12%). "Without VAT" records the full amount with no VAT component — use this for plain cash advances.</div>
                                </div>
                            </div>

                            {{-- Standalone (COD / Other) section --}}
                            <div class="cv-type-section" data-type="cod_purchase,other_disbursement" style="display:none;">
                                <h6 class="fw-bold text-primary">Purchase / VAT Detail</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Default Cost Account <span class="text-danger">*</span></label>
                                    <select name="cost_account_id" id="defaultCostAccount" class="form-select">
                                        <option value="">Select Account</option>
                                        @foreach($costAccounts as $account)
                                        <option value="{{ $account->id }}" {{ (string) old('cost_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text text-muted">Pre-fills each new receipt row below — change a row's own Cost Account to split this CV across more than one Chart of Accounts entry.</div>
                                </div>

                                <label class="form-label fw-semibold d-flex align-items-center justify-content-between">
                                    <span>Receipts / Invoices <span class="text-danger">*</span></span>
                                    <button type="button" id="addReceiptRow" class="btn btn-sm btn-outline-primary">
                                        <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Receipt
                                    </button>
                                </label>
                                <p class="small text-muted mb-2">One payment often covers several supplier receipts — add a row per receipt/invoice, each with its own Cost Account. The CV total is computed automatically from all rows.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-1" id="receiptsTable">
                                        <thead>
                                            <tr>
                                                <th style="min-width:150px;">SI / Receipt #</th>
                                                <th style="min-width:180px;">Supplier</th>
                                                <th style="min-width:190px;">Cost Account</th>
                                                <th style="min-width:130px;">Amount w/ VAT</th>
                                                <th style="min-width:130px;">VAT-Exempt</th>
                                                <th style="min-width:130px;">Non-VAT</th>
                                                <th style="width:110px;" class="text-end">Total</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="receiptsTableBody"></tbody>
                                        <tfoot>
                                            <tr class="table-light fw-bold">
                                                <td colspan="3" class="text-end">Sub-Total</td>
                                                <td id="receiptTotalAmountWVat" class="text-end">₱0.00</td>
                                                <td id="receiptTotalExempt" class="text-end">₱0.00</td>
                                                <td id="receiptTotalNonVat" class="text-end">₱0.00</td>
                                                <td id="receiptTotalGrand" class="text-end">₱0.00</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="small text-muted mt-1" id="standalonePreview">—</div>
                                <template id="receiptRowTemplate">
                                    <tr class="receipt-row">
                                        <td><input type="text" name="receipts[__INDEX__][si_no]" class="form-control form-control-sm receipt-si-no"></td>
                                        <td>
                                            @include('partials.supplier-picker', [
                                                'name' => 'receipts[__INDEX__][supplier_id]',
                                                'suppliers' => $suppliers,
                                                'placeholder' => 'Search supplier...',
                                                'searchClass' => 'form-control-sm',
                                                'selectClass' => 'form-select-sm receipt-supplier',
                                            ])
                                        </td>
                                        <td>
                                            <select name="receipts[__INDEX__][cost_account_id]" class="form-select form-select-sm receipt-cost-account">
                                                <option value="">Select Account</option>
                                                @foreach($costAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="number" step="0.01" min="0" name="receipts[__INDEX__][amount_w_vat]" class="form-control form-control-sm receipt-amount"></td>
                                        <td><input type="number" step="0.01" min="0" name="receipts[__INDEX__][vat_exempt]" class="form-control form-control-sm receipt-exempt"></td>
                                        <td><input type="number" step="0.01" min="0" name="receipts[__INDEX__][non_vat_purchase]" class="form-control form-control-sm receipt-nonvat"></td>
                                        <td class="text-end receipt-row-total text-nowrap">₱0.00</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger removeReceiptRow"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                                        </td>
                                    </tr>
                                </template>
                            </div>

                            {{-- Amount field shared by PCF replenishment / APV payment / Service payment / Advance (standalone types use their own amount above) --}}
                            <div class="cv-type-section" data-type="pcf_replenishment,apv_payment,service_payment,advance" style="display:none;">
                                <div class="mb-3" style="max-width: 280px;">
                                    <label class="form-label fw-semibold" id="linkedAmountLabel">Amount w/ VAT <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" name="amount_w_vat" id="linkedAmount" class="form-control @error('amount_w_vat') is-invalid @enderror" value="{{ old('amount_w_vat', $payApv ? round($payApv->payable_total - $payApv->amount_paid, 2) : ($payService ? round($payService->payable_total - $payService->amount_paid, 2) : '')) }}">
                                    @error('amount_w_vat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            {{-- Advance amount summary: recomputed live from the amount above + the VAT toggle --}}
                            <div class="cv-type-section" data-type="advance" style="display:none;">
                                <div class="card bg-light border-0" style="max-width: 340px;">
                                    <div class="card-body py-3">
                                        <div class="small text-muted mb-2 fw-semibold">Advance Summary</div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">Net Amount</span>
                                            <span id="advanceNetAmount" class="fw-semibold">₱0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">VAT (12%)</span>
                                            <span id="advanceVatAmount" class="fw-semibold">₱0.00</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-semibold">Total Amount</span>
                                            <span id="advanceTotalAmount" class="fw-bold">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card p-4 shadow-sm h-100">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                                <i data-lucide="percent" style="width: 20px; height: 20px;"></i>
                                <span>EWT</span>
                            </h5>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">EWT Rate</label>
                                <select name="ewt_rate" class="form-select">
                                    <option value="0" {{ old('ewt_rate', '0') === '0' ? 'selected' : '' }}>None</option>
                                    <option value="0.01" {{ old('ewt_rate') === '0.01' ? 'selected' : '' }}>1%</option>
                                    <option value="0.02" {{ old('ewt_rate') === '0.02' ? 'selected' : '' }}>2%</option>
                                    <option value="0.05" {{ old('ewt_rate') === '0.05' ? 'selected' : '' }}>5%</option>
                                    <option value="0.10" {{ old('ewt_rate') === '0.10' ? 'selected' : '' }}>10%</option>
                                </select>
                                <div class="form-text text-muted">Withheld from the amount paid to the payee and remitted to the BIR.</div>
                            </div>
                            <div class="flex-grow-1 mb-3">
                                <label class="form-label fw-semibold">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="6">{{ old('remarks') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 mt-4">
                <a href="{{ route('check-vouchers.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                    <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                    <span>Save Check Voucher</span>
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/supplier-picker.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('cvType');
    const sections = document.querySelectorAll('.cv-type-section');
    const prefilledPcvs = @json($prefilledPcvsData);
    const unreplenishedUrl = @json(route('check-vouchers.unreplenished-pcvs'));
    const unpaidApvsUrl = @json(route('check-vouchers.unpaid-apvs'));
    const unpaidServicesUrl = @json(route('check-vouchers.unpaid-services'));

    function toggleSections() {
        const type = typeSelect.value;
        sections.forEach(function (section) {
            const types = section.dataset.type.split(',');
            section.style.display = types.includes(type) ? '' : 'none';
        });
    }
    typeSelect.addEventListener('change', toggleSections);
    toggleSections();

    // --- Advance: VAT toggle drives the shared amount label + live summary box ---
    const linkedAmountLabel = document.getElementById('linkedAmountLabel');
    const advanceVatRadios = document.querySelectorAll('input[name="advance_vat_type"]');
    const advanceNetAmount = document.getElementById('advanceNetAmount');
    const advanceVatAmount = document.getElementById('advanceVatAmount');
    const advanceTotalAmount = document.getElementById('advanceTotalAmount');

    function isAdvanceWithVat() {
        const checked = document.querySelector('input[name="advance_vat_type"]:checked');
        return checked ? checked.value === 'with_vat' : false;
    }

    function updateAdvanceAmountUi() {
        if (typeSelect.value !== 'advance') {
            if (linkedAmountLabel) linkedAmountLabel.innerHTML = 'Amount w/ VAT <span class="text-danger">*</span>';
            return;
        }

        const withVat = isAdvanceWithVat();
        if (linkedAmountLabel) {
            linkedAmountLabel.innerHTML = (withVat ? 'Amount w/ VAT' : 'Amount') + ' <span class="text-danger">*</span>';
        }

        const amount = parseFloat(document.getElementById('linkedAmount')?.value) || 0;
        // Without VAT: the amount stands as-is, nothing to deduct.
        // With VAT: the amount is VAT-inclusive, so it's split into Net + 12% VAT.
        let net = amount;
        let vat = 0;
        if (withVat) {
            net = Math.round((amount / 1.12) * 100) / 100;
            vat = Math.round((amount - net) * 100) / 100;
        }

        if (advanceNetAmount) advanceNetAmount.textContent = '₱' + net.toFixed(2);
        if (advanceVatAmount) advanceVatAmount.textContent = '₱' + vat.toFixed(2);
        if (advanceTotalAmount) advanceTotalAmount.textContent = '₱' + amount.toFixed(2);
    }

    advanceVatRadios.forEach(function (radio) {
        radio.addEventListener('change', updateAdvanceAmountUi);
    });
    typeSelect.addEventListener('change', updateAdvanceAmountUi);
    updateAdvanceAmountUi();

    // --- PCF replenishment checklist ---
    const pcvChecklist = document.getElementById('pcvChecklist');
    const linkedAmount = document.getElementById('linkedAmount');
    linkedAmount?.addEventListener('input', updateAdvanceAmountUi);

    function renderPcvChecklist(pcvs) {
        if (!pcvs.length) {
            pcvChecklist.innerHTML = '<div class="text-muted small">No un-replenished petty cash vouchers found.</div>';
            return;
        }
        pcvChecklist.innerHTML = pcvs.map(function (pcv) {
            const checked = prefilledPcvs.some(p => p.id === pcv.id) ? 'checked' : '';
            return '<div class="form-check">' +
                '<input class="form-check-input pcv-checkbox" type="checkbox" name="petty_cash_voucher_ids[]" value="' + pcv.id + '" data-total="' + pcv.total + '" id="pcv-' + pcv.id + '" ' + checked + '>' +
                '<label class="form-check-label" for="pcv-' + pcv.id + '">' + pcv.pcv_no + ' (' + pcv.date + ') — ₱' + Number(pcv.total).toFixed(2) + '</label>' +
                '</div>';
        }).join('');
        pcvChecklist.querySelectorAll('.pcv-checkbox').forEach(function (checkbox) {
            checkbox.addEventListener('change', updatePcvTotal);
        });
        updatePcvTotal();
    }

    function updatePcvTotal() {
        let total = 0;
        pcvChecklist.querySelectorAll('.pcv-checkbox:checked').forEach(function (checkbox) {
            total += parseFloat(checkbox.dataset.total || 0);
        });
        if (linkedAmount && typeSelect.value === 'pcf_replenishment') {
            linkedAmount.value = total.toFixed(2);
        }
    }

    fetch(unreplenishedUrl, { headers: { Accept: 'application/json' } })
        .then(res => res.json())
        .then(renderPcvChecklist)
        .catch(() => { pcvChecklist.innerHTML = '<div class="text-danger small">Failed to load petty cash vouchers.</div>'; });

    // --- APV search ---
    const apvSearch = document.getElementById('apvSearch');
    const apvResults = document.getElementById('apvResults');
    const purchaseVoucherId = document.getElementById('purchaseVoucherId');
    const selectedApvInfo = document.getElementById('selectedApvInfo');
    const payeeName = document.getElementById('payeeName');
    const payeeAddress = document.getElementById('payeeAddress');
    const payeeTin = document.getElementById('payeeTin');
    let apvSearchTimer = null;

    function renderApvResults(apvs) {
        if (!apvs.length) {
            apvResults.innerHTML = '<div class="text-muted small">No unpaid APVs found for the selected branch. Create a Purchase Voucher first, or switch branches if the APV belongs elsewhere.</div>';
            return;
        }
        apvResults.innerHTML = apvs.map(function (apv) {
            return '<button type="button" class="btn btn-sm btn-outline-secondary w-100 text-start mb-1 apv-result" ' +
                'data-id="' + apv.id + '" data-vendor="' + (apv.vendor_name || '') + '" data-address="' + (apv.vendor_address || '') + '" data-tin="' + (apv.vendor_tin || '') + '" data-balance="' + apv.remaining_balance + '" data-apv-no="' + apv.apv_no + '">' +
                apv.apv_no + ' — ' + (apv.vendor_name || 'No vendor') + ' — Balance ₱' + Number(apv.remaining_balance).toFixed(2) +
                '</button>';
        }).join('');
        apvResults.querySelectorAll('.apv-result').forEach(function (button) {
            button.addEventListener('click', function () {
                purchaseVoucherId.value = this.dataset.id;
                payeeName.value = this.dataset.vendor;
                payeeAddress.value = this.dataset.address;
                payeeTin.value = this.dataset.tin;
                if (linkedAmount) linkedAmount.value = parseFloat(this.dataset.balance).toFixed(2);
                selectedApvInfo.textContent = 'Paying APV ' + this.dataset.apvNo + ' — Remaining balance: ₱' + Number(this.dataset.balance).toFixed(2);
                selectedApvInfo.classList.remove('d-none');
                apvResults.innerHTML = '';
            });
        });
    }

    function searchApvs(term) {
        fetch(unpaidApvsUrl + '?search=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } })
            .then(res => res.json())
            .then(renderApvResults)
            .catch(() => { apvResults.innerHTML = '<div class="text-danger small">Search failed.</div>'; });
    }

    if (apvSearch) {
        apvSearch.addEventListener('input', function () {
            clearTimeout(apvSearchTimer);
            const term = this.value;
            apvSearchTimer = setTimeout(function () { searchApvs(term); }, 300);
        });
        if (typeSelect.value === 'apv_payment') searchApvs(apvSearch.value);
        typeSelect.addEventListener('change', function () {
            if (this.value === 'apv_payment' && apvResults.innerHTML === '') searchApvs(apvSearch.value);
        });
    }

    // --- Service search ---
    const serviceSearch = document.getElementById('serviceSearch');
    const serviceResults = document.getElementById('serviceResults');
    const serviceId = document.getElementById('serviceId');
    const selectedServiceInfo = document.getElementById('selectedServiceInfo');
    let serviceSearchTimer = null;

    function renderServiceResults(services) {
        if (!services.length) {
            serviceResults.innerHTML = '<div class="text-muted small">No unpaid Services found for the selected branch.</div>';
            return;
        }
        serviceResults.innerHTML = services.map(function (service) {
            return '<button type="button" class="btn btn-sm btn-outline-secondary w-100 text-start mb-1 service-result" ' +
                'data-id="' + service.id + '" data-supplier="' + (service.supplier_name || service.payor || '') + '" data-balance="' + service.remaining_balance + '" data-ref-no="' + service.ref_no + '">' +
                service.ref_no + ' — ' + (service.supplier_name || service.payor || 'No supplier') + ' — Balance ₱' + Number(service.remaining_balance).toFixed(2) +
                '</button>';
        }).join('');
        serviceResults.querySelectorAll('.service-result').forEach(function (button) {
            button.addEventListener('click', function () {
                serviceId.value = this.dataset.id;
                payeeName.value = this.dataset.supplier;
                if (linkedAmount) linkedAmount.value = parseFloat(this.dataset.balance).toFixed(2);
                selectedServiceInfo.textContent = 'Paying Service ' + this.dataset.refNo + ' — Remaining balance: ₱' + Number(this.dataset.balance).toFixed(2);
                selectedServiceInfo.classList.remove('d-none');
                serviceResults.innerHTML = '';
            });
        });
    }

    function searchServices(term) {
        fetch(unpaidServicesUrl + '?search=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } })
            .then(res => res.json())
            .then(renderServiceResults)
            .catch(() => { serviceResults.innerHTML = '<div class="text-danger small">Search failed.</div>'; });
    }

    if (serviceSearch) {
        serviceSearch.addEventListener('input', function () {
            clearTimeout(serviceSearchTimer);
            const term = this.value;
            serviceSearchTimer = setTimeout(function () { searchServices(term); }, 300);
        });
        if (typeSelect.value === 'service_payment') searchServices(serviceSearch.value);
        typeSelect.addEventListener('change', function () {
            if (this.value === 'service_payment' && serviceResults.innerHTML === '') searchServices(serviceSearch.value);
        });
    }

    // --- Supplier autofill: payee details come from the Suppliers table ---
    const cvSupplier = document.getElementById('cvSupplier');
    if (cvSupplier) {
        cvSupplier.addEventListener('change', function () {
            const option = this.selectedOptions[0];
            if (!option || !option.value) return;
            payeeName.value = option.dataset.name || '';
            payeeAddress.value = option.dataset.address || '';
            payeeTin.value = option.dataset.tin || '';
        });
    }

    // --- Standalone (COD/Other) receipts: add / edit / remove multiple receipt rows ---
    const receiptsTableBody = document.getElementById('receiptsTableBody');
    const receiptRowTemplate = document.getElementById('receiptRowTemplate');
    const addReceiptRowBtn = document.getElementById('addReceiptRow');
    const defaultCostAccount = document.getElementById('defaultCostAccount');
    const standalonePreview = document.getElementById('standalonePreview');
    const oldReceipts = @json(old('receipts', []));
    let receiptRowIndex = 0;

    function money(value) {
        return '₱' + Number(value || 0).toFixed(2);
    }

    function addReceiptRow(data) {
        data = data || {};
        const html = receiptRowTemplate.innerHTML.replace(/__INDEX__/g, receiptRowIndex++);
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;

        row.querySelector('.receipt-si-no').value = data.si_no || '';
        row.querySelector('.receipt-supplier').value = data.supplier_id || '';
        // Pre-fill from whatever this receipt already had (re-displaying after a
        // validation error), otherwise fall back to the CV's Default Cost Account
        // so most receipts need zero extra clicks — override per row to split.
        row.querySelector('.receipt-cost-account').value = data.cost_account_id || defaultCostAccount?.value || '';
        row.querySelector('.receipt-amount').value = data.amount_w_vat || '';
        row.querySelector('.receipt-exempt').value = data.vat_exempt || '';
        row.querySelector('.receipt-nonvat').value = data.non_vat_purchase || '';

        row.querySelectorAll('input').forEach(function (input) {
            input.addEventListener('input', updateReceiptTotals);
        });
        row.querySelector('.removeReceiptRow').addEventListener('click', function () {
            if (receiptsTableBody.querySelectorAll('.receipt-row').length > 1) {
                row.remove();
                updateReceiptTotals();
            }
        });

        receiptsTableBody.appendChild(row);
        if (window.SupplierPicker) window.SupplierPicker.enhance(row.querySelector('.supplier-combo'));
        if (window.lucide) window.lucide.createIcons();
        updateReceiptTotals();
    }

    function updateReceiptTotals() {
        let totalAmount = 0, totalExempt = 0, totalNonVat = 0;
        receiptsTableBody.querySelectorAll('.receipt-row').forEach(function (row) {
            const amount = parseFloat(row.querySelector('.receipt-amount').value || 0) || 0;
            const exempt = parseFloat(row.querySelector('.receipt-exempt').value || 0) || 0;
            const nonVat = parseFloat(row.querySelector('.receipt-nonvat').value || 0) || 0;
            row.querySelector('.receipt-row-total').textContent = money(amount + exempt + nonVat);
            totalAmount += amount;
            totalExempt += exempt;
            totalNonVat += nonVat;
        });

        document.getElementById('receiptTotalAmountWVat').textContent = money(totalAmount);
        document.getElementById('receiptTotalExempt').textContent = money(totalExempt);
        document.getElementById('receiptTotalNonVat').textContent = money(totalNonVat);
        document.getElementById('receiptTotalGrand').textContent = money(totalAmount + totalExempt + totalNonVat);

        const net = totalAmount / 1.12;
        const vat = totalAmount - net;
        standalonePreview.textContent = 'Net ₱' + net.toFixed(2) + ' · VAT ₱' + vat.toFixed(2) + ' · CV Total ₱' + (net + vat + totalExempt + totalNonVat).toFixed(2);
    }

    addReceiptRowBtn.addEventListener('click', function () { addReceiptRow(); });

    if (oldReceipts && oldReceipts.length) {
        oldReceipts.forEach(function (r) { addReceiptRow(r); });
    } else {
        addReceiptRow();
    }
});
</script>
@endpush
