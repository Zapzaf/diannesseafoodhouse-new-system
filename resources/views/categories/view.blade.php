@extends('layouts.app')

@section('page_title', 'View Location Categories')

@section('content')
    <x-page-header title="{{ $location->name }}: Location &gt; Categories &gt; Items" subtitle="Branch: {{ $location->branch?->name ?? 'N/A' }}" icon="archive">
        <a href="{{ route('categories.all') }}" class="btn btn-outline-primary">
            <i data-lucide="arrow-left" class="me-1"></i> All Location Categories
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        @php
            $selectedCategoryName = $selectedCategoryId
                ? ($categoryOptions->firstWhere('id', $selectedCategoryId)?->name ?? 'Unknown Category')
                : 'All Categories';
        @endphp

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div><i class="me-1" data-lucide="tag"></i>Category Name: {{ $selectedCategoryName }}</div>
                <a href="{{ route('items.create', ['location_id' => $location->id, 'category_id' => $selectedCategoryId]) }}" class="btn btn-sm btn-primary">
                    <i data-lucide="plus-circle" class="me-1"></i> Add Item
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Beginning Item</th>
                                <th>Remaining Item</th>
                                <th>Unit</th>
                                <th>Low Stock Threshold</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            @php
                                $remaining = (float) $item->quantity;
                                $threshold = (float) $item->low_stock_threshold;
                            @endphp
                            <tr class="{{ $remaining <= $threshold ? 'table-warning' : '' }}">
                                <td class="fw-semibold">
                                    {{ $item->name }}
                                    @if($remaining <= $threshold)
                                    <span class="badge bg-danger ms-1">Low Stock</span>
                                    @endif
                                </td>
                                <td>{{ $item->category?->name ?? 'N/A' }}</td>
                                <td>{{ $item->category?->location?->name ?? 'N/A' }}</td>
                                <td>{{ number_format((float) $item->beginning_item, 2) }}</td>
                                <td>{{ number_format($remaining, 2) }}</td>
                                <td>{{ $item->unit ?? 'N/A' }}</td>
                                <td>{{ number_format($threshold, 2) }}</td>
                                <td>{{ $item->supplier_name ?? 'N/A' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No items found under this location/category.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
