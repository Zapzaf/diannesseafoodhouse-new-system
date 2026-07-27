@extends('layouts.app')
@section('page_title', 'Menu Order Sales Report - Dianne\'s Seafood House')
@section('content')
<x-page-header title="Menu Order Sales Report" subtitle="BIR-style summary of menu order sales, discounts, VAT, and payments" icon="receipt">
    <div class="d-flex gap-2 d-print-none">
        <a href="{{ route('reports.menu-order-sales.export-excel', request()->query()) }}" class="btn btn-light text-primary">
            <i data-lucide="file-spreadsheet" class="me-1"></i> Export Excel
        </a>
        <a href="{{ route('reports.menu-order-sales.export-pdf', request()->query()) }}" class="btn btn-light text-primary">
            <i data-lucide="file-text" class="me-1"></i> Export PDF
        </a>
        <button type="button" class="btn btn-light text-primary" onclick="window.print()">
            <i data-lucide="printer" class="me-1"></i> Print
        </button>
    </div>
</x-page-header>

<div class="container-xl px-4" id="salesReportPrintArea">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4 d-print-none">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.menu-order-sales.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="">All Methods</option>
                        @foreach($paymentMethods as $paymentMethod)
                        <option value="{{ $paymentMethod }}" {{ $method === $paymentMethod ? 'selected' : '' }}>{{ ucfirst($paymentMethod) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.menu-order-sales.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 d-none d-print-flex">
        <div>
            <div class="fw-bold fs-5">Menu Order Sales Report</div>
            <div class="text-muted small">
                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
                &middot; Branch: {{ $selectedBranchId ? ($branches->firstWhere('id', $selectedBranchId)->name ?? 'Unknown') : 'All Branches' }}
                @if($method) &middot; Method: {{ ucfirst($method) }} @endif
            </div>
        </div>
        <div class="text-muted small">Generated: {{ now()->format('M d, Y h:i A') }}</div>
    </div>

    {{-- KPI Summary --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Gross Sales</div>
                    <div class="fs-4 fw-bold">₱{{ number_format($summary['gross_sales'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Discounts</div>
                    <div class="fs-4 fw-bold text-danger">₱{{ number_format($summary['total_discount'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">VAT Amount</div>
                    <div class="fs-4 fw-bold">₱{{ number_format($summary['vat_amount'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Net Sales</div>
                    <div class="fs-4 fw-bold text-success">₱{{ number_format($summary['net_sales'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">VATable Sales</div>
                    <div class="fs-5 fw-bold">₱{{ number_format($summary['vatable_sales'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">VAT-Exempt Sales</div>
                    <div class="fs-5 fw-bold">₱{{ number_format($summary['vat_exempt_sales'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Zero-Rated Sales</div>
                    <div class="fs-5 fw-bold">₱{{ number_format($summary['zero_rated_sales'], 2) }}</div>
                    <div class="text-muted" style="font-size: 0.7rem;">Not currently tracked as a separate category</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Number of Transactions</div>
                    <div class="fs-5 fw-bold">{{ number_format($summary['transaction_count']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Customers Served</div>
                    <div class="fs-5 fw-bold">{{ number_format($summary['customers_served']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Senior Citizen Discounts</div>
                    <div class="fs-5 fw-bold">{{ number_format($summary['senior_count']) }} <span class="fs-6 text-muted">tx</span></div>
                    <div class="text-muted small">₱{{ number_format($summary['senior_discount_amount'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">PWD Discounts</div>
                    <div class="fs-5 fw-bold">{{ number_format($summary['pwd_count']) }} <span class="fs-6 text-muted">tx</span></div>
                    <div class="text-muted small">₱{{ number_format($summary['pwd_discount_amount'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Refunds / Void Transactions</div>
                    <div class="fs-5 fw-bold">{{ number_format($summary['voided_count']) }} <span class="fs-6 text-muted">tx</span></div>
                    <div class="text-muted small">₱{{ number_format($summary['voided_amount'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold"><i data-lucide="building-2" class="me-1"></i> Total Sales per Branch</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Branch</th>
                                    <th class="text-end">Transactions</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">Discounts</th>
                                    <th class="text-end">Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($summary['sales_by_branch'] as $row)
                                <tr>
                                    <td>{{ $row->branch_name }}</td>
                                    <td class="text-end">{{ number_format($row->transactions) }}</td>
                                    <td class="text-end">₱{{ number_format($row->gross, 2) }}</td>
                                    <td class="text-end">₱{{ number_format($row->discount, 2) }}</td>
                                    <td class="text-end">₱{{ number_format($row->collected, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No sales in this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold"><i data-lucide="credit-card" class="me-1"></i> Payment Method Breakdown</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Method</th>
                                    <th class="text-end">Transactions</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($summary['by_method'] as $row)
                                <tr>
                                    <td>{{ ucfirst($row->method) }}</td>
                                    <td class="text-end">{{ number_format($row->transactions) }}</td>
                                    <td class="text-end">₱{{ number_format($row->amount, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No payments in this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Transactions --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Transactions (Official Receipts)</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>OR #</th>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Discount</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">VAT</th>
                            <th class="text-end">Net</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                        <tr>
                            <td class="text-nowrap small">{{ $payment->or_number ?? '—' }}</td>
                            <td class="text-nowrap text-muted small">{{ optional($payment->payment_date)->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</td>
                            <td class="text-muted small">{{ $payment->branch?->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $payment->order?->order_number ?? '—' }}</td>
                            <td class="small">{{ $payment->order?->customer_name ?: 'Walk-in' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($payment->method) }}</span></td>
                            <td class="small">
                                @if($payment->discount_type && $payment->discount_type !== 'none')
                                    {{ ucfirst($payment->discount_type) }} (₱{{ number_format($payment->discount_amount, 2) }})
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">₱{{ number_format($payment->subtotal + $payment->additional_charge_amount, 2) }}</td>
                            <td class="text-end">₱{{ number_format($payment->vat_amount, 2) }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format($payment->amount, 2) }}</td>
                            <td class="text-muted small">{{ $payment->receivedBy?->name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">No transactions in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($payments->hasPages())
        <div class="card-footer d-flex justify-content-center d-print-none">
            {{ $payments->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .sidebar,
    header.glass-header,
    .d-print-none {
        display: none !important;
    }

    .d-none.d-print-flex {
        display: flex !important;
    }

    .main-content {
        margin: 0 !important;
    }

    .container-xl {
        max-width: 100% !important;
    }

    .card {
        break-inside: avoid;
    }
}
</style>
@endpush
