@extends('layouts.app')
@section('page_title', 'Delivery Report - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Delivery Report" subtitle="Delivery history with status breakdown" icon="truck">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.delivery.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="received" {{ $status === 'received' ? 'selected' : '' }}>Received</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.delivery.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i data-feather="clock" class="text-warning" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Deliveries</div>
                        <div class="fs-4 fw-bold text-warning">{{ number_format($pendingCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-feather="check-circle" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Received Deliveries</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($receivedCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-feather="truck" class="me-1"></i> Deliveries</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Destination</th>
                            <th>Source</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveries as $delivery)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="text-nowrap text-muted small">{{ $delivery->created_at->format('M d, Y H:i') }}</td>
                            <td class="fw-semibold text-nowrap">{{ $delivery->reference_number }}</td>
                            <td>{{ $delivery->destinationBranch?->name ?? '—' }}</td>
                            <td>
                                @if($delivery->sourceBranch)
                                    {{ $delivery->sourceBranch->name }}
                                @elseif($delivery->supplier)
                                    {{ $delivery->supplier->name }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $delivery->items->count() }} item(s)</td>
                            <td>
                                <span class="badge-status badge-{{ $delivery->status }}">{{ strtoupper($delivery->status) }}</span>
                            </td>
                            <td class="text-muted small">{{ $delivery->approver?->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $delivery->creator?->name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No deliveries in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($deliveries->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $deliveries->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
</main>
@endsection
