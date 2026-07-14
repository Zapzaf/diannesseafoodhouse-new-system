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

    @if($items->total() === 0 && ! $locationId && ! $categoryId)
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i data-lucide="check-circle" class="text-success mb-3" style="width:48px;height:48px;"></i>
            <h5 class="text-success">All stock levels are healthy!</h5>
            <p class="text-muted mb-0">No items are currently at or below their low stock threshold.</p>
        </div>
    </div>
    @else
    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><i data-lucide="alert-triangle" class="me-1" style="width: 16px; height: 16px;"></i> Low Stock Items</div>
            <form method="GET" action="{{ route('items.low-stock') }}" class="d-flex gap-2 align-items-stretch flex-wrap">
                <select name="location_id" data-role="location-filter" class="form-select" style="width: auto;" onchange="this.form.elements['category_id'].selectedIndex = 0; this.form.submit()">
                    <option value="">All Locations</option>
                    @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((int) $locationId === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                <select name="category_id" data-role="category-filter" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $categoryId === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="per_page" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    @foreach([5, 10, 20, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int) request('per_page', 10) === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                @if($locationId || $categoryId)
                <a href="{{ route('items.low-stock') }}" class="btn btn-outline-secondary d-flex align-items-center py-0">Reset</a>
                @endif
            </form>
        </div>
        <form method="POST" action="{{ route('items.low-stock.send-email') }}" id="lowStockEmailForm">
            @csrf
            <div class="card-body">
                @if($items->total() > 0)
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <i data-lucide="alert-triangle" class="me-2 flex-shrink-0"></i>
                    <div><strong>{{ $items->total() }} item(s)</strong> require immediate restocking{{ $locationId || $categoryId ? ' for the selected filters' : '' }}.</div>
                </div>
                @endif
                <div id="bulkEmailBar" class="d-none mb-3">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="mail" class="me-1" style="width:16px;height:16px;"></i>
                        Send Low Stock Email (<span id="selectedCount">0</span>)
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 36px;" class="text-center align-middle" data-no-sort="1">
                                    <input type="checkbox" class="form-check-input m-0" id="selectAll" aria-label="Select all visible items" style="cursor: pointer;">
                                </th>
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
                            @forelse($items as $item)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input item-checkbox m-0" name="item_ids[]" value="{{ $item->id }}" aria-label="Select {{ $item->name }}" style="cursor: pointer;">
                                </td>
                                <td>{{ $items->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td class="text-muted small">{{ $item->branch?->name ?? '—' }}</td>
                                <td class="text-muted small">{{ $item->category?->location?->name ?? '—' }}</td>
                                <td class="text-muted small">{{ $item->category?->name ?? '—' }}</td>
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
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No low stock items match the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }} of {{ $items->total() }} entries
            </div>
            <div class="mb-0 custom-pagination-wrapper">
                {{ $items->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
        <style>
            .custom-pagination-wrapper nav { margin-bottom: 0 !important; }
        </style>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // The global AJAX-table system replaces the whole card when a filter,
    // per-page size, or pagination link changes. All listeners here are
    // delegated to `document` and query the DOM at event time, so the
    // selection UI keeps working across every swap.

    function refresh() {
        const boxes = Array.from(document.querySelectorAll('.item-checkbox'));
        const selectAll = document.getElementById('selectAll');
        const bulkBar = document.getElementById('bulkEmailBar');
        const countEl = document.getElementById('selectedCount');
        if (!bulkBar || !countEl) return;

        const selected = boxes.filter((box) => box.checked).length;
        countEl.textContent = selected;
        bulkBar.classList.toggle('d-none', selected === 0);
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && selected === boxes.length;
            selectAll.indeterminate = selected > 0 && selected < boxes.length;
        }
    }

    document.addEventListener('change', function (event) {
        if (event.target.id === 'selectAll') {
            document.querySelectorAll('.item-checkbox').forEach((box) => {
                box.checked = event.target.checked;
            });
            refresh();
        } else if (event.target.classList && event.target.classList.contains('item-checkbox')) {
            refresh();
        }
    });

    // Changing the location resets the category filter. Runs in the capture
    // phase so it executes before the AJAX-table handler serializes the form.
    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('[data-role="location-filter"]')) {
            const category = event.target.form && event.target.form.querySelector('[data-role="category-filter"]');
            if (category) category.selectedIndex = 0;
        }
    }, true);

    refresh();
});
</script>
@endpush
