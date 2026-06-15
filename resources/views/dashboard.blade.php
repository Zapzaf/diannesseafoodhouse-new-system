@extends('layouts.app')

@section('page_title', 'Dashboard - Dianne Seafood House')

@push('styles')
<style>
.kpi-card { border-left: 4px solid var(--bs-primary); }
.kpi-card .icon-circle { width:3rem;height:3rem;background:rgba(162,44,41,.1);border-radius:50%;display:flex;align-items:center;justify-content:center; }
.kpi-card .icon-circle svg { color:#a22c29;stroke:#a22c29; }
.stat-label { font-size:.76rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b7280; }
.stat-value { font-size:1.6rem;font-weight:800;color:#111827; }
</style>
@endpush

@section('content')
<main>
<x-page-header title="Dashboard" :subtitle="now()->format('l, F j, Y')" icon="home">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <!-- KPI Row 1 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Daily Revenue</div>
                        <div class="stat-value">PHP {{ number_format($dailyRevenue, 2) }}</div>
                    </div>
                    <div class="icon-circle"><i data-feather="dollar-sign"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Monthly Revenue</div>
                        <div class="stat-value">PHP {{ number_format($monthlyRevenue, 2) }}</div>
                    </div>
                    <div class="icon-circle"><i data-feather="trending-up"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Monthly Expenses</div>
                        <div class="stat-value">PHP {{ number_format($monthlyExpenses, 2) }}</div>
                    </div>
                    <div class="icon-circle"><i data-feather="trending-down"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 shadow-sm" style="border-left-color:{{ $netIncome >= 0 ? '#1f7a4d' : '#b3261e' }}">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Net Income</div>
                        <div class="stat-value" style="color:{{ $netIncome >= 0 ? '#1f7a4d' : '#b3261e' }}">PHP {{ number_format($netIncome, 2) }}</div>
                    </div>
                    <div class="icon-circle"><i data-feather="bar-chart-2"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Row 2 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body">
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-value {{ $lowStockCount > 0 ? 'text-danger' : 'text-success' }}">{{ $lowStockCount }}</div>
                    <small class="text-muted">requires restocking</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body">
                    <div class="stat-label">Categories</div>
                    <div class="stat-value text-primary">{{ $categoryCount }}</div>
                    <small class="text-muted">inventory groups</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body">
                    <div class="stat-label">Suppliers</div>
                    <div class="stat-value text-info">{{ $supplierCount }}</div>
                    <a href="{{ route('suppliers.index') }}" class="small">Manage Suppliers</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body">
                    <div class="stat-label">Branches</div>
                    <div class="stat-value text-success">{{ $branchCount }}</div>
                    <small class="text-muted">active operations</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts + Tables Row -->
    <div class="row g-3 mb-4">
        <!-- Revenue Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold">Revenue Trend (Last 6 Months)</div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <!-- Expense Breakdown -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold">Expense Breakdown (This Month)</div>
                <div class="card-body">
                    <canvas id="expenseChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    @if($lowStockCount > 0)
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-danger">
                <div class="card-header fw-bold text-danger d-flex justify-content-between">
                    <span><i data-feather="alert-triangle" class="me-1"></i> Low Stock Alerts</span>
                    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-danger">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark"><tr><th>Item</th><th>Category</th><th>Current Qty</th><th>Threshold</th></tr></thead>
                            <tbody>
                            @foreach($lowStockItems as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category?->name ?? '-' }}</td>
                                <td class="text-danger fw-bold">{{ $item->quantity }}</td>
                                <td>{{ $item->low_stock_threshold }}</td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
</main>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode(array_column($revenueChart, 'label')) !!},
        datasets: [{
            label: 'Revenue (?)',
            data: {!! json_encode(array_column($revenueChart, 'amount')) !!},
            backgroundColor: 'rgba(162,44,41,0.75)',
            borderColor: '#a22c29',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Expense Breakdown Chart
@php $categories = $expenseBreakdown->pluck('category')->toArray(); $amounts = $expenseBreakdown->pluck('total')->map(fn($v)=>(float)$v)->toArray(); @endphp
const expenseCtx = document.getElementById('expenseChart').getContext('2d');
new Chart(expenseCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($categories) !!},
        datasets: [{
            data: {!! json_encode($amounts) !!},
            backgroundColor: ['#a22c29','#b45309','#0b7285','#1f7a4d','#6d28d9','#374151'],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>
@endpush
