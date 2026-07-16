@extends('layouts.app')
@section('page_title', 'Check Vouchers (CV)')
@section('content')
    <x-page-header title="Check Vouchers (CV)" subtitle="Disbursements: petty cash replenishment, APV payments, and direct purchases" icon="banknote">
        <a href="{{ route('check-vouchers.create') }}" class="btn btn-primary">
            <i data-lucide="plus-circle" class="me-1"></i> New Check Voucher
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="banknote"></i> Disbursements</div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search CV # or payee" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="pcf_replenishment" {{ request('type') === 'pcf_replenishment' ? 'selected' : '' }}>PCF Replenishment</option>
                            <option value="apv_payment" {{ request('type') === 'apv_payment' ? 'selected' : '' }}>APV Payment</option>
                            <option value="cod_purchase" {{ request('type') === 'cod_purchase' ? 'selected' : '' }}>COD Purchase</option>
                            <option value="other_disbursement" {{ request('type') === 'other_disbursement' ? 'selected' : '' }}>Other Disbursement</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="issued" {{ request('status') === 'issued' ? 'selected' : '' }}>Issued</option>
                            <option value="cleared" {{ request('status') === 'cleared' ? 'selected' : '' }}>Cleared</option>
                            <option value="voided" {{ request('status') === 'voided' ? 'selected' : '' }}>Voided</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>CV #</th>
                                <th>Branch</th>
                                <th>Type</th>
                                <th>Payee</th>
                                <th class="text-end">Amount Paid</th>
                                <th>Status</th>
                                <th>Check #</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $voucher)
                            <tr>
                                <td>{{ $voucher->date->format('M d, Y') }}</td>
                                <td class="fw-semibold">{{ $voucher->cv_no }}</td>
                                <td class="text-muted small">{{ $voucher->branch?->name ?? '—' }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $voucher->type)) }}</td>
                                <td>{{ $voucher->payee_name }}</td>
                                <td class="text-end">₱{{ number_format($voucher->amount_paid, 2) }}</td>
                                <td>
                                    <span class="badge {{ match($voucher->status) { 'issued' => 'bg-primary-soft text-primary', 'cleared' => 'bg-success-soft text-success', 'voided' => 'bg-secondary-soft text-secondary', default => 'bg-warning-soft text-warning' } }}">
                                        {{ ucfirst($voucher->status) }}
                                    </span>
                                </td>
                                <td>{{ $voucher->checkRegisterEntry?->check_no ?? '—' }}</td>
                                <td class="table-actions-cell text-nowrap">
                                    <a href="{{ route('check-vouchers.show', $voucher) }}" class="btn btn-sm btn-outline-secondary"><i data-lucide="eye"></i></a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No check vouchers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($vouchers->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $vouchers->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
