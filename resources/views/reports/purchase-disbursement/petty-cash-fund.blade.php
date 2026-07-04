@extends('layouts.app')
@section('page_title', 'Petty Cash Fund Status')
@section('content')
    <x-page-header title="Petty Cash Fund Status" subtitle="Running float balance: spent vs. replenished" icon="wallet">
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Total Spent</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($totalSpent, 2) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Total Replenished</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($totalReplenished, 2) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Pending Replenishment</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($pendingReplenishment, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="wallet"></i> Petty Cash Vouchers</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>PCV #</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th>Replenished Via</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $voucher)
                            <tr>
                                <td>{{ $voucher->date->format('M d, Y') }}</td>
                                <td><a href="{{ route('petty-cash-vouchers.show', $voucher) }}">{{ $voucher->pcv_no }}</a></td>
                                <td class="text-end">₱{{ number_format($voucher->total, 2) }}</td>
                                <td>
                                    <span class="badge {{ $voucher->isReplenished() ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }}">
                                        {{ $voucher->isReplenished() ? 'Replenished' : 'Pending' }}
                                    </span>
                                </td>
                                <td>{{ $voucher->checkVoucher?->cv_no ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No petty cash vouchers recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
