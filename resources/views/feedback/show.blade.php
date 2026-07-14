@extends('layouts.app')
@section('page_title', 'Feedback Details - Dianne Seafood House')
@section('content')
<x-page-header title="Feedback #{{ $feedback->id }}" subtitle="Customer satisfaction response details" icon="message-square">
    <a href="{{ route('feedback.index') }}" class="btn btn-light text-primary">
        <i data-lucide="arrow-left" class="me-1"></i> Back to Feedback
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Branch</div>
                    <div class="fs-5 fw-bold">{{ $feedback->branch?->name ?? '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Customer</div>
                    <div class="fs-5 fw-bold">{{ $feedback->name ?: 'Anonymous' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Visit Date</div>
                    <div class="fs-5 fw-bold">{{ $feedback->date->format('M d, Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Average Rating</div>
                    <div class="fs-5 fw-bold {{ $feedback->average_rating >= 4 ? 'text-success' : ($feedback->average_rating >= 3 ? 'text-warning' : 'text-danger') }}">
                        {{ number_format($feedback->average_rating, 2) }} / 5
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i data-lucide="star" class="me-1"></i> Ratings</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <tbody>
                        @foreach($ratingFields as $field => $label)
                        <tr>
                            <th style="width: 40%;">{{ $label }}</th>
                            <td>
                                <span class="badge {{ $feedback->{$field} >= 4 ? 'bg-success' : ($feedback->{$field} >= 3 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                    {{ $feedback->{$field} }}/5
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="pencil" class="me-1"></i> Suggestions / Improvements</div>
        <div class="card-body">
            <p class="mb-0 {{ $feedback->improvements ? '' : 'text-muted' }}">{{ $feedback->improvements ?: 'No comments provided.' }}</p>
        </div>
    </div>
</div>
@endsection
