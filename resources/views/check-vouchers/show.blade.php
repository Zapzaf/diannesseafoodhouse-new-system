@extends('layouts.app')
@section('page_title', 'Check Voucher ' . $checkVoucher->cv_no)
@section('content')
    <x-page-header title="Check Voucher {{ $checkVoucher->cv_no }}" subtitle="{{ ucwords(str_replace('_', ' ', $checkVoucher->type)) }}" icon="banknote">
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
                    <div class="col-md-3"><div class="small text-muted">Payee</div><div class="fw-semibold">{{ $checkVoucher->payee_name }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Particulars</div><div class="fw-semibold">{{ $checkVoucher->particulars }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">EWT Rate</div><div class="fw-semibold">{{ number_format($checkVoucher->ewt_rate * 100, 2) }}%</div></div>
                </div>
                @if($checkVoucher->purchaseVoucher)
                <div class="mt-3">
                    <div class="small text-muted">Settles Purchase Voucher</div>
                    <a href="{{ route('purchase-vouchers.show', $checkVoucher->purchaseVoucher) }}">{{ $checkVoucher->purchaseVoucher->apv_no }}</a>
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

        @if($checkVoucher->receipts->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="receipt"></i> Receipts / Invoices ({{ $checkVoucher->receipts->count() }})</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>SI / Receipt #</th>
                                <th class="text-end">Amount w/ VAT</th>
                                <th class="text-end">VAT-Exempt</th>
                                <th class="text-end">Non-VAT</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($checkVoucher->receipts as $receipt)
                            <tr>
                                <td>{{ $receipt->si_no ?: '—' }}</td>
                                <td class="text-end">₱{{ number_format($receipt->amount_w_vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($receipt->vat_exempt, 2) }}</td>
                                <td class="text-end">₱{{ number_format($receipt->non_vat_purchase, 2) }}</td>
                                <td class="text-end fw-semibold">₱{{ number_format($receipt->total, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td class="text-end">Sub-Total</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('amount_w_vat'), 2) }}</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('vat_exempt'), 2) }}</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('non_vat_purchase'), 2) }}</td>
                                <td class="text-end">₱{{ number_format($checkVoucher->receipts->sum('total'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

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
    </div>
@endsection
