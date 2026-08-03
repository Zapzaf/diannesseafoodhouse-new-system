@php
    $items = old('items', $voucher?->items->map(fn ($item) => [
        'quantity' => $item->quantity,
        'unit' => $item->unit,
        'particulars' => $item->particulars,
        'cost_account_id' => $item->cost_account_id,
        'amount_w_vat' => $item->amount_w_vat,
        'vat_exempt' => $item->vat_exempt,
        'non_vat_purchase' => $item->non_vat_purchase,
        'remarks' => $item->remarks,
    ])->all() ?? []);
    if (empty($items)) {
        $items = [['quantity' => '', 'unit' => '', 'particulars' => '', 'cost_account_id' => '', 'amount_w_vat' => '', 'vat_exempt' => '', 'non_vat_purchase' => '', 'remarks' => '']];
    }
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card p-4 shadow-sm h-100">
            <div class="card-body p-0">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="info" style="width: 20px; height: 20px;"></i>
                    <span>Voucher Details</span>
                </h5>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $voucher?->date?->toDateString()) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">APV # <span class="text-muted fw-normal">(auto-generated)</span></label>
                        <input type="text" class="form-control" value="{{ $voucher?->apv_no ?? ($suggestedApvNo ?? '') }}" readonly disabled>
                        @error('apv_no')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">SI #</label>
                        <input type="text" name="si_no" class="form-control @error('si_no') is-invalid @enderror" value="{{ old('si_no', $voucher?->si_no) }}">
                        @error('si_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-check mt-1">
                            <input type="checkbox" name="allow_duplicate_invoice" value="1" class="form-check-input" id="allowDuplicateInvoice" {{ old('allow_duplicate_invoice') ? 'checked' : '' }}>
                            <label class="form-check-label small text-muted" for="allowDuplicateInvoice">Allow duplicate invoice # for this supplier</label>
                        </div>
                    </div>
                    @include('finance._branch-picker', ['voucher' => $voucher, 'colWidth' => 4])
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Purchase Type <span class="text-danger">*</span></label>
                        <select name="purchase_type" id="purchaseType" class="form-select @error('purchase_type') is-invalid @enderror" required>
                            <option value="credit" {{ old('purchase_type', $voucher?->purchase_type ?? 'credit') === 'credit' ? 'selected' : '' }}>Credit</option>
                            <option value="cod" {{ old('purchase_type', $voucher?->purchase_type) === 'cod' ? 'selected' : '' }}>Cash on Delivery (COD)</option>
                        </select>
                        @error('purchase_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Buyer <span class="text-danger">*</span></label>
                        <input type="text" name="buyer" class="form-control @error('buyer') is-invalid @enderror" value="{{ old('buyer', $voucher?->buyer) }}" placeholder="e.g. Ate Dinah" required>
                        <div class="form-text text-muted">The person who purchased the goods — distinct from the Vendor/Supplier below.</div>
                        @error('buyer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Vendor</label>
                        @include('partials.supplier-picker', [
                            'name' => 'vendor_id',
                            'id' => 'apvVendor',
                            'suppliers' => $vendors,
                            'selected' => old('vendor_id', $voucher?->vendor_id),
                            'placeholder' => 'Search vendor...',
                            'emptyLabel' => 'Select Vendor',
                            'selectClass' => (($errors ?? null)?->has('vendor_id')) ? 'is-invalid' : '',
                            'optionAttrs' => fn ($vendor) => 'data-address="'.e($vendor->address).'" data-tin="'.e($vendor->tin).'"',
                        ])
                        <div class="form-text" id="apvVendorInfo">Address and TIN come from the supplier record.</div>
                        @error('vendor_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const select = document.getElementById('apvVendor');
                            const info = document.getElementById('apvVendorInfo');
                            function refresh() {
                                const option = select.selectedOptions[0];
                                if (option && option.value) {
                                    const parts = [];
                                    if (option.dataset.address) parts.push(option.dataset.address);
                                    if (option.dataset.tin) parts.push('TIN: ' + option.dataset.tin);
                                    info.textContent = parts.length ? parts.join(' · ') : 'No address/TIN on the supplier record.';
                                } else {
                                    info.textContent = 'Address and TIN come from the supplier record.';
                                }
                            }
                            select.addEventListener('change', refresh);
                            refresh();
                        });
                        </script>
                    </div>
                    <div class="col-md-6" id="creditAccountWrapper">
                        <label class="form-label fw-semibold">Credit Account <span class="text-danger">*</span></label>
                        <select name="credit_account_id" class="form-select @error('credit_account_id') is-invalid @enderror">
                            <option value="">Select Account</option>
                            @foreach($creditAccounts as $account)
                            <option value="{{ $account->id }}" {{ (string) old('credit_account_id', $voucher?->credit_account_id) === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted">Usually "Accounts Payable - &lt;Vendor&gt;". Add new accounts via <a href="{{ route('chart-of-accounts.create') }}" target="_blank">Chart of Accounts</a>.</div>
                        @error('credit_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-3" id="codPaymentWrapper">
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

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const purchaseType = document.getElementById('purchaseType');
                    const creditWrapper = document.getElementById('creditAccountWrapper');
                    const codWrapper = document.getElementById('codPaymentWrapper');
                    const creditAccountSelect = creditWrapper.querySelector('select[name="credit_account_id"]');
                    const codSelects = codWrapper.querySelectorAll('select');

                    function refresh() {
                        const isCod = purchaseType.value === 'cod';
                        creditWrapper.style.display = isCod ? 'none' : '';
                        codWrapper.style.display = isCod ? '' : 'none';
                        creditAccountSelect.required = !isCod;
                        codSelects.forEach(function (select) { select.required = isCod; });
                    }

                    purchaseType.addEventListener('change', refresh);
                    refresh();
                });
                </script>

                <h5 class="fw-bold mb-3 mt-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="list" style="width: 20px; height: 20px;"></i>
                    <span>Line Items</span>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle voucher-items-table" data-no-table-enhance="1" style="min-width: 1080px;">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Qty</th>
                                <th style="width: 90px;">Unit</th>
                                <th>Particulars</th>
                                <th style="width: 220px;">Cost Account</th>
                                <th style="width: 130px;">Amount w/ VAT</th>
                                <th style="width: 120px;">VAT-Exempt</th>
                                <th style="width: 120px;">Non-VAT</th>
                                <th style="width: 170px;">Computed</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsContainer">
                            @foreach($items as $index => $item)
                            <tr class="voucher-item-row">
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][quantity]" class="form-control form-control-sm" value="{{ $item['quantity'] }}"></td>
                                <td><input type="text" name="items[{{ $index }}][unit]" class="form-control form-control-sm" value="{{ $item['unit'] }}"></td>
                                <td>
                                    <input type="text" name="items[{{ $index }}][particulars]" class="form-control form-control-sm" value="{{ $item['particulars'] }}" required>
                                    <input type="text" name="items[{{ $index }}][remarks]" class="form-control form-control-sm mt-1" placeholder="Remarks (optional)" value="{{ $item['remarks'] }}">
                                </td>
                                <td>
                                    <select name="items[{{ $index }}][cost_account_id]" class="form-select form-select-sm" required>
                                        <option value="">Select</option>
                                        @foreach($costAccounts as $account)
                                        <option value="{{ $account->id }}" {{ (string) $item['cost_account_id'] === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][amount_w_vat]" class="form-control form-control-sm voucher-item-amount-w-vat" value="{{ $item['amount_w_vat'] }}"></td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][vat_exempt]" class="form-control form-control-sm voucher-item-vat-exempt" value="{{ $item['vat_exempt'] }}"></td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][non_vat_purchase]" class="form-control form-control-sm voucher-item-non-vat" value="{{ $item['non_vat_purchase'] }}"></td>
                                <td class="small text-muted voucher-item-preview">—</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger voucher-item-remove"><i data-lucide="x"></i></button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <template id="itemRowTemplate">
                    <tr class="voucher-item-row">
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][quantity]" class="form-control form-control-sm"></td>
                        <td><input type="text" name="items[__INDEX__][unit]" class="form-control form-control-sm"></td>
                        <td>
                            <input type="text" name="items[__INDEX__][particulars]" class="form-control form-control-sm" required>
                            <input type="text" name="items[__INDEX__][remarks]" class="form-control form-control-sm mt-1" placeholder="Remarks (optional)">
                        </td>
                        <td>
                            <select name="items[__INDEX__][cost_account_id]" class="form-select form-select-sm" required>
                                <option value="">Select</option>
                                @foreach($costAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][amount_w_vat]" class="form-control form-control-sm voucher-item-amount-w-vat"></td>
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][vat_exempt]" class="form-control form-control-sm voucher-item-vat-exempt"></td>
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][non_vat_purchase]" class="form-control form-control-sm voucher-item-non-vat"></td>
                        <td class="small text-muted voucher-item-preview">—</td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger voucher-item-remove"><i data-lucide="x"></i></button></td>
                    </tr>
                </template>

                <button type="button" id="addItemRowBtn" class="btn btn-sm btn-outline-primary mt-2">
                    <i data-lucide="plus-circle" class="me-1"></i> Add Line Item
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4 shadow-sm h-100">
            <div class="card-body p-0 d-flex flex-column h-100">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
                    <span>Grand Total</span>
                </h5>
                <div class="mb-3">
                    <div class="h4 fw-bold" id="grandTotal">₱0.00</div>
                    <div class="small text-muted">Sum of net purchases + VAT-exempt + non-VAT across all line items.</div>
                </div>
                <div class="flex-grow-1 mb-3">
                    <label class="form-label fw-semibold">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="6">{{ old('remarks', $voucher?->remarks) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-3 mt-4">
    <a href="{{ route('purchase-vouchers.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
    <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
        <i data-lucide="save" style="width: 18px; height: 18px;"></i>
        <span>Save Purchase Voucher</span>
    </button>
</div>
