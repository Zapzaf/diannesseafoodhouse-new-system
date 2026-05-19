@extends('layouts.app')
@section('page_title', 'Scrap Materials - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Scrap Materials" subtitle="Wastage reports from production — losses and conversions" icon="trash-2">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i data-feather="trash-2" class="me-1"></i> Wastage Reports</div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Production Order</th>
                            <th>Items Wasted</th>
                            <th>Filed By</th>
                            <th class="table-actions-head">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="text-nowrap text-muted small">{{ $report->created_at->format('M d, Y H:i') }}</td>
                            <td>{{ $report->branch?->name ?? '—' }}</td>
                            <td>
                                @if($report->productionOrder)
                                    <a href="{{ route('productions.show', $report->productionOrder) }}" class="text-decoration-none">
                                        Production #{{ $report->production_order_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $report->items->count() }} item(s)</td>
                            <td class="text-muted small">{{ $report->creator?->name ?? '—' }}</td>
                            <td class="table-actions-cell">
                                <button type="button" class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#wasteModal{{ $report->id }}">
                                    View
                                </button>
                            </td>
                        </tr>

                        {{-- Detail Modal --}}
                        <div class="modal fade" id="wasteModal{{ $report->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Wastage Report — {{ $report->branch?->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted small mb-3">
                                            Filed by <strong>{{ $report->creator?->name ?? 'N/A' }}</strong>
                                            on {{ $report->created_at->format('M d, Y H:i') }}
                                        </p>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-secondary">
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Qty Lost</th>
                                                        <th>Reason</th>
                                                        <th>Converted To</th>
                                                        <th>Converted Qty</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($report->items as $wi)
                                                    <tr>
                                                        <td>{{ $wi->item?->name ?? '—' }}</td>
                                                        <td>{{ number_format($wi->quantity_lost, 2) }} {{ $wi->item?->unit }}</td>
                                                        <td class="text-muted small">{{ $wi->reason ?? '—' }}</td>
                                                        <td>{{ $wi->convertedItem?->name ?? '—' }}</td>
                                                        <td>
                                                            @if($wi->convertedItem)
                                                                {{ number_format($wi->converted_quantity, 2) }} {{ $wi->convertedItem->unit }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No wastage reports found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($reports->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $reports->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
</main>
@endsection
