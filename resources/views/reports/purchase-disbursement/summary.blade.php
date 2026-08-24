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
                        <input type="text" name="search" id="summarySearchFilter" class="form-control" placeholder="Voucher #, payee, TIN, particulars..." value="{{ $search }}">
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
                        <div class="fs-5 fw-bold">{{ number_format($rowCounts->count()) }}</div>
                        <div class="text-muted small mt-1">Matching the current filters</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase mb-2">Grand Total</div>
                        <div class="fs-5 fw-bold text-primary">₱{{ number_format($rowCounts->sum('amount'), 2) }}</div>
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

        {{-- Detailed, row-per-voucher listing — a separate, independently paginated
             table per voucher type/register, same AJAX pattern (IndexTableBridge)
             already used on the Costing Report's two tables. --}}
        @php
            $voucherSections = [
                'apv' => ['label' => 'Purchase Vouchers (APV)', 'icon' => 'file-text'],
                'cv' => ['label' => 'Check Vouchers (CV)', 'icon' => 'banknote'],
                'pcv' => ['label' => 'Petty Cash Vouchers (PCV)', 'icon' => 'wallet'],
                'check-register' => ['label' => 'Check Register', 'icon' => 'check-square'],
            ];
        @endphp

        @foreach($voucherSections as $slug => $section)
        @php
            $tableId = 'summary'.str()->studly($slug).'Table';
            $colCount = $slug === 'pcv' ? 9 : 8;
        @endphp
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div><i class="me-1" data-lucide="{{ $section['icon'] }}"></i> {{ $section['label'] }}</div>
                <div class="text-muted small" id="{{ $tableId }}Info"></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0" id="{{ $tableId }}">
                        <thead class="table-dark">
                            <tr>
                                <th>Voucher #</th>
                                <th>Date</th>
                                <th>Supplier / Payee</th>
                                <th>TIN</th>
                                <th>Particulars</th>
                                <th>Branch</th>
                                <th>Status</th>
                                @if($slug === 'pcv')
                                <th>Replenished By (CV #)</th>
                                @endif
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="{{ $tableId }}Body"><tr><td colspan="{{ $colCount }}" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-center">
                <nav aria-label="{{ $section['label'] }} pagination">
                    <ul id="{{ $tableId }}Pagination" class="pagination pagination-sm mb-0"></ul>
                </nav>
            </div>
        </div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/reports-init.js') }}"></script>
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    ReportUtils.initPeriodFilter('#summaryFilterForm');

    const sharedFilters = [
        { inputId: 'periodResolvedDateFrom', param: 'date_from' },
        { inputId: 'periodResolvedDateTo', param: 'date_to' },
    ];

    function renderVoucherRow(row, ctx) {
        const statusLabel = row.status ? String(row.status).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '—';
        return `
            <tr>
                <td class="fw-semibold text-nowrap">${ctx.escapeHtml(row.voucher_no || '—')}</td>
                <td class="text-nowrap text-muted small">${ctx.escapeHtml(row.date || '—')}</td>
                <td>${ctx.escapeHtml(row.payee || '—')}</td>
                <td class="text-muted small">${ctx.escapeHtml(row.tin || '—')}</td>
                <td class="text-muted small" style="max-width: 260px;">
                    <span class="d-inline-block text-truncate" style="max-width: 260px;" title="${ctx.escapeHtml(row.particulars || '')}">${ctx.escapeHtml(row.particulars || '—')}</span>
                </td>
                <td class="text-muted small">${ctx.escapeHtml(row.branch || '—')}</td>
                <td class="text-muted small">${ctx.escapeHtml(statusLabel)}</td>
                <td class="text-end fw-semibold text-nowrap">₱${Number(row.amount || 0).toFixed(2)}</td>
            </tr>
        `;
    }

    // PCV-only variant: adds the "Replenished By (CV #)" cross-reference
    // column so it's clear at a glance which Check Voucher reimbursed each
    // Petty Cash Voucher, without having to look it up separately.
    function renderPcvRow(row, ctx) {
        const statusLabel = row.status ? String(row.status).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '—';
        return `
            <tr>
                <td class="fw-semibold text-nowrap">${ctx.escapeHtml(row.voucher_no || '—')}</td>
                <td class="text-nowrap text-muted small">${ctx.escapeHtml(row.date || '—')}</td>
                <td>${ctx.escapeHtml(row.payee || '—')}</td>
                <td class="text-muted small">${ctx.escapeHtml(row.tin || '—')}</td>
                <td class="text-muted small" style="max-width: 260px;">
                    <span class="d-inline-block text-truncate" style="max-width: 260px;" title="${ctx.escapeHtml(row.particulars || '')}">${ctx.escapeHtml(row.particulars || '—')}</span>
                </td>
                <td class="text-muted small">${ctx.escapeHtml(row.branch || '—')}</td>
                <td class="text-muted small">${ctx.escapeHtml(statusLabel)}</td>
                <td class="text-muted small">${ctx.escapeHtml(row.linked_voucher || '—')}</td>
                <td class="text-end fw-semibold text-nowrap">₱${Number(row.amount || 0).toFixed(2)}</td>
            </tr>
        `;
    }

    IndexTableBridge.init({
        tableId: 'summaryApvTable',
        tbodyId: 'summaryApvTableBody',
        paginationId: 'summaryApvTablePagination',
        infoId: 'summaryApvTableInfo',
        stateKey: 'summaryApvTable',
        dataUrl: @json(route('reports.purchase-disbursement.summary.apv-data')),
        searchInputId: 'summarySearchFilter',
        filters: sharedFilters,
        emptyMessage: 'No Purchase Voucher (APV) records for this period.',
        colspan: 8,
        renderRow: renderVoucherRow,
    });

    IndexTableBridge.init({
        tableId: 'summaryCvTable',
        tbodyId: 'summaryCvTableBody',
        paginationId: 'summaryCvTablePagination',
        infoId: 'summaryCvTableInfo',
        stateKey: 'summaryCvTable',
        dataUrl: @json(route('reports.purchase-disbursement.summary.cv-data')),
        searchInputId: 'summarySearchFilter',
        filters: sharedFilters,
        emptyMessage: 'No Check Voucher (CV) records for this period.',
        colspan: 8,
        renderRow: renderVoucherRow,
    });

    IndexTableBridge.init({
        tableId: 'summaryPcvTable',
        tbodyId: 'summaryPcvTableBody',
        paginationId: 'summaryPcvTablePagination',
        infoId: 'summaryPcvTableInfo',
        stateKey: 'summaryPcvTable',
        dataUrl: @json(route('reports.purchase-disbursement.summary.pcv-data')),
        searchInputId: 'summarySearchFilter',
        filters: sharedFilters,
        emptyMessage: 'No Petty Cash Voucher (PCV) records for this period.',
        colspan: 9,
        renderRow: renderPcvRow,
    });

    IndexTableBridge.init({
        tableId: 'summaryCheckRegisterTable',
        tbodyId: 'summaryCheckRegisterTableBody',
        paginationId: 'summaryCheckRegisterTablePagination',
        infoId: 'summaryCheckRegisterTableInfo',
        stateKey: 'summaryCheckRegisterTable',
        dataUrl: @json(route('reports.purchase-disbursement.summary.check-register-data')),
        searchInputId: 'summarySearchFilter',
        filters: sharedFilters,
        emptyMessage: 'No Check Register records for this period.',
        colspan: 8,
        renderRow: renderVoucherRow,
    });
});
</script>
@endpush
