@extends('layouts.app')
@section('page_title', 'Pending Transactions - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Pending Transactions" subtitle="Manual stock movements awaiting review" icon="clock">
    <a href="{{ route('transactions.index') }}" class="btn btn-light text-primary">
        <i data-feather="list" class="me-1"></i> All Transactions
    </a>
    <a href="{{ route('transactions.create') }}" class="btn btn-light text-primary">
        <i data-feather="plus-circle" class="me-1"></i> New Transaction
    </a>
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><i data-feather="clock" class="me-1"></i> Pending Approval</div>
            <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="5" {{ request('per_page') == 5 ? 'selected' : '' }}>5</option>
                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                    <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                </select>
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i data-feather="search" style="width: 14px; height: 14px;"></i></button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Log ID</th>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reason</th>
                            <th>Notes</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                        <tr>
                            <td class="text-nowrap fw-semibold small">{{ $tx->log_id ?? 'N/A' }}</td>
                            <td class="text-nowrap">{{ $tx->created_at->format('M d, Y H:i') }}</td>
                            <td class="fw-semibold">{{ $tx->inventory?->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $tx->type === 'in' ? 'bg-success' : 'bg-danger' }}">
                                    {{ strtoupper($tx->type) }}
                                </span>
                            </td>
                            <td>{{ number_format($tx->quantity, 2) }} {{ $tx->inventory?->unit }}</td>
                            <td class="text-muted small">{{ $tx->reason ?? '—' }}</td>
                            <td class="text-muted small">{{ $tx->notes ?? '—' }}</td>
                            <td class="text-muted small">{{ $tx->creator?->name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No pending transactions found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} entries
            </div>
            <div class="mb-0 custom-pagination-wrapper">
                {{ $transactions->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
        <style>
            .custom-pagination-wrapper nav { margin-bottom: 0 !important; }
            .custom-pagination-wrapper p.small.text-muted { display: none !important; }
            .custom-pagination-wrapper .pagination { margin-bottom: 0 !important; }
        </style>
    </div>
</div>
</main>
@endsection
