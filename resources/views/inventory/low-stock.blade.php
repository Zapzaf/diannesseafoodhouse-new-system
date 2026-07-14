@extends('layouts.app')
@section('page_title', 'Low Stock Alerts - Dianne Seafood House')
@section('content')
<x-page-header title="Low Stock Alerts" subtitle="Items at or below their minimum threshold" icon="alert-triangle">
    <a href="{{ route('items.index') }}" class="btn btn-light text-primary">
        <i data-lucide="arrow-left" class="me-1"></i> Back to Items
    </a>
    <a href="{{ route('items.create') }}" class="btn btn-primary">
        <i data-lucide="plus-circle" class="me-1"></i> Add Item
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    @if($items->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i data-lucide="check-circle" class="text-success mb-3" style="width:48px;height:48px;"></i>
            <h5 class="text-success">All stock levels are healthy!</h5>
            <p class="text-muted mb-0">No items are currently at or below their low stock threshold.</p>
        </div>
    </div>
    @else
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i data-lucide="alert-triangle" class="me-2 flex-shrink-0"></i>
        <div><strong>{{ $items->total() }} item(s)</strong> require immediate restocking.</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><i data-lucide="alert-triangle" class="me-1"></i> Low Stock Items</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Branch</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Threshold</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        <tr>
                            <td>{{ $items->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->branch?->name ?? '—' }}</td>
                            <td>{{ $item->category?->location?->name ?? '—' }}</td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td>
                                <span class="fw-bold {{ $item->quantity == 0 ? 'text-danger' : 'text-warning' }}">
                                    {{ number_format($item->quantity, 2) }} {{ $item->unit }}
                                </span>
                            </td>
                            <td class="text-muted">{{ number_format($item->low_stock_threshold, 2) }} {{ $item->unit }}</td>
                            <td>
                                @if($item->quantity == 0)
                                    <span class="badge-status badge-expired">OUT OF STOCK</span>
                                @else
                                    <span class="badge-status badge-pending">LOW STOCK</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($items->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $items->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
