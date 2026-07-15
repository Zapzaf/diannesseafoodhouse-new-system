@extends('layouts.app')
@section('page_title', 'Costing Report #' . $costingReport->id . ' - Dianne Seafood House')
@section('content')
<x-page-header :title="'Costing Report #' . $costingReport->id" :subtitle="$costingReport->item?->name ?? 'Deleted item'" icon="file-text">
    <a href="{{ route('reports.costing.index') }}" class="btn btn-light">
        <i data-lucide="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

@php
    $difference = (float) $costingReport->proposed_price - (float) $costingReport->current_price;
    $statusClass = $costingReport->status === 'approved' ? 'badge-approved' : ($costingReport->status === 'rejected' ? 'badge-expired' : 'badge-pending');
@endphp

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Status</div>
                    <div class="mt-2"><span class="badge-status {{ $statusClass }}">{{ strtoupper($costingReport->status) }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Current Price</div>
                    <div class="fs-4 fw-bold">&#8369;{{ number_format((float) $costingReport->current_price, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Proposed Price</div>
                    <div class="fs-4 fw-bold text-primary">&#8369;{{ number_format((float) $costingReport->proposed_price, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Difference</div>
                    <div class="fs-4 fw-bold {{ $difference >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $difference >= 0 ? '+' : '' }}&#8369;{{ number_format($difference, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold"><i data-lucide="clipboard" class="me-1"></i> Report Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Item</dt>
                        <dd class="col-sm-8">{{ $costingReport->item?->name ?? 'Deleted item' }}</dd>
                        <dt class="col-sm-4">Branch</dt>
                        <dd class="col-sm-8">{{ $costingReport->branch?->name ?? 'N/A' }}</dd>
                        <dt class="col-sm-4">Category</dt>
                        <dd class="col-sm-8">{{ $costingReport->item?->category?->name ?? 'N/A' }}</dd>
                        <dt class="col-sm-4">Requested By</dt>
                        <dd class="col-sm-8">{{ $costingReport->requester?->name ?? 'N/A' }}</dd>
                        <dt class="col-sm-4">Submitted</dt>
                        <dd class="col-sm-8">{{ $costingReport->created_at?->format('M d, Y H:i') }}</dd>
                        <dt class="col-sm-4">Reviewed By</dt>
                        <dd class="col-sm-8">{{ $costingReport->approver?->name ?? '-' }}</dd>
                        <dt class="col-sm-4">Reviewed At</dt>
                        <dd class="col-sm-8">{{ $costingReport->approved_at?->format('M d, Y H:i') ?? '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <div><i data-lucide="message-square" class="me-1"></i> Justification</div>
                    <span class="badge bg-primary bg-opacity-10 text-primary">{{ $costingReport->reasonTypeLabel() }}{{ $costingReport->reference_id ? ' #'.$costingReport->reference_id : '' }}</span>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-line;">{{ $costingReport->reason }}</p>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold"><i data-lucide="paperclip" class="me-1"></i> Supporting Documents</div>
                <div class="card-body">
                    @forelse($costingReport->attachments as $attachment)
                    <a href="{{ $attachment->url() }}" target="_blank" rel="noopener"
                       class="d-flex justify-content-between align-items-center text-decoration-none border rounded-3 px-3 py-2 mb-2">
                        <span class="d-flex align-items-center gap-2 text-truncate">
                            <i data-lucide="file" class="text-primary flex-shrink-0" style="width:16px;height:16px;"></i>
                            <span class="text-truncate">{{ $attachment->original_name }}</span>
                        </span>
                        <span class="text-muted small text-nowrap ms-3 d-inline-flex align-items-center gap-2 flex-shrink-0">
                            <span>{{ $attachment->humanSize() }}</span>
                            <i data-lucide="download" class="text-primary" style="width:15px;height:15px;"></i>
                        </span>
                    </a>
                    @empty
                    <p class="mb-0 text-muted">No supporting documents were uploaded.</p>
                    @endforelse
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Supporting Costing Details</div>
                <div class="card-body">
                    <p class="mb-0 text-muted" style="white-space: pre-line;">{{ $costingReport->costing_details ?: 'No supporting costing details were provided.' }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if(auth()->user()?->isAdmin() && $costingReport->isPending())
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold"><i data-lucide="shield" class="me-1"></i> Admin Review</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('reports.costing.approve', $costingReport) }}" class="mb-3">
                        @csrf
                        <label class="form-label fw-semibold">Approval Remarks</label>
                        <textarea name="approval_remarks" rows="3" class="form-control mb-3">{{ old('approval_remarks') }}</textarea>
                        <button type="submit" class="btn btn-success w-100">Approve and Update Price</button>
                    </form>

                    <form method="POST" action="{{ route('reports.costing.reject', $costingReport) }}">
                        @csrf
                        <label class="form-label fw-semibold">Rejection Remarks</label>
                        <textarea name="approval_remarks" rows="3" class="form-control @error('approval_remarks') is-invalid @enderror mb-3" required>{{ old('approval_remarks') }}</textarea>
                        @error('approval_remarks')<div class="invalid-feedback d-block mb-3">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-danger w-100">Reject Request</button>
                    </form>
                </div>
            </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-header fw-semibold"><i data-lucide="check-square" class="me-1"></i> Review Result</div>
                <div class="card-body">
                    <div class="text-muted small mb-2">Remarks</div>
                    <p class="mb-0" style="white-space: pre-line;">{{ $costingReport->approval_remarks ?: 'No review remarks yet.' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
