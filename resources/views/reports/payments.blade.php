@extends('layouts.app')
@section('page_title', 'Payment Report')
@section('content')
<x-page-header title="Payment Report" subtitle="Track collections, outstanding balances, and payment channels" icon="credit-card">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.payments') }}" class="row g-2 align-items-end" id="reportPeriodForm">
                @if(auth()->user()->isAdmin())
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Branch</label>
                    <select name="branch_id" class="form-select">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (string) $selectedBranchId === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Method</label>
                    <select name="method" class="form-select">
                        <option value="">All</option>
                        @foreach(['cash', 'gcash', 'card', 'bank'] as $method)
                        <option value="{{ $method }}" {{ $filters['method'] === $method ? 'selected' : '' }}>{{ strtoupper($method) }}</option>
                        @endforeach
                    </select>
                </div>
                @include('reports.partials.period-filter', ['filters' => $filters])
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a href="{{ route('reports.payments') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">Total Paid</div><div class="h5 fw-bold text-success">P{{ number_format((float)$totalPaid, 2) }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">Outstanding Balances</div><div class="h5 fw-bold text-danger">P{{ number_format((float)$outstandingBalances, 2) }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">VATABLE Sales</div>
                    <div class="h5 fw-bold text-primary">P{{ number_format((float)$vatableTotal, 2) }}</div>
                    <div class="small text-muted mt-1">VAT-Exempt: P{{ number_format((float)$vatExemptTotal, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Discounts Given (PWD/Senior)</div>
                    <div class="h5 fw-bold text-warning">P{{ number_format((float)$totalDiscountGiven, 2) }}</div>
                    <div class="small text-muted mt-1">
                        PWD: P{{ number_format((float)$pwdPaymentsTotal, 2) }} &bull;
                        Senior: P{{ number_format((float)$seniorPaymentsTotal, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Payment Status Summary</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="paymentStatusTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Status</th>
                            <th>Transactions</th>
                            <th>Gross Total</th>
                            <th>Paid Total</th>
                            <th>Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(['paid' => 'Fully Paid', 'partial' => 'Partially Paid', 'unpaid' => 'Unpaid'] as $key => $label)
                        @php($row = $paymentSummary[$key] ?? null)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $row->total_transactions ?? 0 }}</td>
                            <td>P{{ number_format((float)($row->gross_total ?? 0), 2) }}</td>
                            <td>P{{ number_format((float)($row->paid_total ?? 0), 2) }}</td>
                            <td class="{{ $key === 'paid' ? 'text-success' : 'text-danger' }}">P{{ number_format((float)($row->outstanding_total ?? 0), 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Payment Transactions</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="paymentTransactionsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Branch</th>
                            <th>Customer</th>
                            <th>Reference</th>
                            <th>Method</th>
                            <th>Discount</th>
                            <th>OR #</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d H:i') }}</td>
                            <td>{{ $payment->source_label }}</td>
                            <td>{{ $payment->branch_name }}</td>
                            <td>{{ $payment->customer_name }}</td>
                            <td>{{ $payment->reference_label }}</td>
                            <td>{{ strtoupper($payment->method) }}</td>
                            <td>
                                @if($payment->discount_type && $payment->discount_type !== 'none')
                                <span class="badge bg-warning text-dark">{{ $payment->discount_label }}</span>
                                @elseif($payment->discount_label !== 'None')
                                <span class="badge bg-warning text-dark">{{ $payment->discount_label }}</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><small>{{ $payment->or_number ?? '—' }}</small></td>
                            <td class="fw-semibold">P{{ number_format((float)$payment->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No payments found for selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script src="{{ asset('js/reports-init.js') }}"></script>
<script>
ReportUtils.initPeriodFilter('#reportPeriodForm');
ReportUtils.initDataTable('#paymentStatusTable');
ReportUtils.initDataTable('#paymentTransactionsTable');
</script>
@endpush
@endsection
