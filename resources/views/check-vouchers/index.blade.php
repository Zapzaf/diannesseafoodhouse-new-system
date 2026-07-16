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

                @php
                    $money = fn ($value) => (float) $value > 0 ? '₱'.number_format((float) $value, 2) : '—';
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" style="min-width: 1400px;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>CV #</th>
                                <th>APV #</th>
                                <th>Payee</th>
                                <th class="text-end">Amount w/ VAT</th>
                                <th class="text-end">VAT</th>
                                <th class="text-end">Net Purchases</th>
                                <th class="text-end">VAT-Exempt</th>
                                <th class="text-end">Non-VAT</th>
                                <th class="text-end">EWT</th>
                                <th class="text-end">Amount Paid</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $voucher)
                            @php $hasApv = $voucher->purchase_voucher_id !== null; @endphp
                            <tr>
                                <td class="text-nowrap">{{ $voucher->date->format('M d, Y') }}</td>
                                <td class="fw-semibold text-nowrap">
                                    {{ $voucher->cv_no }}
                                    <div class="small text-muted fw-normal">{{ $voucher->branch?->name ?? '—' }} · {{ ucwords(str_replace('_', ' ', $voucher->type)) }}</div>
                                </td>
                                <td class="text-nowrap">
                                    @if($hasApv)
                                        <a href="{{ route('purchase-vouchers.show', $voucher->purchase_voucher_id) }}">{{ $voucher->purchaseVoucher?->apv_no ?? '#'.$voucher->purchase_voucher_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $voucher->payee_name }}</td>
                                <td class="text-end text-nowrap">{{ $money($voucher->amount_w_vat) }}</td>
                                {{-- APV-paid rows keep their VAT breakdown on the APV, per the disbursement book --}}
                                <td class="text-end text-nowrap">{{ $hasApv ? '—' : $money($voucher->vat) }}</td>
                                <td class="text-end text-nowrap">{{ $hasApv ? '—' : $money($voucher->net_purchases) }}</td>
                                <td class="text-end text-nowrap">{{ $hasApv ? '—' : $money($voucher->vat_exempt) }}</td>
                                <td class="text-end text-nowrap">{{ $hasApv ? '—' : $money($voucher->non_vat_purchase) }}</td>
                                <td class="text-end text-nowrap">
                                    {{ $money($voucher->ewt_amount) }}
                                    @if((float) $voucher->ewt_rate > 0)
                                    <div class="small text-muted">{{ rtrim(rtrim(number_format((float) $voucher->ewt_rate * 100, 2), '0'), '.') }}%</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap fw-semibold">₱{{ number_format($voucher->amount_paid, 2) }}</td>
                                <td>
                                    <span class="badge {{ match($voucher->status) { 'issued' => 'bg-primary-soft text-primary', 'cleared' => 'bg-success-soft text-success', 'voided' => 'bg-secondary-soft text-secondary', default => 'bg-warning-soft text-warning' } }}">
                                        {{ ucfirst($voucher->status) }}
                                    </span>
                                    @if($voucher->checkRegisterEntry)
                                    <div class="small text-muted mt-1">Check {{ $voucher->checkRegisterEntry->check_no }}</div>
                                    @endif
                                </td>
                                <td class="text-muted small" style="max-width: 180px;">
                                    <span class="d-inline-block text-truncate" style="max-width: 170px;" title="{{ $voucher->remarks }}">{{ $voucher->remarks ?: '—' }}</span>
                                </td>
                                <td class="table-actions-cell text-nowrap">
                                    <a href="{{ route('check-vouchers.show', $voucher) }}" class="btn btn-sm btn-outline-secondary"><i data-lucide="eye"></i></a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="14" class="text-center text-muted py-4">No check vouchers found.</td></tr>
                            @endforelse
                        </tbody>
                        @if($vouchers->total() > 0)
                        <tfoot>
                            <tr class="table-warning fw-bold">
                                <td colspan="4" class="text-end">SUB-TOTAL ({{ $vouchers->total() }} CVs)</td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) $totals->amount_w_vat, 2) }}</td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) $totals->vat, 2) }}</td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) $totals->net_purchases, 2) }}</td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) $totals->vat_exempt, 2) }}</td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) $totals->non_vat_purchase, 2) }}</td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) $totals->ewt_amount, 2) }}</td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) $totals->amount_paid, 2) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            @if($vouchers->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $vouchers->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
