@extends('layouts.app')
@section('page_title', 'Petty Cash Voucher ' . $pettyCashVoucher->pcv_no)
@section('content')
    <x-page-header title="Petty Cash Voucher {{ $pettyCashVoucher->pcv_no }}" subtitle="Petty cash purchase detail" icon="wallet">
        @unless($pettyCashVoucher->isReplenished())
        <a href="{{ route('check-vouchers.create', ['replenish_pcv' => [$pettyCashVoucher->id]]) }}" class="btn btn-success text-white">
            <i data-lucide="banknote" class="me-1"></i> Replenish via Check Voucher
        </a>
        @endunless
        <a href="{{ route('petty-cash-vouchers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="info"></i> Voucher Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="small text-muted">Date</div><div class="fw-semibold">{{ $pettyCashVoucher->date->format('M d, Y') }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Status</div><div>
                        <span class="badge {{ $pettyCashVoucher->isReplenished() ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }}">
                            {{ $pettyCashVoucher->isReplenished() ? 'Replenished' : 'Pending' }}
                        </span>
                    </div></div>
                    <div class="col-md-3"><div class="small text-muted">Replenished Via</div><div class="fw-semibold">{{ $pettyCashVoucher->checkVoucher?->cv_no ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Sub-Total</div><div class="fw-semibold">₱{{ number_format($pettyCashVoucher->total, 2) }}</div></div>
                </div>
                @if($pettyCashVoucher->remarks)
                <div class="mt-3"><div class="small text-muted">Remarks</div><div>{{ $pettyCashVoucher->remarks }}</div></div>
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
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pettyCashVoucher->items as $item)
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
                                <td colspan="7" class="text-end">Sub-Total</td>
                                <td class="text-end">₱{{ number_format($pettyCashVoucher->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
