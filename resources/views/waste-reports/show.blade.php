@extends('layouts.app')

@section('page_title', 'Waste Report Details - Dianne Seafood House')

@section('content')
<main>
    <x-page-header :title="'Waste Report WR-' . str_pad((string) $wasteReport->id, 5, '0', STR_PAD_LEFT)" :subtitle="'Branch: ' . ($wasteReport->branch?->name ?? 'N/A')" icon="alert-triangle">
        <a href="{{ route('waste-reports.index') }}" class="btn btn-light text-primary">
            <i data-feather="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Report Summary</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Report Date</div>
                        <div class="fw-semibold">{{ $wasteReport->report_date->format('M d, Y') }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Filed By</div>
                        <div class="fw-semibold">{{ $wasteReport->creator?->name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Total Wasted Quantity</div>
                        <div class="fw-semibold">{{ number_format($wasteReport->items->sum(fn ($item) => (float) $item->quantity), 2) }}</div>
                    </div>
                </div>
                @if($wasteReport->remarks)
                    <hr>
                    <div class="text-muted small">Remarks</div>
                    <div>{{ $wasteReport->remarks }}</div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Wasted Items</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Reason</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wasteReport->items as $row)
                                <tr>
                                    <td>
                                        {{ $row->item?->name ?? 'Deleted item' }}
                                        <div class="small text-muted">
                                            #{{ $row->item_id }} - {{ $row->item?->category?->location?->name ?? 'N/A' }} / {{ $row->item?->category?->name ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td>{{ number_format((float) $row->quantity, 2) }} {{ $row->item?->unit }}</td>
                                    <td><span class="badge bg-danger-subtle text-danger">{{ $row->reason }}</span></td>
                                    <td class="text-muted small">{{ $row->notes ?: 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
