@extends('layouts.app')
@section('page_title', 'Purchase Voucher ' . $purchaseVoucher->apv_no)
@section('content')
    <x-page-header title="Purchase Voucher {{ $purchaseVoucher->apv_no }}" subtitle="Credit purchase details and payment history" icon="file-text">
        @if($purchaseVoucher->status !== 'paid')
        <a href="{{ route('check-vouchers.create', ['pay_apv' => $purchaseVoucher->id]) }}" class="btn btn-success text-white">
            <i data-lucide="banknote" class="me-1"></i> Pay via Check Voucher
        </a>
        @endif
        <a href="{{ route('purchase-vouchers.index') }}" class="btn btn-light text-primary">
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
                        <span class="badge {{ match($purchaseVoucher->status) { 'paid' => 'bg-success-soft text-success', 'partially_paid' => 'bg-warning-soft text-warning', default => 'bg-danger-soft text-danger' } }}">
                            {{ ucwords(str_replace('_', ' ', $purchaseVoucher->status)) }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Payable Total (w/ VAT)</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($purchaseVoucher->payable_total, 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Amount Paid</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($purchaseVoucher->amount_paid, 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Remaining Balance</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format(max($purchaseVoucher->payable_total - $purchaseVoucher->amount_paid, 0), 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="info"></i> Voucher Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="small text-muted">Date</div><div class="fw-semibold">{{ $purchaseVoucher->date->format('M d, Y') }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Vendor</div><div class="fw-semibold">{{ $purchaseVoucher->vendor?->name ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">SI #</div><div class="fw-semibold">{{ $purchaseVoucher->si_no ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Credit Account</div><div class="fw-semibold">{{ $purchaseVoucher->creditAccount?->name }}</div></div>
                </div>
                @if($purchaseVoucher->remarks)
                <div class="mt-3"><div class="small text-muted">Remarks</div><div>{{ $purchaseVoucher->remarks }}</div></div>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="list"></i> Line Items</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Particulars</th>
                                <th>Cost Account</th>
                                <th class="text-end">Amount w/ VAT</th>
                                <th class="text-end">VAT</th>
                                <th class="text-end">Net Purchases</th>
                                <th class="text-end">VAT-Exempt</th>
                                <th class="text-end">Non-VAT</th>
                                <th class="text-end">Total Purchases</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseVoucher->items as $item)
                            <tr>
                                <td>{{ $item->particulars }}</td>
                                <td>{{ $item->costAccount?->name }}</td>
                                <td class="text-end">₱{{ number_format($item->amount_w_vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($item->vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($item->net_purchases, 2) }}</td>
                                <td class="text-end">₱{{ number_format($item->vat_exempt, 2) }}</td>
                                <td class="text-end">₱{{ number_format($item->non_vat_purchase, 2) }}</td>
                                <td class="text-end fw-semibold">₱{{ number_format($item->total_purchases, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="7" class="text-end">Total Purchases</td>
                                <td class="text-end">₱{{ number_format($purchaseVoucher->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="banknote"></i> Payments (Check Vouchers)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>CV #</th>
                                <th class="text-end">Amount w/ VAT</th>
                                <th class="text-end">EWT</th>
                                <th class="text-end">Amount Paid</th>
                                <th>Status</th>
                                <th>Check #</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchaseVoucher->checkVouchers as $cv)
                            <tr>
                                <td>{{ $cv->date->format('M d, Y') }}</td>
                                <td><a href="{{ route('check-vouchers.show', $cv) }}">{{ $cv->cv_no }}</a></td>
                                <td class="text-end">₱{{ number_format($cv->amount_w_vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($cv->ewt_amount, 2) }}</td>
                                <td class="text-end">₱{{ number_format($cv->amount_paid, 2) }}</td>
                                <td>{{ ucfirst($cv->status) }}</td>
                                <td>{{ $cv->checkRegisterEntry?->check_no ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
