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
                                    <option value="cod_purchase" {{ old('type') === 'cod_purchase' ? 'selected' : '' }}>COD Purchase</option>
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
                                </div>
                                <div class="col-md-4">
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

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Vendor / Supplier</label>
                                <select name="supplier_id" id="cvSupplier" class="form-select @error('supplier_id') is-invalid @enderror">
                                    <option value="">Select Supplier (optional — fills payee details below)</option>
                                    @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}"
                                        data-name="{{ $supplier->name }}"
                                        data-address="{{ $supplier->address }}"
                                        data-tin="{{ $supplier->tin }}"
                                        @selected((string) old('supplier_id', $payApv?->vendor_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

                            {{-- Standalone (COD / Other) section --}}
                            <div class="cv-type-section" data-type="cod_purchase,other_disbursement" style="display:none;">
                                <h6 class="fw-bold text-primary">Purchase / VAT Detail</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Cost Account <span class="text-danger">*</span></label>
                                    <select name="cost_account_id" class="form-select">
                                        <option value="">Select Account</option>
                                        @foreach($costAccounts as $account)
                                        <option value="{{ $account->id }}" {{ (string) old('cost_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Amount w/ VAT</label>
                                        <input type="number" step="0.01" min="0" name="amount_w_vat" id="standaloneAmount" class="form-control" value="{{ old('amount_w_vat') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">VAT-Exempt</label>
                                        <input type="number" step="0.01" min="0" name="vat_exempt" id="standaloneExempt" class="form-control" value="{{ old('vat_exempt') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Non-VAT</label>
                                        <input type="number" step="0.01" min="0" name="non_vat_purchase" id="standaloneNonVat" class="form-control" value="{{ old('non_vat_purchase') }}">
                                    </div>
                                </div>
                                <div class="small text-muted mt-2" id="standalonePreview">—</div>
                            </div>

                            {{-- Amount field shared by PCF replenishment / APV payment (standalone types use their own amount above) --}}
                            <div class="cv-type-section" data-type="pcf_replenishment,apv_payment" style="display:none;">
                                <div class="mb-3" style="max-width: 280px;">
                                    <label class="form-label fw-semibold">Amount w/ VAT <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" name="amount_w_vat" id="linkedAmount" class="form-control @error('amount_w_vat') is-invalid @enderror" value="{{ old('amount_w_vat', $payApv ? round($payApv->payable_total - $payApv->amount_paid, 2) : '') }}">
                                    @error('amount_w_vat')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('cvType');
    const sections = document.querySelectorAll('.cv-type-section');
    const prefilledPcvs = @json($prefilledPcvsData);
    const unreplenishedUrl = @json(route('check-vouchers.unreplenished-pcvs'));
    const unpaidApvsUrl = @json(route('check-vouchers.unpaid-apvs'));

    function toggleSections() {
        const type = typeSelect.value;
        sections.forEach(function (section) {
            const types = section.dataset.type.split(',');
            section.style.display = types.includes(type) ? '' : 'none';
        });
    }
    typeSelect.addEventListener('change', toggleSections);
    toggleSections();

    // --- PCF replenishment checklist ---
    const pcvChecklist = document.getElementById('pcvChecklist');
    const linkedAmount = document.getElementById('linkedAmount');

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

    // --- Standalone (COD/Other) VAT preview ---
    const standaloneAmount = document.getElementById('standaloneAmount');
    const standaloneExempt = document.getElementById('standaloneExempt');
    const standaloneNonVat = document.getElementById('standaloneNonVat');
    const standalonePreview = document.getElementById('standalonePreview');

    function updateStandalonePreview() {
        const amount = parseFloat(standaloneAmount.value || 0) || 0;
        const exempt = parseFloat(standaloneExempt.value || 0) || 0;
        const nonVat = parseFloat(standaloneNonVat.value || 0) || 0;
        const net = amount / 1.12;
        const vat = amount - net;
        standalonePreview.textContent = 'Net ₱' + net.toFixed(2) + ' · VAT ₱' + vat.toFixed(2) + ' · Total ₱' + (net + exempt + nonVat).toFixed(2);
    }
    [standaloneAmount, standaloneExempt, standaloneNonVat].forEach(function (input) {
        if (input) input.addEventListener('input', updateStandalonePreview);
    });
    updateStandalonePreview();
});
</script>
@endpush
