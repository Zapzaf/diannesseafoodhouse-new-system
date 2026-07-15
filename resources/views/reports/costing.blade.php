@extends('layouts.app')
@section('page_title', 'Costing Reports - Dianne Seafood House')
@section('content')
<x-page-header title="Costing Reports" subtitle="Price change requests, approvals, and audit history" icon="dollar-sign">
    <a href="{{ route('reports.costing.create') }}" class="btn btn-light">
        <i data-lucide="plus" class="me-1"></i> New Costing Report
    </a>
</x-page-header>

@php
    $pendingCount = (int) ($statusCounts['pending'] ?? 0);
    $approvedCount = (int) ($statusCounts['approved'] ?? 0);
    $rejectedCount = (int) ($statusCounts['rejected'] ?? 0);
@endphp

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i data-lucide="clock" class="text-warning" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending</div>
                        <div class="fs-4 fw-bold text-warning">{{ number_format($pendingCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-lucide="check-circle" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Approved</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($approvedCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i data-lucide="x-circle" class="text-danger" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Rejected</div>
                        <div class="fs-4 fw-bold text-danger">{{ number_format($rejectedCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3" style="background-color: rgba(240, 124, 89, 0.1) !important;">
                        <i data-lucide="file-text" class="text-primary" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Reports Shown</div>
                        <div class="fs-4 fw-bold">{{ number_format($reports->total()) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.costing.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <i data-lucide="clipboard" class="me-1"></i> Report History
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Branch</th>
                            <th class="text-end">Price Change</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Requested By</th>
                            <th>Reviewed By</th>
                            <th>Submitted</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                        @php $delta = (float) $report->proposed_price - (float) $report->current_price; @endphp
                        <tr>
                            <td class="text-muted small">{{ $report->id }}</td>
                            <td class="fw-semibold">
                                {{ $report->item?->name ?? 'Deleted item' }}
                                <div class="small text-muted">{{ $report->item?->category?->name ?? 'N/A' }}</div>
                            </td>
                            <td class="text-muted small">{{ $report->branch?->name ?? 'N/A' }}</td>
                            <td class="text-end text-nowrap">
                                <span class="text-muted small">&#8369;{{ number_format((float) $report->current_price, 4) }}</span>
                                <i data-lucide="arrow-right" class="mx-1 text-muted" style="width:12px;height:12px;"></i>
                                <span class="fw-semibold">&#8369;{{ number_format((float) $report->proposed_price, 4) }}</span>
                                <div class="small fw-semibold {{ $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-muted') }}">
                                    {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 4) }}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary">{{ $report->reasonTypeLabel() }}{{ $report->reference_id ? ' #'.$report->reference_id : '' }}</span>
                            </td>
                            <td>
                                @if($report->status === 'approved')
                                    <span class="badge-status badge-approved">APPROVED</span>
                                @elseif($report->status === 'rejected')
                                    <span class="badge-status badge-expired">REJECTED</span>
                                @else
                                    <span class="badge-status badge-pending">PENDING</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $report->requester?->name ?? 'N/A' }}</td>
                            <td class="text-muted small">{{ $report->approver?->name ?? '—' }}</td>
                            <td class="text-muted small text-nowrap">{{ $report->created_at?->format('M d, Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('reports.costing.show', $report) }}" class="btn btn-sm btn-outline-primary" title="View report">
                                    <i data-lucide="eye" style="width:15px;height:15px;"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No costing reports found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                Showing {{ $reports->firstItem() ?? 0 }} to {{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} entries
            </div>
            <div class="mb-0 custom-pagination-wrapper">
                {{ $reports->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i data-lucide="archive" class="me-1"></i> Current Item Cost Reference
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Item</th>
                            <th>Branch</th>
                            <th class="text-end">Current Unit Price</th>
                            <th class="text-end">Latest Approved Unit Cost</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                        <tr>
                            <td class="fw-semibold">
                                {{ $item->name }}
                                <div class="small text-muted">{{ $item->category?->location?->name ?? 'N/A' }} / {{ $item->category?->name ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $item->branch?->name ?? 'N/A' }}</td>
                            <td class="text-end">&#8369;{{ number_format((float) ($item->unit_price ?? 0), 4) }}</td>
                            <td class="text-end">
                                {!! $item->latest_unit_cost !== null ? '&#8369;' . number_format((float) $item->latest_unit_cost, 4) : '-' !!}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('reports.costing.create', ['item_id' => $item->id]) }}" class="btn btn-sm btn-outline-primary">
                                    <i data-lucide="file-plus" class="me-1" style="width:14px;height:14px;"></i>Request Change
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No items found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
