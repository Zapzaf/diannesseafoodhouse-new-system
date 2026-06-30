@extends('layouts.app')

@section('page_title', 'Waste Reports - Dianne Seafood House')

@section('content')
    <x-page-header title="Waste Reports" subtitle="Track unusable, expired, spoiled, or contaminated inventory" icon="alert-triangle">
        <a href="{{ route('waste-reports.create') }}" class="btn btn-light text-primary">
            <i data-lucide="plus" class="me-1"></i> Create Waste Report
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <div class="fw-semibold"><i data-lucide="alert-triangle" class="me-1"></i> Waste Reports</div>
                <form method="GET" class="d-flex gap-2">
                    <input type="search" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Search report, item, or reason">
                    <button class="btn btn-sm btn-primary" type="submit">Search</button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Branch</th>
                                <th>Items</th>
                                <th>Total Qty</th>
                                <th>Filed By</th>
                                <th class="table-actions-head">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports as $report)
                                <tr>
                                    <td>WR-{{ str_pad((string) $report->id, 5, '0', STR_PAD_LEFT) }}</td>
                                    <td class="text-nowrap">{{ $report->report_date->format('M d, Y') }}</td>
                                    <td>{{ $report->branch?->name ?? 'N/A' }}</td>
                                    <td>{{ $report->items->count() }} item(s)</td>
                                    <td>{{ number_format($report->items->sum(fn ($item) => (float) $item->quantity), 2) }}</td>
                                    <td class="text-muted small">{{ $report->creator?->name ?? 'N/A' }}</td>
                                    <td class="table-actions-cell">
                                        <a href="{{ route('waste-reports.show', $report) }}" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No waste reports found.</td>
                                </tr>
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
@endsection
