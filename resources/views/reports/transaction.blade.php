@extends('layouts.app')
@section('page_title', 'Transaction Report - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Transaction Report" subtitle="Stock movement history with filters" icon="list">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.transaction.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All</option>
                        <option value="in" {{ $type === 'in' ? 'selected' : '' }}>Stock In</option>
                        <option value="out" {{ $type === 'out' ? 'selected' : '' }}>Stock Out</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.transaction.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-feather="trending-up" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Stock In</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($stockIn, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i data-feather="trending-down" class="text-danger" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Stock Out</div>
                        <div class="fs-4 fw-bold text-danger">{{ number_format($stockOut, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-feather="list" class="me-1"></i> Transactions</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Log ID</th>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Branch</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                        <tr>
                            <td class="text-nowrap fw-semibold small">{{ $tx->log_id ?? 'N/A' }}</td>
                            <td class="text-nowrap text-muted small">{{ $tx->created_at->format('M d, Y H:i') }}</td>
                            <td class="fw-semibold">{{ $tx->inventory?->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $tx->inventory?->branch?->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $tx->type === 'in' ? 'bg-success' : 'bg-danger' }}">
                                    {{ strtoupper($tx->type) }}
                                </span>
                            </td>
                            <td>{{ number_format($tx->quantity, 2) }} {{ $tx->inventory?->unit }}</td>
                            <td>
                                @if($tx->status === 'pending')
                                    <span class="badge-status badge-pending">PENDING</span>
                                @else
                                    <span class="badge-status badge-approved">APPROVED</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $tx->reason ?? '—' }}</td>
                            <td class="text-muted small">{{ $tx->creator?->name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No transactions in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($transactions->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $transactions->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
</main>
@endsection
