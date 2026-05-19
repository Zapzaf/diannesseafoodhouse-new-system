@extends('layouts.app')
@section('page_title', 'Expense Report')
@section('content')
<main>
<x-page-header title="Expense Report" subtitle="Review cost categories, monthly trends, and branch spending" icon="trending-down">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.expenses') }}" class="row g-2 align-items-end" id="reportPeriodForm">
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
                @include('reports.partials.period-filter', ['filters' => $filters])
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a href="{{ route('reports.expenses') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">Total Expenses</div><div class="h5 fw-bold text-danger">P{{ number_format((float)$totalExpenses, 2) }}</div></div></div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100 report-chart-card">
                <div class="card-header fw-semibold">Monthly Expense Breakdown</div>
                <div class="card-body"><div class="report-chart-frame"><canvas id="expenseMonthlyChart"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Category Totals</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="expenseCategoryTable">
                            <thead class="table-dark"><tr><th>Category</th><th>Total</th></tr></thead>
                            <tbody>
                                @forelse($categoryTotals as $row)
                                <tr><td>{{ $row->category }}</td><td class="fw-semibold">P{{ number_format((float)$row->total_amount, 2) }}</td></tr>
                                @empty
                                <tr><td colspan="2" class="text-center text-muted py-4">No expense categories found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Expense Records</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="expenseRecordsTable">
                            <thead class="table-dark"><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead>
                            <tbody>
                                @forelse($expenses as $expense)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('Y-m-d') }}</td>
                                    <td>{{ $expense->category }}</td>
                                    <td>{{ $expense->description }}</td>
                                    <td class="fw-semibold">P{{ number_format((float)$expense->amount, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No expense records found for selected filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/reports-init.js') }}"></script>
<script>
ReportUtils.initPeriodFilter('#reportPeriodForm');
ReportUtils.initDataTable('#expenseCategoryTable');
ReportUtils.initDataTable('#expenseRecordsTable');

const expenseMonthlyCtx = document.getElementById('expenseMonthlyChart');
if (expenseMonthlyCtx) {
    new Chart(expenseMonthlyCtx, {
        type: 'bar',
        data: {
            labels: @json($monthlyBreakdown->pluck('month_key')),
            datasets: [{
                label: 'Expenses',
                data: @json($monthlyBreakdown->pluck('total_amount')->map(fn($v) => (float)$v)),
                backgroundColor: '#b3261e',
            }],
        },
        options: { responsive: true, maintainAspectRatio: false },
    });
}
</script>
@endpush
@endsection
