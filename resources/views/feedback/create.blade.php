@extends('layouts.app')
@section('page_title', 'Record Feedback - Dianne Seafood House')
@php
    $lockedBranchId = $selectedBranchId;
    $selectedBranch = old('branch_id', $selectedBranchId);
    $branchName = $lockedBranchId ? optional($branches->firstWhere('id', (int) $lockedBranchId))->name : null;
@endphp
@section('content')
<x-page-header title="Record Feedback" subtitle="Capture a customer satisfaction response" icon="plus-circle">
    <a href="{{ route('feedback.index') }}" class="btn btn-light text-primary">
        <i data-lucide="arrow-left" class="me-1"></i> Back to Feedback
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('feedback.store') }}" method="POST">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
                        @if($lockedBranchId)
                            <input type="hidden" name="branch_id" value="{{ $lockedBranchId }}">
                            <input type="text" class="form-control" value="{{ $branchName ?? 'Selected branch' }}" disabled>
                        @else
                            <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                                <option value="">Select Branch</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((int) $selectedBranch === (int) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('branch_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Customer Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Leave blank for anonymous">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Visit Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', now()->toDateString()) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    @foreach($ratingFields as $field => $label)
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ $label }} <span class="text-danger">*</span></label>
                        <select name="{{ $field }}" class="form-select @error($field) is-invalid @enderror" required>
                            <option value="">Select rating</option>
                            @for($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" {{ old($field) == $i ? 'selected' : '' }}>{{ $i }} - {{ ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'][$i] }}</option>
                            @endfor
                        </select>
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @endforeach
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Suggestions / Improvements</label>
                    <textarea name="improvements" rows="4" class="form-control @error('improvements') is-invalid @enderror" placeholder="What can we do better?">{{ old('improvements') }}</textarea>
                    @error('improvements')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Feedback</button>
                    <a href="{{ route('feedback.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
