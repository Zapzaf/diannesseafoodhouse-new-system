{{-- Admin-only modal for editing a Check Voucher's Voucher Details header. --}}
@php
    $ewtOptions = ['0' => 'None', '0.01' => '1%', '0.02' => '2%', '0.05' => '5%', '0.10' => '10%'];
    $selectedEwt = (float) old('ewt_rate', $checkVoucher->ewt_rate);
    $ewtKnown = collect(array_keys($ewtOptions))->contains(fn ($v) => abs((float) $v - $selectedEwt) < 0.00001);
    $paymentMethodLocked = $checkVoucher->status !== 'draft';
@endphp
<div class="modal fade" id="editDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('check-vouchers.update', $checkVoucher) }}" method="POST">
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="edit_details">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i data-lucide="pencil" class="me-1"></i> Edit Voucher Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $checkVoucher->date->toDateString()) }}" required>
                            @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CV # <span class="text-danger">*</span></label>
                            <input type="text" name="cv_no" class="form-control @error('cv_no') is-invalid @enderror" value="{{ old('cv_no', $checkVoucher->cv_no) }}" maxlength="50" required>
                            @error('cv_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Reference #</label>
                            <input type="text" class="form-control" value="{{ $checkVoucher->reference_no ?? '—' }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payee <span class="text-danger">*</span></label>
                            <input type="text" name="payee_name" class="form-control @error('payee_name') is-invalid @enderror" value="{{ old('payee_name', $checkVoucher->payee_name) }}" maxlength="255" required>
                            @error('payee_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Particulars <span class="text-danger">*</span></label>
                            <input type="text" name="particulars" class="form-control @error('particulars') is-invalid @enderror" value="{{ old('particulars', $checkVoucher->particulars) }}" maxlength="255" required>
                            @error('particulars')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                            @if($paymentMethodLocked)
                            <input type="hidden" name="payment_method" value="{{ $checkVoucher->payment_method }}">
                            @endif
                            <select {{ $paymentMethodLocked ? 'disabled' : 'name=payment_method required' }} class="form-select @error('payment_method') is-invalid @enderror">
                                @foreach(['cash' => 'Cash', 'check' => 'Check', 'bank_transfer' => 'Bank Transfer', 'online' => 'Online'] as $value => $label)
                                <option value="{{ $value }}" {{ (string) old('payment_method', $checkVoucher->payment_method) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($paymentMethodLocked)
                            <div class="form-text">Locked once the voucher has been paid out.</div>
                            @endif
                            @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Bank Account</label>
                            <select name="bank_account_id" class="form-select @error('bank_account_id') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach($bankAccounts as $bankAccount)
                                <option value="{{ $bankAccount->id }}" {{ (string) old('bank_account_id', $checkVoucher->bank_account_id) === (string) $bankAccount->id ? 'selected' : '' }}>{{ $bankAccount->bank_name }} — {{ $bankAccount->account_name }}</option>
                                @endforeach
                            </select>
                            @error('bank_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">EWT Rate</label>
                            <select name="ewt_rate" class="form-select @error('ewt_rate') is-invalid @enderror">
                                @unless($ewtKnown)
                                <option value="{{ $selectedEwt }}" selected>{{ number_format($selectedEwt * 100, 2) }}%</option>
                                @endunless
                                @foreach($ewtOptions as $value => $label)
                                <option value="{{ $value }}" {{ $ewtKnown && abs((float) $value - $selectedEwt) < 0.00001 ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Changing this recalculates EWT Withheld and Amount Paid.</div>
                            @error('ewt_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="2">{{ old('remarks', $checkVoucher->remarks) }}</textarea>
                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
