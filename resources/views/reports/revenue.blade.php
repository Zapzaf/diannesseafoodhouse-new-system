@extends('layouts.app')
@section('page_title', 'Revenue Report')
@section('content')
<x-page-header title="Revenue Report" subtitle="Analyze paid revenue across periods, dates, and branches" icon="bar-chart-2">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.revenue') }}" class="row g-2 align-items-end" id="reportPeriodForm">
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
                    <a href="{{ route('reports.revenue') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
            <div class="small text-muted mt-2">
                Showing data from {{ \Carbon\Carbon::parse($filters['date_from'])->format('M d, Y') }} to {{ \Carbon\Carbon::parse($filters['date_to'])->format('M d, Y') }}.
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Subtotal (Paid Only)</div>
                    <div class="h5 fw-bold">P{{ number_format((float)$totals->subtotal, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">VAT Amount (12%)</div>
                    <div class="h5 fw-bold">P{{ number_format((float)$totals->vat_amount, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Gross Revenue (Paid Only)</div>
                    <div class="h5 fw-bold text-success">P{{ number_format((float)$totals->total_amount, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Net Income (Revenue - Expenses)</div>
                    <div class="h5 fw-bold {{ $netIncome >= 0 ? 'text-primary' : 'text-danger' }}">P{{ number_format((float)$netIncome, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4 report-chart-card h-100">
        <div class="card-header fw-semibold">Revenue Trend</div>
        <div class="card-body">
            <div class="report-chart-frame">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Revenue Breakdown</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="revenueTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Period</th>
                            <th>Subtotal</th>
                            <th>VAT</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trend as $row)
                        <tr>
                            <td>{{ $row->period_key }}</td>
                            <td>P{{ number_format((float)$row->subtotal, 2) }}</td>
                            <td>P{{ number_format((float)$row->vat_amount, 2) }}</td>
                            <td class="fw-semibold">P{{ number_format((float)$row->total_amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No fully paid transactions found for the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/reports-init.js') }}"></script>
<script>
ReportUtils.initDataTable('#revenueTable');
ReportUtils.initPeriodFilter('#reportPeriodForm');

const revCtx = document.getElementById('revenueTrendChart');
if (revCtx) {
    const rootStyles = getComputedStyle(document.documentElement);
    const themePrimary = rootStyles.getPropertyValue('--primary-color').trim() || '#f07c59';
    const themePrimaryRgb = rootStyles.getPropertyValue('--primary-color-rgb').trim() || '240, 124, 89';

    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                label: 'Paid Revenue',
                data: @json($chartRevenue),
                borderColor: themePrimary,
                backgroundColor: `rgba(${themePrimaryRgb},0.20)`,
                fill: true,
                tension: 0.25,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
        },
    });
}
</script>
@endpush
@endsection
