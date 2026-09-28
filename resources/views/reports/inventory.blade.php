@extends('layouts.app')
@section('page_title', 'Inventory Report - Dianne Seafood House')
@section('content')
<x-page-header title="Inventory Report" subtitle="Current stock levels across all items and locations" icon="bar-chart-2">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Summary Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-lucide="package" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Items</div>
                        <div class="fs-4 fw-bold">{{ number_format($totalItems) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-lucide="layers" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Quantity</div>
                        <div class="fs-4 fw-bold">{{ number_format($totalQuantity, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i data-lucide="wallet" class="text-info" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Inventory Value <span class="text-nowrap">(as of {{ \Illuminate\Support\Carbon::parse($asOfDate)->format('M d, Y') }})</span></div>
                        <div class="fs-4 fw-bold">₱{{ number_format($totalValueAsOf, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i data-lucide="alert-triangle" class="text-warning" style="width:24px;height:24px;"></i>
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
    <div class="card shadow-sm mb-4 border-warning" data-static-pagination="1">
        <div class="card-header text-warning fw-semibold">
            <i data-lucide="alert-triangle" class="me-1"></i> Low Stock Items ({{ $lowStockItems->count() }})
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
    <div class="card shadow-sm" data-static-pagination="1">
        <div class="card-header fw-semibold"><i data-lucide="archive" class="me-1"></i> All Items — Stock Levels as of {{ \Illuminate\Support\Carbon::parse($asOfDate)->format('M d, Y') }}</div>
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control" max="{{ now()->toDateString() }}" value="{{ $asOfDate }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">View</button>
                </div>
            </form>
            @if($asOfDate !== now()->toDateString())
            <div class="alert alert-info small py-2">Showing inventory as of <strong>{{ \Illuminate\Support\Carbon::parse($asOfDate)->format('M d, Y') }}</strong>. <a href="{{ route('reports.inventory.index') }}" class="alert-link">Back to today</a>.</div>
            @endif
            <p class="form-text text-muted">Unit cost is the most recent purchase price on or before this date; items never purchased before then show today's current cost instead.</p>
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
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($snapshotItemsPage as $index => $item)
                        <tr>
                            <td>{{ $snapshotItemsPage->firstItem() + $index }}</td>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->branch?->name ?? '—' }}</td>
                            <td>{{ $item->category?->location?->name ?? '—' }}</td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td class="text-muted">{{ $item->unit }}</td>
                            <td class="fw-semibold">{{ number_format($item->quantity_as_of, 2) }}</td>
                            <td class="text-muted">{{ number_format($item->low_stock_threshold, 2) }}</td>
                            <td class="text-end">₱{{ number_format($item->cost_as_of, 2) }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format($item->value_as_of, 2) }}</td>
                            <td>
                                @if($item->quantity_as_of <= 0)
                                    <span class="badge-status badge-expired">OUT OF STOCK</span>
                                @elseif($item->quantity_as_of <= $item->low_stock_threshold)
                                    <span class="badge-status badge-pending">LOW STOCK</span>
                                @else
                                    <span class="badge-status badge-active">OK</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">No items found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($snapshotItemsPage->hasPages())
        <div class="card-footer d-flex justify-content-center">{{ $snapshotItemsPage->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
</div>
@endsection

