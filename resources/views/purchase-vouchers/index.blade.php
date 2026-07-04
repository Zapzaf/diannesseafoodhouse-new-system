@extends('layouts.app')
@section('page_title', 'Purchase Vouchers (APV)')
@section('content')
    <x-page-header title="Purchase Vouchers (APV)" subtitle="Credit purchases not yet paid to a vendor" icon="file-text">
        <a href="{{ route('purchase-vouchers.create') }}" class="btn btn-primary">
            <i data-lucide="plus-circle" class="me-1"></i> New Purchase Voucher
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="file-text"></i> Credit Purchases</div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search APV # or vendor" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>APV #</th>
                                <th>Vendor</th>
                                <th>Credit Account</th>
                                <th class="text-end">Total Purchases</th>
                                <th>Status</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $voucher)
                            <tr>
                                <td>{{ $voucher->date->format('M d, Y') }}</td>
                                <td class="fw-semibold">{{ $voucher->apv_no }}</td>
                                <td>{{ $voucher->vendor?->name ?? '—' }}</td>
                                <td>{{ $voucher->creditAccount?->name }}</td>
                                <td class="text-end">₱{{ number_format($voucher->total, 2) }}</td>
                                <td>
                                    <span class="badge {{ match($voucher->status) { 'paid' => 'bg-success-soft text-success', 'partially_paid' => 'bg-warning-soft text-warning', default => 'bg-danger-soft text-danger' } }}">
                                        {{ ucwords(str_replace('_', ' ', $voucher->status)) }}
                                    </span>
                                </td>
                                <td class="table-actions-cell text-nowrap">
                                    <a href="{{ route('purchase-vouchers.show', $voucher) }}" class="btn btn-sm btn-outline-secondary"><i data-lucide="eye"></i></a>
                                    @if($voucher->status === 'unpaid')
                                    <a href="{{ route('purchase-vouchers.edit', $voucher) }}" class="btn btn-sm btn-outline-primary"><i data-lucide="edit-2"></i></a>
                                    <form action="{{ route('purchase-vouchers.destroy', $voucher) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this APV?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2"></i></button>
                                    </form>
                                    @else
                                    <a href="{{ route('check-vouchers.create', ['pay_apv' => $voucher->id]) }}" class="btn btn-sm btn-outline-success">Pay</a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No purchase vouchers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($vouchers->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $vouchers->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
