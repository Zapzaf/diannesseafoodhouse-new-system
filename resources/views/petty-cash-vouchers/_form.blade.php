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
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $voucher?->date?->toDateString()) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">PCV # <span class="text-danger">*</span></label>
                        <input type="text" name="pcv_no" class="form-control @error('pcv_no') is-invalid @enderror" value="{{ old('pcv_no', $voucher?->pcv_no) }}" required>
                        @error('pcv_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @include('finance._branch-picker', ['voucher' => $voucher, 'colWidth' => 6])
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Vendor / Supplier</label>
                        <select name="supplier_id" id="pcvSupplier" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">Select Supplier (optional)</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                data-address="{{ $supplier->address }}"
                                data-tin="{{ $supplier->tin }}"
                                @selected((string) old('supplier_id', $voucher?->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" id="pcvSupplierAddress" class="form-control" value="{{ $voucher?->supplier?->address }}" readonly disabled placeholder="From supplier record">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">TIN</label>
                        <input type="text" id="pcvSupplierTin" class="form-control" value="{{ $voucher?->supplier?->tin }}" readonly disabled placeholder="From supplier record">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">CV #</label>
                        <input type="text" class="form-control" value="{{ $voucher?->checkVoucher?->cv_no ?? 'Not yet replenished' }}" readonly disabled>
                        <div class="form-text">Set automatically when this PCV is replenished by a Check Voucher.</div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const select = document.getElementById('pcvSupplier');
                    if (!select) return;
                    function fillSupplierInfo() {
                        const option = select.selectedOptions[0];
                        document.getElementById('pcvSupplierAddress').value = option?.dataset.address || '';
                        document.getElementById('pcvSupplierTin').value = option?.dataset.tin || '';
                    }
                    select.addEventListener('change', fillSupplierInfo);
                    fillSupplierInfo();
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
                                <th style="width: 90px;" class="text-end">Qty</th>
                                <th style="width: 90px;">Unit</th>
                                <th>Particulars</th>
                                <th style="width: 220px;">Cost Account</th>
                                <th style="width: 130px;" class="text-end">Amount w/ VAT</th>
                                <th style="width: 120px;" class="text-end">VAT-Exempt</th>
                                <th style="width: 120px;" class="text-end">Non-VAT</th>
                                <th style="width: 180px;" class="text-end">Computed</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsContainer">
                            @foreach($items as $index => $item)
                            @php $rowAmountError = $errors->first("items.$index.amount_w_vat"); @endphp
                            <tr class="voucher-item-row {{ $rowAmountError ? 'table-danger' : '' }}">
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][quantity]" class="form-control form-control-sm text-end" value="{{ $item['quantity'] }}"></td>
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
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[{{ $index }}][amount_w_vat]" class="form-control form-control-sm text-end voucher-item-amount-w-vat {{ $rowAmountError ? 'is-invalid' : '' }}" value="{{ $item['amount_w_vat'] }}">
                                </td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][vat_exempt]" class="form-control form-control-sm text-end voucher-item-vat-exempt {{ $rowAmountError ? 'is-invalid' : '' }}" value="{{ $item['vat_exempt'] }}"></td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][non_vat_purchase]" class="form-control form-control-sm text-end voucher-item-non-vat {{ $rowAmountError ? 'is-invalid' : '' }}" value="{{ $item['non_vat_purchase'] }}"></td>
                                <td class="voucher-item-preview">—</td>
                                <td><button type="button" class="btn btn-sm btn-danger voucher-item-remove text-white"><i data-lucide="x"></i></button></td>
                            </tr>
                            @if($rowAmountError)
                            <tr class="voucher-item-row-error">
                                <td colspan="9" class="small text-danger pt-0">
                                    <i data-lucide="alert-circle" style="width: 14px; height: 14px;" class="me-1"></i>
                                    Row {{ $index + 1 }} ({{ $item['particulars'] ?: 'no particulars yet' }}): {{ $rowAmountError }}
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <template id="itemRowTemplate">
                    <tr class="voucher-item-row">
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][quantity]" class="form-control form-control-sm text-end"></td>
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
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][amount_w_vat]" class="form-control form-control-sm text-end voucher-item-amount-w-vat"></td>
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][vat_exempt]" class="form-control form-control-sm text-end voucher-item-vat-exempt"></td>
                        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][non_vat_purchase]" class="form-control form-control-sm text-end voucher-item-non-vat"></td>
                        <td class="voucher-item-preview">—</td>
                        <td><button type="button" class="btn btn-sm btn-danger voucher-item-remove text-white"><i data-lucide="x"></i></button></td>
                    </tr>
                </template>

                <button type="button" id="addItemRowBtn" class="btn btn-sm btn-primary text-white mt-2">
                    <i data-lucide="plus-circle" class="me-1"></i> Add Line Item
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4 shadow-sm h-100">
            <div class="card-body p-0 d-flex flex-column h-100">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="wallet" style="width: 20px; height: 20px;"></i>
                    <span>Sub-Total</span>
                </h5>
                <div class="voucher-summary-total mb-3">
                    <div class="h4 fw-bold mb-1" id="grandTotal">₱0.00</div>
                    <div class="small text-muted">VAT-inclusive: sum of Amount w/ VAT + VAT-exempt + Non-VAT across all line items. This is what gets reimbursed in one lump sum via a PCF Replenishment Check Voucher.</div>
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
    <a href="{{ route('petty-cash-vouchers.index') }}" class="btn btn-secondary text-white px-4">Cancel</a>
    <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
        <i data-lucide="save" style="width: 18px; height: 18px;"></i>
        <span>Save Petty Cash Voucher</span>
    </button>
</div>
