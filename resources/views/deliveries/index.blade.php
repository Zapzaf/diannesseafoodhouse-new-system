@extends('layouts.app')

@section('page_title', 'Deliveries - Dianne Seafood House')

@section('content')
    <x-page-header title="Deliveries" subtitle="Track all incoming deliveries" icon="truck">
        <a class="btn btn-primary" href="{{ route('deliveries.create') }}">
            <i data-lucide="plus-circle" class="me-1"></i> Log Delivery
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>Delivery Log</div>
                <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                    <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="5"  {{ request('per_page', 10) == 5  ? 'selected' : '' }}>5</option>
                        <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page', 10) == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                        <button class="btn btn-secondary text-white" type="submit" aria-label="Search"><i data-lucide="search" style="width: 14px; height: 14px;"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" data-server-page-sort="1">
                    <thead>
                        <tr>
                            <th data-sort-key="reference_number">Reference</th>
                            <th data-sort-key="supplier_name">Supplier</th>
                            @if(auth()->user()?->isAdmin())
                            <th data-sort-key="destination_branch">Destination Branch</th>
                            <th data-sort-key="source_branch">Source Branch</th>
                            @endif
                            <th data-sort-key="items_count">Items</th>
                            <th data-sort-key="total_cost">Total Cost</th>
                            <th data-sort-key="created_at">Date</th>
                            <th data-sort-key="status">Status</th>
                            <th class="table-actions-head">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveries as $delivery)
                        <tr>
                            <td class="fw-semibold">{{ $delivery->reference_number }}</td>
                            <td>
                                @if($delivery->sourceBranch)
                                    {{ $delivery->sourceBranch->name }}
                                @elseif($delivery->supplier)
                                    {{ $delivery->supplier->name }}
                                @else
                                    <span class="badge bg-info text-dark">From Supplier</span>
                                @endif
                            </td>
                            @if(auth()->user()?->isAdmin())
                            <td>{{ $delivery->destinationBranch?->name ?? '—' }}</td>
                            <td>
                                @if($delivery->sourceBranch)
                                    {{ $delivery->sourceBranch->name }}
                                @else
                                    {!! $delivery->supplier ? e($delivery->supplier->name) : '<span class="badge bg-info text-dark">From Supplier</span>' !!}
                                @endif
                            </td>
                            @endif
                            <td>{{ $delivery->items->count() }}</td>
                            <td class="fw-semibold">&#8369;{{ number_format($delivery->items->sum(fn($i) => (float) ($i->price ?? 0)), 2) }}</td>
                            <td class="text-muted small">{{ $delivery->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                @php
                                    $statusLabels = ['pending' => 'PENDING REVIEW', 'received' => 'APPROVED', 'rejected' => 'REJECTED'];
                                    $statusClass = $delivery->status === 'rejected' ? 'badge-expired' : 'badge-'.$delivery->status;
                                @endphp
                                <span class="badge-status {{ $statusClass }}">{{ $statusLabels[$delivery->status] ?? strtoupper($delivery->status) }}</span>
                            </td>
                            <td class="table-actions-cell text-nowrap">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-sm btn-info text-white">
                                    <i data-lucide="eye" style="width:14px;height:14px;" class="me-1"></i> View
                                </a>
                                @can('delete', $delivery)
                                <form action="{{ route('deliveries.destroy', $delivery) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete delivery {{ $delivery->reference_number }}?\n\nThis will reverse ALL inventory changes from this delivery, and delete any linked production records (including finished ones) after reversing their inputs, outputs, and scrap.\n\nThis cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger text-white" title="Delete delivery and reverse inventory">
                                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->isAdmin() ? 9 : 7 }}" class="text-center text-muted py-4">No deliveries recorded yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Showing {{ $deliveries->firstItem() ?? 0 }} to {{ $deliveries->lastItem() ?? 0 }} of {{ $deliveries->total() }} entries
                </div>
                <div class="mb-0 custom-pagination-wrapper">
                    {{ $deliveries->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
            <style>
                .custom-pagination-wrapper nav { margin-bottom: 0 !important; }
                .custom-pagination-wrapper p.small.text-muted { display: none !important; }
                .custom-pagination-wrapper .pagination { margin-bottom: 0 !important; }
            </style>
    </div>
@endsection
