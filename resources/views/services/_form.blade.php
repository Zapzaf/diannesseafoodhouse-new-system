<div class="row g-4">
    <div class="col-lg-8">
        <div class="card p-4 shadow-sm h-100">
            <div class="card-body p-0">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="info" style="width: 20px; height: 20px;"></i>
                    <span>Service Details</span>
                </h5>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $service?->date?->toDateString()) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Ref # <span class="text-muted fw-normal">(auto-generated)</span></label>
                        <input type="text" class="form-control" value="{{ $service?->ref_no ?? ($suggestedSerNo ?? '') }}" readonly disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">SI / Invoice #</label>
                        <input type="text" name="si_no" class="form-control @error('si_no') is-invalid @enderror" value="{{ old('si_no', $service?->si_no) }}">
                        @error('si_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-check mt-1">
                            <input type="checkbox" name="allow_duplicate_invoice" value="1" class="form-check-input" id="allowDuplicateInvoice" {{ old('allow_duplicate_invoice') ? 'checked' : '' }}>
                            <label class="form-check-label small text-muted" for="allowDuplicateInvoice">Allow duplicate invoice # for this supplier</label>
                        </div>
                    </div>
                    @include('finance._branch-picker', ['voucher' => $service, 'colWidth' => 4])
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                        {{-- No HTML `required` here: the underlying <select> this widget
                            drives is permanently d-none (a visible search box stands in
                            for it), and this form has no `novalidate` — a required-but-
                            invisible field silently blocks submit in every browser if the
                            admin hasn't picked a supplier yet. Server-side validation
                            already enforces this and surfaces the error below. --}}
                        @include('partials.supplier-picker', [
                            'name' => 'supplier_id',
                            'suppliers' => $suppliers,
                            'selected' => old('supplier_id', $service?->supplier_id),
                            'selectClass' => (($errors ?? null)?->has('supplier_id')) ? 'is-invalid' : '',
                        ])
                        <div class="form-text text-muted">The company providing the service, e.g. PLDT, Meralco.</div>
                        @error('supplier_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payor <span class="text-danger">*</span></label>
                        <input type="text" name="payor" class="form-control @error('payor') is-invalid @enderror" value="{{ old('payor', $service?->payor) }}" placeholder="e.g. Ate Dinah" required>
                        <div class="form-text text-muted">The person who authorized/processed this payment — distinct from the Supplier above.</div>
                        @error('payor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Service Type / Expense Account <span class="text-danger">*</span></label>
                        <select name="expense_account_id" class="form-select @error('expense_account_id') is-invalid @enderror" required>
                            <option value="">Select Account</option>
                            @foreach($expenseAccounts as $account)
                            <option value="{{ $account->id }}" {{ (string) old('expense_account_id', $service?->expense_account_id) === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted">e.g. Electricity, Water, Internet, Professional Fees. Add new accounts via <a href="{{ route('chart-of-accounts.create') }}" target="_blank">Chart of Accounts</a>.</div>
                        @error('expense_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payment Type <span class="text-danger">*</span></label>
                        <select name="service_payment_type" id="servicePaymentType" class="form-select @error('service_payment_type') is-invalid @enderror" required>
                            <option value="credit" {{ old('service_payment_type', $service?->service_payment_type ?? 'credit') === 'credit' ? 'selected' : '' }}>Credit (bill received, not yet paid)</option>
                            <option value="immediate" {{ old('service_payment_type', $service?->service_payment_type) === 'immediate' ? 'selected' : '' }}>Immediate (paid now)</option>
                        </select>
                        @error('service_payment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-3" id="servicePaymentWrapper">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Bank Account <span class="text-danger">*</span></label>
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
                        <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                            @foreach(['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'online' => 'Online'] as $value => $label)
                            <option value="{{ $value }}" {{ (string) old('payment_method', 'cash') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h5 class="fw-bold mb-3 mt-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="banknote" style="width: 20px; height: 20px;"></i>
                    <span>Amount</span>
                </h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Amount w/ VAT</label>
                        <input type="number" step="0.01" min="0" name="amount_w_vat" class="form-control @error('amount_w_vat') is-invalid @enderror" value="{{ old('amount_w_vat', $service?->amount_w_vat) }}">
                        @error('amount_w_vat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">VAT-Exempt</label>
                        <input type="number" step="0.01" min="0" name="vat_exempt" class="form-control @error('vat_exempt') is-invalid @enderror" value="{{ old('vat_exempt', $service?->vat_exempt) }}">
                        @error('vat_exempt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Non-VAT</label>
                        <input type="number" step="0.01" min="0" name="non_vat_purchase" class="form-control @error('non_vat_purchase') is-invalid @enderror" value="{{ old('non_vat_purchase', $service?->non_vat_purchase) }}">
                        @error('non_vat_purchase')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4 shadow-sm h-100">
            <div class="card-body p-0 d-flex flex-column h-100">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
                    <span>Remarks</span>
                </h5>
                <div class="flex-grow-1 mb-3">
                    <textarea name="remarks" class="form-control" rows="10">{{ old('remarks', $service?->remarks) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-3 mt-4">
    <a href="{{ route('services.index') }}" class="btn btn-secondary text-white px-4">Cancel</a>
    <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
        <i data-lucide="save" style="width: 18px; height: 18px;"></i>
        <span>Save Service</span>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentType = document.getElementById('servicePaymentType');
    const wrapper = document.getElementById('servicePaymentWrapper');
    const selects = wrapper.querySelectorAll('select');

    function refresh() {
        const isImmediate = paymentType.value === 'immediate';
        wrapper.style.display = isImmediate ? '' : 'none';
        selects.forEach(function (select) { select.required = isImmediate; });
    }

    paymentType.addEventListener('change', refresh);
    refresh();
});
</script>
