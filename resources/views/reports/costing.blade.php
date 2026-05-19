@extends('layouts.app')
@section('page_title', 'Costing Report - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Costing Report" subtitle="Latest approved unit costs per item" icon="dollar-sign">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.costing.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.costing.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i data-feather="dollar-sign" class="me-1"></i> Latest Unit Costs
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Branch</th>
                            <th class="text-end">Current Unit Price</th>
                            <th class="text-end">Latest Approved Unit Cost</th>
                            <th>Cost Source</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                        @php
                            $current = $item->unit_price !== null ? (float) $item->unit_price : null;
                            $latest = $item->latest_unit_cost !== null ? (float) $item->latest_unit_cost : null;
                        @endphp
                        <tr>
                            <td class="text-muted small">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">
                                #{{ $item->id }} - {{ $item->name }}
                                <div class="small text-muted">
                                    {{ $item->category?->location?->name ?? 'N/A' }} / {{ $item->category?->name ?? 'N/A' }}
                                </div>
                            </td>
                            <td>{{ $item->branch?->name ?? '—' }}</td>
                            <td class="text-end">
                                @if($current !== null)
                                    &#8369;{{ number_format($current, 4) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                @if($latest !== null)
                                    &#8369;{{ number_format($latest, 4) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-muted small">{{ $item->latest_cost_reason ?? '—' }}</td>
                            <td class="text-muted small">
                                {{ $item->latest_cost_date ? \Carbon\Carbon::parse($item->latest_cost_date)->format('M d, Y H:i') : '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No items found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>
@endsection

