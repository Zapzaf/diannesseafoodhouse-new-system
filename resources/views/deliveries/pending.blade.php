@extends('layouts.app')
@section('page_title', 'Pending Deliveries - Dianne Seafood House')
@section('content')
<x-page-header title="Pending Deliveries" subtitle="Deliveries awaiting approval and allocation" icon="clock">
    <a href="{{ route('deliveries.index') }}" class="btn btn-light text-primary">
        <i data-lucide="arrow-left" class="me-1"></i> All Deliveries
    </a>
    <a href="{{ route('deliveries.create') }}" class="btn btn-primary">
        <i data-lucide="plus" class="me-1"></i> Log Delivery
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    @if($deliveries->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i data-lucide="check-circle" class="text-success mb-3" style="width:48px;height:48px;"></i>
            <h5 class="text-success">No pending deliveries!</h5>
            <p class="text-muted mb-0">All deliveries have been reviewed and approved.</p>
        </div>
    </div>
    @else
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i data-lucide="alert-triangle" class="me-2 flex-shrink-0"></i>
        <div><strong>{{ $deliveries->total() }} delivery/deliveries</strong> pending approval.</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="clock" class="me-1"></i> Pending Approval</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-server-page-sort="1">
                    <thead>
                        <tr>
                            <th data-sort-key="reference_number">Reference</th>
                            <th data-sort-key="supplier_name">Supplier / Source</th>
                            <th data-sort-key="destination_branch">Destination Branch</th>
                            <th data-sort-key="items_count">Items</th>
                            <th data-sort-key="created_at">Date</th>
                            <th>Created By</th>
                            <th data-sort-key="status">Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                        <tr>
                            <td class="fw-semibold">{{ $delivery->reference_number }}</td>
                            <td>
                                @if($delivery->sourceBranch)
                                    {{ $delivery->sourceBranch->name }}
                                @elseif($delivery->supplier)
                                    {{ $delivery->supplier->name }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $delivery->destinationBranch?->name ?? 'N/A' }}</td>
                            <td>{{ $delivery->items->count() }}</td>
                            <td class="text-muted small">{{ $delivery->created_at->format('M d, Y H:i') }}</td>
                            <td>{{ $delivery->creator?->name ?? 'System' }}</td>
                            <td><span class="badge-status badge-pending">PENDING</span></td>
                            <td class="table-actions-cell text-nowrap">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-sm btn-info text-white">
                                    <i data-lucide="eye" class="me-1"></i> View
                                </a>
                                @can('approve', $delivery)
                                <form method="POST" action="{{ route('deliveries.approve', $delivery) }}" class="d-inline">
                                    @csrf
                                    @foreach($delivery->items as $index => $item)
                                    <input type="hidden" name="items[{{ $index }}][delivery_item_id]" value="{{ $item->id }}">
                                    <input type="hidden" name="items[{{ $index }}][allocated_to]" value="{{ $item->allocated_to ?? 'inventory' }}">
                                    @endforeach
                                    <button type="submit" class="btn btn-sm btn-success text-white">
                                        <i data-lucide="check" class="me-1"></i> Approve
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($deliveries->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $deliveries->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
