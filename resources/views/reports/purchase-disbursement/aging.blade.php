@extends('layouts.app')
@section('page_title', 'Unpaid APV Aging')
@section('content')
    <x-page-header title="Unpaid APV Aging" subtitle="Outstanding accounts payable by vendor" icon="alert-triangle">
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-3 shadow-sm mb-4" style="max-width: 320px;">
            <div class="small text-muted">Total Outstanding</div>
            <div class="h4 fw-bold mb-0">₱{{ number_format($totalOutstanding, 2) }}</div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="alert-triangle"></i> Outstanding Purchase Vouchers</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>APV #</th>
                                <th>Vendor</th>
                                <th>Date</th>
                                <th>Days Outstanding</th>
                                <th>Aging Bucket</th>
                                <th class="text-end">Remaining Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $row)
                            <tr>
                                <td><a href="{{ route('purchase-vouchers.show', $row->voucher) }}">{{ $row->voucher->apv_no }}</a></td>
                                <td>{{ $row->voucher->vendor?->name ?? '—' }}</td>
                                <td>{{ $row->voucher->date->format('M d, Y') }}</td>
                                <td>{{ $row->days_outstanding }}</td>
                                <td><span class="badge bg-warning-soft text-warning">{{ $row->bucket }}</span></td>
                                <td class="text-end">₱{{ number_format($row->remaining_balance, 2) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $row->voucher->status)) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No unpaid purchase vouchers. Nice!</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
