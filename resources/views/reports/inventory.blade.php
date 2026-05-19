@extends('layouts.app')
@section('page_title', 'Inventory Report - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Inventory Report" subtitle="Current stock levels across all items and locations" icon="bar-chart-2">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    {{-- Summary Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-feather="package" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Items</div>
                        <div class="fs-4 fw-bold">{{ number_format($totalItems) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-feather="layers" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Quantity</div>
                        <div class="fs-4 fw-bold">{{ number_format($totalQuantity, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i data-feather="alert-triangle" class="text-warning" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Low Stock Items</div>
                        <div class="fs-4 fw-bold text-warning">{{ number_format($lowStockCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Low Stock Alert --}}
    @if($lowStockItems->isNotEmpty())
    <div class="card shadow-sm mb-4 border-warning">
        <div class="card-header text-warning fw-semibold">
            <i data-feather="alert-triangle" class="me-1"></i> Low Stock Items ({{ $lowStockItems->count() }})
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Item</th>
                            <th>Branch</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Threshold</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lowStockItems as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->branch?->name ?? '—' }}</td>
                            <td>{{ $item->category?->location?->name ?? '—' }}</td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td class="text-danger fw-bold">{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                            <td class="text-muted">{{ number_format($item->low_stock_threshold, 2) }} {{ $item->unit }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- All Items --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-feather="archive" class="me-1"></i> All Items — Stock Levels</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Branch</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            <th>Threshold</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->branch?->name ?? '—' }}</td>
                            <td>{{ $item->category?->location?->name ?? '—' }}</td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td class="text-muted">{{ $item->unit }}</td>
                            <td class="fw-semibold">{{ number_format($item->quantity, 2) }}</td>
                            <td class="text-muted">{{ number_format($item->low_stock_threshold, 2) }}</td>
                            <td>
                                @if($item->quantity == 0)
                                    <span class="badge-status badge-expired">OUT OF STOCK</span>
                                @elseif($item->quantity <= $item->low_stock_threshold)
                                    <span class="badge-status badge-pending">LOW STOCK</span>
                                @else
                                    <span class="badge-status badge-active">OK</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No items found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>
@endsection

