@extends('layouts.app')
@section('page_title', 'Feedback Report - Dianne Seafood House')
@section('content')
<x-page-header title="Feedback Report" subtitle="Customer satisfaction summary and trends" icon="message-square">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.feedback.index') }}" class="row g-2 align-items-end">
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
                    <a href="{{ route('reports.feedback.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i data-lucide="users" class="text-primary" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Responses</div>
                        <div class="fs-4 fw-bold">{{ number_format($totalResponses) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-lucide="star" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Overall Average Rating</div>
                        <div class="fs-4 fw-bold {{ ($overallAverage ?? 0) >= 4 ? 'text-success' : (($overallAverage ?? 0) >= 3 ? 'text-warning' : 'text-danger') }}">
                            {{ $overallAverage !== null ? number_format($overallAverage, 2).' / 5' : 'N/A' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Category averages --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold"><i data-lucide="bar-chart-2" class="me-1"></i> Average Rating by Category</div>
                <div class="card-body">
                    @forelse($averages as $field => $average)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">{{ $ratingFields[$field] }}</span>
                            <span class="text-muted">{{ $average !== null ? number_format($average, 2).' / 5' : 'N/A' }}</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar {{ ($average ?? 0) >= 4 ? 'bg-success' : (($average ?? 0) >= 3 ? 'bg-warning' : 'bg-danger') }}"
                                 role="progressbar" style="width: {{ $average !== null ? ($average / 5) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted mb-0">No data available.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold"><i data-lucide="pie-chart" class="me-1"></i> Overall Experience Distribution</div>
                <div class="card-body">
                    @for($rating = 5; $rating >= 1; $rating--)
                    @php
                        $count = $experienceDistribution[$rating] ?? 0;
                        $percent = $totalResponses > 0 ? ($count / $totalResponses) * 100 : 0;
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">{{ $rating }} star{{ $rating > 1 ? 's' : '' }}</span>
                            <span class="text-muted">{{ number_format($count) }} ({{ number_format($percent, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar {{ $rating >= 4 ? 'bg-success' : ($rating >= 3 ? 'bg-warning' : 'bg-danger') }}"
                                 role="progressbar" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Feedback Responses</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Customer</th>
                            @foreach($ratingFields as $label)
                            <th class="text-center">{{ $label }}</th>
                            @endforeach
                            <th class="text-center">Average</th>
                            <th>Improvements</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($feedback as $entry)
                        <tr>
                            <td class="text-nowrap text-muted small">{{ $entry->date->format('M d, Y') }}</td>
                            <td class="text-muted small">{{ $entry->branch?->name ?? '—' }}</td>
                            <td class="fw-semibold">{{ $entry->name ?: 'Anonymous' }}</td>
                            @foreach($ratingFields as $field => $label)
                            <td class="text-center">
                                <span class="badge {{ $entry->{$field} >= 4 ? 'bg-success' : ($entry->{$field} >= 3 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                    {{ $entry->{$field} }}/5
                                </span>
                            </td>
                            @endforeach
                            <td class="text-center fw-bold">{{ number_format($entry->average_rating, 2) }}</td>
                            <td class="text-muted small">{{ $entry->improvements ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ 5 + count($ratingFields) }}" class="text-center text-muted py-4">No feedback in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($feedback->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $feedback->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
