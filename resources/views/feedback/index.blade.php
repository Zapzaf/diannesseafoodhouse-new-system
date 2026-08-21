@extends('layouts.app')
@section('page_title', 'Customer Feedback - Dianne Seafood House')
@section('content')
<x-page-header title="Customer Feedback" subtitle="Collected customer satisfaction responses" icon="message-square">
    <a href="{{ route('feedback.create') }}" class="btn btn-light text-primary">
        <i data-lucide="plus" class="me-1"></i> Record Feedback
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Search --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('feedback.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Customer name or comments..." value="{{ request('search') }}">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="{{ route('feedback.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="message-square" class="me-1"></i> Feedback Entries</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Customer</th>
                            @foreach($ratingFields as $label)
                            <th class="text-center">{{ $label }}</th>
                            @endforeach
                            <th class="text-center">Average</th>
                            <th class="text-end table-actions-head">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($feedback as $entry)
                        <tr>
                            <td class="text-nowrap fw-semibold small">{{ $entry->id }}</td>
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
                            <td class="table-actions-cell text-end text-nowrap">
                                <a href="{{ route('feedback.show', $entry) }}" class="btn btn-sm btn-info text-white" title="View">
                                    <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                </a>
                                <form action="{{ route('feedback.destroy', $entry) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this feedback entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger text-white" title="Delete">
                                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ 6 + count($ratingFields) }}" class="text-center text-muted py-4">No feedback recorded yet.</td></tr>
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
