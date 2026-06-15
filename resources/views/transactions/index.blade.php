@extends('layouts.app')
@section('page_title', 'Inventory Transactions')
@section('content')
<main>
<x-page-header title="Inventory Transactions" subtitle="Review all stock-in and stock-out activity by branch" icon="list">
    <a href="{{ route('inventory.index') }}" class="btn btn-light text-primary">
        <i data-feather="arrow-left" class="me-1"></i> Back to Inventory
    </a>
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')
    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><i data-feather="list" class="me-1" style="width: 16px; height: 16px;"></i> Transaction Log</div>
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
                            <th>Trans. Date</th>
                            @if(auth()->user()?->isAdmin())
                            <th>Branch</th>
                            @endif
                            <th>Item ID</th>
                            <th>Item</th>
                            <th>Unit</th>
                            <th>Type</th>
                            <th>Beginning</th>
                            <th>Quantity</th>
                            <th>Remaining</th>
                            <th>Trans. Price</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                        @php
                            $isBeginning = $tx->reason === 'Initial stock';
                            $typeLabel = $isBeginning ? 'BEGINNING' : strtoupper($tx->type);
                            $typeClass = $isBeginning ? 'bg-primary' : ($tx->type === 'in' ? 'bg-success' : 'bg-danger');
                        @endphp
                        <tr>
                            <td class="text-nowrap fw-semibold small">{{ $tx->log_id ?? 'N/A' }}</td>
                            <td class="text-muted small">{{ $tx->transaction_date ? $tx->transaction_date->format('M d, Y H:i') : $tx->created_at->format('M d, Y H:i') }}</td>
                            @if(auth()->user()?->isAdmin())
                            <td class="text-muted small">{{ $tx->branch?->name ?? ($tx->inventory?->branch?->name ?? '—') }}</td>
                            @endif
                            <td class="text-muted small">{{ $tx->inventory->id }}</td>
                            <td>{{ $tx->inventory->name }}</td>
                            <td class="text-muted small">{{ $tx->inventory->unit ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $typeClass }}">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $tx->beginning_quantity !== null ? number_format((float)$tx->beginning_quantity, 2) : '—' }}</td>
                            <td>{{ number_format((float)$tx->quantity, 2) }}</td>
                            <td>{{ $tx->remaining_quantity !== null ? number_format((float)$tx->remaining_quantity, 2) : '—' }}</td>
                            <td class="text-muted small">{{ $tx->transaction_price !== null ? '₱' . number_format((float)$tx->transaction_price, 2) : '—' }}</td>
                            <td>
                                @if($tx->status === 'pending')
                                <span class="badge-status badge-pending">PENDING</span>
                                @if($tx->notes)
                                <div class="small text-muted mt-1">{{ $tx->notes }}</div>
                                @endif
                                @else
                                <span class="badge-status badge-approved">APPROVED</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $tx->reason ?? '—' }}</td>
                            <td class="text-muted small">{{ $tx->creator?->name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 14 : 13 }}" class="text-center text-muted py-4">No transactions found.</td></tr>
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
