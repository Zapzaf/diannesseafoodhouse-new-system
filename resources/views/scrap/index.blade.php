@extends('layouts.app')

@section('page_title', 'Scrap Materials - Dianne Seafood House')

@section('content')
<x-page-header title="Scrap Materials" subtitle="Production scrap and conversion records" icon="trash-2">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm scrap-table-card">
        <div class="card-header d-flex justify-content-between align-items-center gap-3">
            <div class="fw-semibold d-flex align-items-center gap-2">
                <i data-lucide="trash-2"></i>
                <span>Scrap Waste List</span>
            </div>
            <span class="badge bg-light text-muted border">{{ $scrapItems->total() }} record(s)</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 scrap-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Production</th>
                            <th>Scrap / Source</th>
                            <th>Qty Lost</th>
                            <th>Reason</th>
                            <th>Convert To</th>
                            <th>Convert Qty</th>
                            <th>Filed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scrapItems as $scrapItem)
                            @php
                                $report = $scrapItem->report;
                                $rowNumber = $scrapItems->firstItem() + $loop->index;
                                $sourceName = $scrapItem->scrap_name ?: $scrapItem->item?->name;
                                $isConversionOnly = ! $sourceName && $scrapItem->convertedItem;
                            @endphp
                            <tr>
                                <td class="text-muted small fw-semibold">{{ $rowNumber }}</td>
                                <td class="text-nowrap">
                                    <div class="fw-semibold">{{ $report?->created_at?->format('M d, Y') ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $report?->created_at?->format('h:i A') ?? '' }}</div>
                                </td>
                                <td>{{ $report?->branch?->name ?? 'N/A' }}</td>
                                <td>
                                    @if($report?->productionOrder)
                                        <a href="{{ route('productions.show', $report->productionOrder) }}" class="text-decoration-none fw-semibold">
                                            PROD-{{ $report->production_order_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $sourceName ?? 'Scrap Conversion' }}</div>
                                    @if($scrapItem->item)
                                        <div class="small text-muted">Item #{{ $scrapItem->item_id }}</div>
                                    @elseif($isConversionOnly)
                                        <div class="small text-muted">Conversion-only entry</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if($isConversionOnly)
                                        <span class="text-muted">N/A</span>
                                    @else
                                        {{ number_format((float) $scrapItem->quantity_lost, 2) }}
                                        <span class="text-muted">{{ $scrapItem->quantity_lost_unit ?? $scrapItem->item?->unit }}</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $scrapItem->reason ?: 'N/A' }}</td>
                                <td>
                                    @if($scrapItem->convertedItem)
                                        <div class="fw-semibold">{{ $scrapItem->convertedItem->name }}</div>
                                        <div class="small text-muted">Item #{{ $scrapItem->convert_to_item_id }}</div>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if($scrapItem->convertedItem)
                                        {{ number_format((float) $scrapItem->converted_quantity, 2) }}
                                        <span class="text-muted">{{ $scrapItem->convertedItem->unit }}</span>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $report?->creator?->name ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    No scrap waste records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($scrapItems->hasPages())
            <div class="card-footer d-flex justify-content-center">
                {{ $scrapItems->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
.scrap-table-card .card-header {
    background: var(--bg-card);
    border-bottom: 1px solid var(--border-color);
}

.scrap-table {
    min-width: 1120px;
}

.scrap-table thead th {
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-main);
    color: var(--text-muted);
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .04em;
    padding: .9rem 1rem;
    text-transform: uppercase;
    white-space: nowrap;
}

.scrap-table tbody td {
    border-color: var(--border-color);
    padding: .85rem 1rem;
    vertical-align: middle;
}

.scrap-table tbody tr:hover {
    background: rgba(var(--primary-color-rgb), .06);
}

.scrap-table [data-lucide] {
    height: 18px;
    width: 18px;
}
</style>
@endpush
