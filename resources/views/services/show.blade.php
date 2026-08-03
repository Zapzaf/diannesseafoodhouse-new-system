@extends('layouts.app')
@section('page_title', 'Service ' . $service->ref_no)
@section('content')
    <x-page-header title="Service {{ $service->ref_no }}" subtitle="Service expense details and payment history" icon="file-text">
        @if($service->status !== 'paid')
        <a href="{{ route('check-vouchers.create', ['pay_service' => $service->id]) }}" class="btn btn-success text-white">
            <i data-lucide="banknote" class="me-1"></i> Pay via Check Voucher
        </a>
        @endif
        <a href="{{ route('services.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Status</div>
                    <div class="h5 fw-bold mb-0">
                        <span class="badge {{ match($service->status) { 'paid' => 'bg-success-soft text-success', 'partially_paid' => 'bg-warning-soft text-warning', default => 'bg-danger-soft text-danger' } }}">
                            {{ ucwords(str_replace('_', ' ', $service->status)) }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Total (w/ VAT)</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($service->payable_total, 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Amount Paid</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format($service->amount_paid, 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm h-100">
                    <div class="small text-muted">Remaining Balance</div>
                    <div class="h5 fw-bold mb-0">₱{{ number_format(max($service->payable_total - $service->amount_paid, 0), 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="info"></i> Service Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="small text-muted">Date</div><div class="fw-semibold">{{ $service->date->format('M d, Y') }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Supplier</div><div class="fw-semibold">{{ $service->supplier?->name ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Payor</div><div class="fw-semibold">{{ $service->payor }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">SI #</div><div class="fw-semibold">{{ $service->si_no ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Expense Account</div><div class="fw-semibold">{{ $service->expenseAccount?->name }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Payment Type</div><div class="fw-semibold">{{ ucfirst($service->service_payment_type) }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Created By</div><div class="fw-semibold">{{ $service->creator?->name ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="small text-muted">Last Modified By</div><div class="fw-semibold">{{ $service->updater?->name ?? '—' }}</div></div>
                </div>
                @if($service->remarks)
                <div class="mt-3"><div class="small text-muted">Remarks</div><div>{{ $service->remarks }}</div></div>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="banknote"></i> Payments (Check Vouchers)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>CV #</th>
                                <th class="text-end">Amount w/ VAT</th>
                                <th class="text-end">Amount Paid</th>
                                <th>Status</th>
                                <th>Check #</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($service->checkVouchers as $cv)
                            <tr>
                                <td>{{ $cv->date->format('M d, Y') }}</td>
                                <td><a href="{{ route('check-vouchers.show', $cv) }}">{{ $cv->cv_no }}</a></td>
                                <td class="text-end">₱{{ number_format($cv->amount_w_vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($cv->amount_paid, 2) }}</td>
                                <td>{{ ucfirst($cv->status) }}</td>
                                <td>{{ $cv->checkRegisterEntry?->check_no ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('partials.attachments', ['attachmentType' => 'service', 'attachmentId' => $service->id, 'attachments' => $service->attachments])
    </div>
@endsection
