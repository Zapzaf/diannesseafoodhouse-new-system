@extends('layouts.app')

@section('page_title', 'Deliveries - Dianne Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="truck"></i></div>
                            Deliveries
                        </h1>
                        <div class="page-header-subtitle">Track all incoming deliveries</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a class="btn btn-light text-primary" href="{{ route('deliveries.create') }}">
                            <i data-feather="plus-circle" class="me-1"></i> Log Delivery
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
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
                        <button class="btn btn-outline-secondary" type="submit"><i data-feather="search" style="width: 14px; height: 14px;"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Reference</th>
                            <th>Supplier</th>
                            @if(auth()->user()?->isAdmin())
                            <th>Destination Branch</th>
                            <th>Source Branch</th>
                            @endif
                            <th>Items</th>
                            <th>Total Cost</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
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
                                <span class="badge-status badge-{{ $delivery->status }}">{{ strtoupper($delivery->status) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary">
                                    <i data-feather="eye" style="width:14px;height:14px;" class="me-1"></i> View
                                </a>
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
    </div>
</main>
@endsection
