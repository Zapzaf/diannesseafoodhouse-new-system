@extends('layouts.app')
@section('page_title', 'Check Voucher ' . $checkVoucher->cv_no)
@php
    $canDeleteVoucher = auth()->user()->isAdmin()
        && ! ($checkVoucher->type === 'advance' && $checkVoucher->liquidations->isNotEmpty());
    $deleteVoucherConfirm = $checkVoucher->status === 'draft'
        ? 'Delete Check Voucher '.$checkVoucher->cv_no.'? This cannot be undone.'
        : 'Check Voucher '.$checkVoucher->cv_no.' has already been paid out. Deleting it will also remove its Check Register entry and any receipts. This cannot be undone. Continue?';
@endphp
@section('content')
    <x-page-header title="Check Voucher {{ $checkVoucher->cv_no }}" subtitle="{{ ucwords(str_replace('_', ' ', $checkVoucher->type)) }}" icon="banknote">
        @if($canDeleteVoucher)
        <form action="{{ route('check-vouchers.destroy', $checkVoucher) }}" method="POST" onsubmit="return confirm('{{ $deleteVoucherConfirm }}')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">
                <i data-lucide="trash-2" class="me-1"></i> Delete
            </button>
        </form>
        @endif
        <a href="{{ route('check-vouchers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Status</div>
                    <div class="h5 fw-bold mb-0">
                        <span class="badge {{ match($checkVoucher->status) { 'issued' => 'bg-primary-soft text-primary', 'cleared' => 'bg-success-soft text-success', 'voided' => 'bg-secondary-soft text-secondary', default => 'bg-warning-soft text-warning' } }}">
                            {{ ucfirst($checkVoucher->status) }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Amount w/ VAT</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($checkVoucher->amount_w_vat, 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">EWT Withheld</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($checkVoucher->ewt_amount, 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Amount Paid</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($checkVoucher->amount_paid, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="info"></i> Voucher Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="small text-muted">Date</div><div class="fw-semibold">{{ $checkVoucher->date->format('M d, Y') }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Reference #</div><div class="fw-semibold">{{ $checkVoucher->reference_no ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Payee</div><div class="fw-semibold">{{ $checkVoucher->payee_name }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Particulars</div><div class="fw-semibold">{{ $checkVoucher->particulars }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Payment Method</div><div class="fw-semibold">{{ ucwords(str_replace('_', ' ', $checkVoucher->payment_method)) }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Bank Account</div><div class="fw-semibold">{{ $checkVoucher->bankAccount ? $checkVoucher->bankAccount->bank_name . ' — ' . $checkVoucher->bankAccount->account_name : '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">EWT Rate</div><div class="fw-semibold">{{ number_format($checkVoucher->ewt_rate * 100, 2) }}%</div></div>
                    <div class="col-md-3"><div class="small text-muted">Created By</div><div class="fw-semibold">{{ $checkVoucher->creator?->name ?? '—' }}</div></div>
                </div>
                @if($checkVoucher->purchaseVoucher)
                <div class="mt-3">
                    <div class="small text-muted">Settles Purchase Voucher</div>
                    <a href="{{ route('purchase-vouchers.show', $checkVoucher->purchaseVoucher) }}">{{ $checkVoucher->purchaseVoucher->apv_no }}</a>
                </div>
                @endif
                @if($checkVoucher->service)
                <div class="mt-3">
                    <div class="small text-muted">Settles Service</div>
                    <a href="{{ route('services.show', $checkVoucher->service) }}">{{ $checkVoucher->service->ref_no }}</a>
                </div>
                @endif
                @if($checkVoucher->type === 'advance')
                <div class="mt-3">
                    <div class="small text-muted">Advance Account</div>
                    <div class="fw-semibold">{{ $checkVoucher->advanceAccount?->name }}</div>
                </div>
                @endif
                @if($checkVoucher->pettyCashVouchers->isNotEmpty())
                <div class="mt-3">
                    <div class="small text-muted">Replenishes Petty Cash Vouchers</div>
                    @foreach($checkVoucher->pettyCashVouchers as $pcv)
                    <a href="{{ route('petty-cash-vouchers.show', $pcv) }}" class="badge bg-primary-soft text-primary text-decoration-none me-1">{{ $pcv->pcv_no }}</a>
                    @endforeach
                </div>
                @endif
                @if($checkVoucher->remarks)
                <div class="mt-3"><div class="small text-muted">Remarks</div><div>{{ $checkVoucher->remarks }}</div></div>
                @endif
            </div>
        </div>

        @php $receiptsEditable = in_array($checkVoucher->type, ['cod_purchase', 'other_disbursement'], true); @endphp
        @if($checkVoucher->receipts->isNotEmpty() || $receiptsEditable)
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <span><i class="me-1" data-lucide="receipt"></i> Receipts / Invoices ({{ $checkVoucher->receipts->count() }})</span>
                @if($checkVoucher->has_multiple_cost_accounts)
                <span class="badge bg-info-soft text-info">Split across {{ $checkVoucher->costAccountBreakdown()->count() }} Chart of Accounts</span>
                @endif
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Reuse CV # <strong>{{ $checkVoucher->cv_no }}</strong> for every receipt in this same payment — add a row per receipt instead of creating a new Check Voucher.</p>
                @if($checkVoucher->has_multiple_cost_accounts)
                <div class="row g-2 mb-3">
                    @foreach($checkVoucher->costAccountBreakdown() as $breakdown)
                    <div class="col-md-4">
                        <div class="border rounded p-2 small">
                            <div class="text-muted">{{ $breakdown['account']?->name ?? 'Unassigned' }}</div>
                            <div class="fw-semibold">₱{{ number_format($breakdown['total'], 2) }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>SI / Receipt #</th>
                                <th>Supplier</th>
                                <th>Cost Account</th>
                                <th class="text-end">Amount w/ VAT</th>
                                <th class="text-end">VAT-Exempt</th>
                                <th class="text-end">Non-VAT</th>
                                <th class="text-end">Total</th>
                                @if($receiptsEditable)<th style="width:110px;">Actions</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($checkVoucher->receipts as $receipt)
                            <tr id="receipt-view-{{ $receipt->id }}">
                                <td>{{ $receipt->si_no ?: '—' }}</td>
                                <td>{{ $receipt->supplier?->name ?: '—' }}</td>
                                <td class="text-muted small">{{ $receipt->costAccount?->name ?: '—' }}</td>
                                <td class="text-end">₱{{ number_format($receipt->amount_w_vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($receipt->vat_exempt, 2) }}</td>
                                <td class="text-end">₱{{ number_format($receipt->non_vat_purchase, 2) }}</td>
                                <td class="text-end fw-semibold">₱{{ number_format($receipt->total, 2) }}</td>
                                @if($receiptsEditable)
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary toggleReceiptEdit" data-target="receipt-edit-{{ $receipt->id }}"><i data-lucide="pencil" style="width:14px;height:14px;"></i></button>
                                    <form action="{{ route('check-vouchers.receipts.destroy', [$checkVoucher, $receipt]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this receipt?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @if($receiptsEditable)
                            <tr id="receipt-edit-{{ $receipt->id }}" class="d-none">
                                <td colspan="8" class="bg-light">
                                    <form action="{{ route('check-vouchers.receipts.update', [$checkVoucher, $receipt]) }}" method="POST" class="row g-3 align-items-end py-2">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold mb-1">SI / Receipt #</label>
                                            <input type="text" name="si_no" class="form-control form-control-sm" placeholder="SI #" value="{{ $receipt->si_no }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold mb-1">Supplier</label>
                                            @include('partials.supplier-picker', [
                                                'name' => 'supplier_id',
                                                'suppliers' => $suppliers,
                                                'selected' => $receipt->supplier_id,
                                                'placeholder' => 'Search supplier...',
                                                'searchClass' => 'form-control-sm',
                                                'selectClass' => 'form-select-sm',
                                            ])
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold mb-1">Cost Account</label>
                                            <select name="cost_account_id" class="form-select form-select-sm">
                                                <option value="">Select Account</option>
                                                @foreach($costAccounts as $account)
                                                <option value="{{ $account->id }}" {{ (int) $receipt->cost_account_id === $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small fw-semibold mb-1">Amount w/ VAT</label>
                                            <input type="number" step="0.01" min="0" name="amount_w_vat" class="form-control form-control-sm" value="{{ $receipt->amount_w_vat }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small fw-semibold mb-1">VAT-Exempt</label>
                                            <input type="number" step="0.01" min="0" name="vat_exempt" class="form-control form-control-sm" value="{{ $receipt->vat_exempt }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small fw-semibold mb-1">Non-VAT</label>
                                            <input type="number" step="0.01" min="0" name="non_vat_purchase" class="form-control form-control-sm" value="{{ $receipt->non_vat_purchase }}">
                                        </div>
                                        <div class="col-12 d-flex gap-2 pt-1">
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary toggleReceiptEdit" data-target="receipt-edit-{{ $receipt->id }}">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="3" class="text-end">Sub-Total</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('amount_w_vat'), 2) }}</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('vat_exempt'), 2) }}</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('non_vat_purchase'), 2) }}</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('total'), 2) }}</td>
                                @if($receiptsEditable)<td></td>@endif
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($receiptsEditable)
                <div class="border rounded p-3 bg-light mt-3">
                    <h6 class="fw-bold text-primary small text-uppercase mb-3">
                        <i data-lucide="plus-circle" style="width:14px;height:14px;" class="me-1"></i>
                        Add Another Receipt to CV {{ $checkVoucher->cv_no }}
                    </h6>
                    <form action="{{ route('check-vouchers.receipts.store', $checkVoucher) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">SI / Receipt #</label>
                            <input type="text" name="si_no" class="form-control @error('si_no') is-invalid @enderror" value="{{ old('si_no') }}">
                            @error('si_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Supplier</label>
                            @include('partials.supplier-picker', [
                                'name' => 'supplier_id',
                                'suppliers' => $suppliers,
                                'selected' => old('supplier_id'),
                                'placeholder' => 'Search supplier...',
                                'selectClass' => (($errors ?? null)?->has('supplier_id')) ? 'is-invalid' : '',
                            ])
                            @error('supplier_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Cost Account</label>
                            <select name="cost_account_id" class="form-select @error('cost_account_id') is-invalid @enderror">
                                <option value="">Same as CV ({{ $checkVoucher->costAccount?->name ?? 'none set' }})</option>
                                @foreach($costAccounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('cost_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('cost_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Amount w/ VAT</label>
                            <input type="number" step="0.01" min="0" name="amount_w_vat" class="form-control @error('amount_w_vat') is-invalid @enderror" value="{{ old('amount_w_vat') }}">
                            @error('amount_w_vat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">VAT-Exempt</label>
                            <input type="number" step="0.01" min="0" name="vat_exempt" class="form-control" value="{{ old('vat_exempt') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Non-VAT</label>
                            <input type="number" step="0.01" min="0" name="non_vat_purchase" class="form-control" value="{{ old('non_vat_purchase') }}">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
                                <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Receipt
                            </button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($checkVoucher->payment_method !== 'check')
        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="banknote"></i> Payment Status</div>
            <div class="card-body">
                @if($checkVoucher->status === 'draft')
                    <p class="text-muted small mb-3">This {{ str_replace('_', ' ', $checkVoucher->payment_method) }} disbursement has not been marked as paid yet.</p>
                    <form action="{{ route('check-vouchers.mark-paid', $checkVoucher) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success text-white">
                            <i data-lucide="check-square" class="me-1"></i> Mark as Paid
                        </button>
                    </form>
                @else
                    <div class="row g-3">
                        <div class="col-md-4"><div class="small text-muted">Status</div><div class="fw-semibold">{{ ucfirst($checkVoucher->status) }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Amount Paid</div><div class="fw-semibold">₱{{ number_format($checkVoucher->amount_paid, 2) }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Method</div><div class="fw-semibold">{{ ucwords(str_replace('_', ' ', $checkVoucher->payment_method)) }}</div></div>
                    </div>
                @endif
            </div>
        </div>
        @endif

        @if($checkVoucher->payment_method === 'check')
        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="check-square"></i> Check Register</div>
            <div class="card-body">
                @if($checkVoucher->checkRegisterEntry)
                    <div class="row g-3">
                        <div class="col-md-3"><div class="small text-muted">Check #</div><div class="fw-semibold">{{ $checkVoucher->checkRegisterEntry->check_no }}</div></div>
                        <div class="col-md-3"><div class="small text-muted">Check Date</div><div class="fw-semibold">{{ $checkVoucher->checkRegisterEntry->check_date->format('M d, Y') }}</div></div>
                        <div class="col-md-3"><div class="small text-muted">Amount</div><div class="fw-semibold">₱{{ number_format($checkVoucher->checkRegisterEntry->amount, 2) }}</div></div>
                        <div class="col-md-3"><div class="small text-muted">Status</div><div class="fw-semibold">{{ ucfirst($checkVoucher->checkRegisterEntry->status) }}</div></div>
                    </div>
                @else
                    <p class="text-muted small mb-3">No check has been issued for this voucher yet.</p>
                    <form action="{{ route('check-vouchers.issue-check', $checkVoucher) }}" method="POST" class="row g-2">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Check Date</label>
                            <input type="date" name="check_date" class="form-control @error('check_date') is-invalid @enderror" value="{{ old('check_date', now()->toDateString()) }}" required>
                            @error('check_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Check #</label>
                            <input type="text" name="check_no" class="form-control @error('check_no') is-invalid @enderror" value="{{ old('check_no') }}" required>
                            @error('check_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success text-white">
                                <i data-lucide="check-square" class="me-1"></i> Issue Check
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
        @endif

        @if($checkVoucher->type === 'advance')
        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="wallet"></i> Advance Liquidation</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><div class="small text-muted">Advance Amount</div><div class="fw-semibold">₱{{ number_format($checkVoucher->amount_w_vat, 2) }}</div></div>
                    <div class="col-md-4"><div class="small text-muted">Liquidated</div><div class="fw-semibold">₱{{ number_format($checkVoucher->liquidated_amount, 2) }}</div></div>
                    <div class="col-md-4"><div class="small text-muted">Outstanding</div><div class="fw-semibold">₱{{ number_format($checkVoucher->outstanding_advance, 2) }}</div></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="small text-muted">VAT</div>
                        <div class="fw-semibold">
                            @if($checkVoucher->vat > 0)
                                <span class="badge bg-primary-soft text-primary">With VAT</span>
                            @else
                                <span class="badge bg-secondary-soft text-secondary">Without VAT</span>
                            @endif
                        </div>
                    </div>
                    @if($checkVoucher->vat > 0)
                    <div class="col-md-4"><div class="small text-muted">Net Purchases</div><div class="fw-semibold">₱{{ number_format($checkVoucher->net_purchases, 2) }}</div></div>
                    <div class="col-md-4"><div class="small text-muted">VAT Amount</div><div class="fw-semibold">₱{{ number_format($checkVoucher->vat, 2) }}</div></div>
                    @else
                    <div class="col-md-4"><div class="small text-muted">Non-VAT Amount</div><div class="fw-semibold">₱{{ number_format($checkVoucher->non_vat_purchase, 2) }}</div></div>
                    @endif
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm">
                        <thead><tr><th>Date</th><th>Expense Account</th><th class="text-end">Amount</th><th>Remarks</th></tr></thead>
                        <tbody>
                            @forelse($checkVoucher->liquidations as $liquidation)
                            <tr>
                                <td>{{ $liquidation->date->format('M d, Y') }}</td>
                                <td>{{ $liquidation->expenseAccount?->name }}</td>
                                <td class="text-end">₱{{ number_format($liquidation->amount, 2) }}</td>
                                <td class="text-muted small">{{ $liquidation->remarks ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No liquidations recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($checkVoucher->status === 'draft')
                <p class="text-muted small mb-0">This advance has not been paid out yet — mark it as paid above before recording a liquidation.</p>
                @elseif($checkVoucher->outstanding_advance > 0)
                <form action="{{ route('check-vouchers.liquidate-advance', $checkVoucher) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" value="{{ old('amount') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Expense Account</label>
                        <select name="expense_account_id" class="form-select form-select-sm" required>
                            <option value="">Select</option>
                            @foreach(\App\Models\ChartOfAccount::where('type', 'debit_expense')->where('is_active', true)->orderBy('name')->get() as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary">Record Liquidation</button>
                    </div>
                </form>
                @endif
            </div>
        </div>
        @endif

        @include('partials.attachments', ['attachmentType' => 'check-voucher', 'attachmentId' => $checkVoucher->id, 'attachments' => $checkVoucher->attachments])
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/supplier-picker.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggleReceiptEdit').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            if (target) target.classList.toggle('d-none');
        });
    });
});
</script>
@endpush
