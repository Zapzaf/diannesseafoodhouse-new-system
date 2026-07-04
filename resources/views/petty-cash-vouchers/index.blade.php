@extends('layouts.app')
@section('page_title', 'Petty Cash Vouchers (PCV)')
@section('content')
    <x-page-header title="Petty Cash Vouchers (PCV)" subtitle="Purchases paid out of petty cash on hand" icon="wallet">
        <a href="{{ route('petty-cash-vouchers.create') }}" class="btn btn-primary">
            <i data-lucide="plus-circle" class="me-1"></i> New Petty Cash Voucher
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="wallet"></i> Petty Cash Purchases</div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search PCV #" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Replenishment</option>
                            <option value="replenished" {{ request('status') === 'replenished' ? 'selected' : '' }}>Replenished</option>
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
                                <th>PCV #</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th>Replenished Via</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $voucher)
                            <tr>
                                <td>{{ $voucher->date->format('M d, Y') }}</td>
                                <td class="fw-semibold">{{ $voucher->pcv_no }}</td>
                                <td class="text-end">₱{{ number_format($voucher->total, 2) }}</td>
                                <td>
                                    <span class="badge {{ $voucher->isReplenished() ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }}">
                                        {{ $voucher->isReplenished() ? 'Replenished' : 'Pending' }}
                                    </span>
                                </td>
                                <td>{{ $voucher->checkVoucher?->cv_no ?? '—' }}</td>
                                <td class="table-actions-cell text-nowrap">
                                    <a href="{{ route('petty-cash-vouchers.show', $voucher) }}" class="btn btn-sm btn-outline-secondary"><i data-lucide="eye"></i></a>
                                    @unless($voucher->isReplenished())
                                    <a href="{{ route('petty-cash-vouchers.edit', $voucher) }}" class="btn btn-sm btn-outline-primary"><i data-lucide="edit-2"></i></a>
                                    <form action="{{ route('petty-cash-vouchers.destroy', $voucher) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this PCV?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2"></i></button>
                                    </form>
                                    @endunless
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No petty cash vouchers found.</td></tr>
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
