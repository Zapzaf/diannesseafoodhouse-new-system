@extends('layouts.app')
@section('page_title', 'Purchase & Disbursement Summary')
@section('content')
    <x-page-header title="Purchase & Disbursement Summary" subtitle="Detailed disbursements across APV, PCV, CV, and the Check Register" icon="bar-chart-2">
        <a class="btn btn-success text-white" href="{{ route('reports.purchase-disbursement.summary.export', request()->query()) }}">
            <i data-lucide="file-spreadsheet" class="me-1"></i> Export to Excel
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <form method="GET" class="row g-2 align-items-end" id="summaryFilterForm">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Voucher #, payee, TIN, particulars..." value="{{ $search }}">
                    </div>
                    @include('reports.partials.period-filter', ['filters' => request()->only(['period', 'date_from', 'date_to']), 'hideWeekly' => true])
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="{{ route('reports.purchase-disbursement.summary') }}" class="btn btn-secondary text-white">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sub-totals per ledger --}}
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Purchase Vouchers (APV)</div>
                        <div class="fs-5 fw-bold">₱{{ number_format($apvTotals->total_purchases, 2) }}</div>
                        <div class="text-muted small mt-1">Net ₱{{ number_format($apvTotals->net_purchases, 2) }} + VAT ₱{{ number_format($apvTotals->vat, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Services</div>
                        <div class="fs-5 fw-bold">₱{{ number_format($serviceTotals->total_purchases, 2) }}</div>
                        <div class="text-muted small mt-1">Net ₱{{ number_format($serviceTotals->net_purchases, 2) }} + VAT ₱{{ number_format($serviceTotals->vat, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Petty Cash Vouchers (PCV)</div>
                        <div class="fs-5 fw-bold">₱{{ number_format($pcvTotals['total_purchases'], 2) }}</div>
                        <div class="text-muted small mt-1">Net ₱{{ number_format($pcvTotals['net_purchases'], 2) }} + VAT ₱{{ number_format($pcvTotals['vat'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Check Vouchers (CV)</div>
                        <div class="fs-5 fw-bold">₱{{ number_format($cvTotals->amount_paid, 2) }}</div>
                        <div class="text-muted small mt-1">EWT withheld ₱{{ number_format($cvTotals->ewt_amount, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Check Register</div>
                        <div class="fs-5 fw-bold">₱{{ number_format($checkRegisterTotal, 2) }}</div>
                        <div class="text-muted small mt-1">Total of checks issued in this period</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Total Vouchers</div>
                        <div class="fs-5 fw-bold">{{ number_format($rows->count()) }}</div>
                        <div class="text-muted small mt-1">Matching the current filters</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Grand Total</div>
                        <div class="fs-5 fw-bold text-primary">₱{{ number_format($rows->sum('amount'), 2) }}</div>
                        <div class="text-muted small mt-1">Sum of every row below</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Period</div>
                        <div class="fs-6 fw-bold">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detailed, row-per-voucher listing across every source --}}
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div><i class="me-1" data-lucide="list"></i> Detailed Disbursements</div>
                <div class="text-muted small">{{ number_format($rows->count()) }} {{ $rows->count() === 1 ? 'record' : 'records' }}</div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Voucher #</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Supplier / Payee</th>
                                <th>TIN</th>
                                <th>Particulars</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $row->voucher_no ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @php
                                        $typeBadge = match($row->voucher_type) {
                                            'APV' => 'bg-primary-soft text-primary',
                                            'CV' => 'bg-danger-soft text-danger',
                                            'PCV' => 'bg-warning-soft text-warning',
                                            'Check Register' => 'bg-success-soft text-success',
                                            default => 'bg-secondary-soft text-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $typeBadge }}">{{ $row->voucher_type }}</span>
                                </td>
                                <td class="text-nowrap text-muted small">{{ $row->date?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $row->payee ?? '—' }}</td>
                                <td class="text-muted small">{{ $row->tin ?? '—' }}</td>
                                <td class="text-muted small" style="max-width: 260px;">
                                    <span class="d-inline-block text-truncate" style="max-width: 260px;" title="{{ $row->particulars }}">{{ $row->particulars ?? '—' }}</span>
                                </td>
                                <td class="text-muted small">{{ $row->branch ?? '—' }}</td>
                                <td class="text-muted small">{{ $row->status ? ucwords(str_replace('_', ' ', $row->status)) : '—' }}</td>
                                <td class="text-end fw-semibold text-nowrap">₱{{ number_format($row->amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No disbursement records for this period.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="8" class="text-end">Grand Total</td>
                                <td class="text-end">₱{{ number_format($rows->sum('amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/reports-init.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    ReportUtils.initPeriodFilter('#summaryFilterForm');
});
</script>
@endpush
