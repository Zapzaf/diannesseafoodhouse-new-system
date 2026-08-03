@extends('layouts.app')
@section('page_title', 'Purchase & Disbursement Summary')
@section('content')
    <x-page-header title="Purchase & Disbursement Summary" subtitle="Sub-totals per ledger, split by VAT / VAT-exempt / Non-VAT" icon="bar-chart-2">
        <a class="btn btn-success text-white" href="{{ route('reports.purchase-disbursement.summary.export', request()->query()) }}">
            <i data-lucide="file-spreadsheet" class="me-1"></i> Export to Excel
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <form method="GET" class="d-flex align-items-end gap-3 flex-wrap">
                    <div>
                        <label class="form-label small fw-semibold text-muted mb-1">From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                    </div>
                    <div>
                        <label class="form-label small fw-semibold text-muted mb-1">To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="file-text"></i> Purchase Vouchers (APV)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Net Purchases</th><th>VAT</th><th>VAT-Exempt</th><th>Non-VAT</th><th>Total Purchases</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>₱{{ number_format($apvTotals->net_purchases, 2) }}</td>
                                <td>₱{{ number_format($apvTotals->vat, 2) }}</td>
                                <td>₱{{ number_format($apvTotals->vat_exempt, 2) }}</td>
                                <td>₱{{ number_format($apvTotals->non_vat_purchase, 2) }}</td>
                                <td class="fw-bold">₱{{ number_format($apvTotals->total_purchases, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="file-text"></i> Services</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Net Purchases</th><th>VAT</th><th>VAT-Exempt</th><th>Non-VAT</th><th>Total</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>₱{{ number_format($serviceTotals->net_purchases, 2) }}</td>
                                <td>₱{{ number_format($serviceTotals->vat, 2) }}</td>
                                <td>₱{{ number_format($serviceTotals->vat_exempt, 2) }}</td>
                                <td>₱{{ number_format($serviceTotals->non_vat_purchase, 2) }}</td>
                                <td class="fw-bold">₱{{ number_format($serviceTotals->total_purchases, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="wallet"></i> Petty Cash Vouchers (PCV)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Net Purchases</th><th>VAT</th><th>VAT-Exempt</th><th>Non-VAT</th><th>Total Purchases</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>₱{{ number_format($pcvTotals['net_purchases'], 2) }}</td>
                                <td>₱{{ number_format($pcvTotals['vat'], 2) }}</td>
                                <td>₱{{ number_format($pcvTotals['vat_exempt'], 2) }}</td>
                                <td>₱{{ number_format($pcvTotals['non_vat_purchase'], 2) }}</td>
                                <td class="fw-bold">₱{{ number_format($pcvTotals['total_purchases'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="banknote"></i> Check Vouchers (CV) — Disbursements</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Net Purchases</th><th>VAT</th><th>VAT-Exempt</th><th>Non-VAT</th><th>EWT Withheld</th><th>Amount Paid</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>₱{{ number_format($cvTotals->net_purchases, 2) }}</td>
                                <td>₱{{ number_format($cvTotals->vat, 2) }}</td>
                                <td>₱{{ number_format($cvTotals->vat_exempt, 2) }}</td>
                                <td>₱{{ number_format($cvTotals->non_vat_purchase, 2) }}</td>
                                <td>₱{{ number_format($cvTotals->ewt_amount, 2) }}</td>
                                <td class="fw-bold">₱{{ number_format($cvTotals->amount_paid, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
