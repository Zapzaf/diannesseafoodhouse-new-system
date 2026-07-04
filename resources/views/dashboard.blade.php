@extends('layouts.app')

@section('page_title', 'Dashboard - Dianne Seafood House')

@section('content')
    @php
        $scopeLabel = $selectedBranchName ?: 'All Branches';
        $analyticsPeriodOptions = [
            'days' => 'Days',
            'week' => 'Week',
            'month' => 'Month',
        ];
    @endphp

    <x-page-header title="Dashboard" :subtitle="now()->format('l, F j, Y')" icon="activity">
        <div class="dashboard-filter-group" aria-label="Dashboard analytics filter">
            @foreach($analyticsPeriodOptions as $periodValue => $periodLabel)
            <a href="{{ route('dashboard', ['analytics_period' => $periodValue]) }}"
               class="dashboard-filter-pill {{ $analyticsPeriod === $periodValue ? 'active' : '' }}">
                {{ $periodLabel }}
            </a>
            @endforeach
        </div>
        <span class="dashboard-scope-pill">
            <i data-lucide="map-pin"></i>
            {{ $scopeLabel }}
        </span>
    </x-page-header>

    <div class="container-xl px-4 dashboard-shell">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <section class="dashboard-overview mb-4">
            <div>
                <span class="dashboard-overline">Operations Snapshot</span>
                <h2>Business performance for {{ $scopeLabel }}</h2>
            </div>
            <div class="dashboard-health">
                <div class="dashboard-health-ring" style="--health-progress: {{ $stockHealthPercent }}%;">
                    <span>{{ $stockHealthPercent }}%</span>
                </div>
                <div>
                    <span class="dashboard-health-label">Stock Health</span>
                    <strong>{{ $itemCount - $lowStockCount }} of {{ $itemCount }} items OK</strong>
                </div>
            </div>
        </section>

        <section class="dashboard-metrics-grid mb-4">
            <article class="dashboard-metric-card metric-revenue">
                <div class="dashboard-metric-icon"><i data-lucide="wallet"></i></div>
                <div class="dashboard-metric-body">
                    <span>Daily Revenue</span>
                    <strong>₱{{ number_format($dailyRevenue, 2) }}</strong>
                    <small>Recorded today</small>
                </div>
            </article>

            <article class="dashboard-metric-card metric-monthly">
                <div class="dashboard-metric-icon"><i data-lucide="trending-up"></i></div>
                <div class="dashboard-metric-body">
                    <span>Monthly Revenue</span>
                    <strong>₱{{ number_format($monthlyRevenue, 2) }}</strong>
                    <small>{{ now()->format('F Y') }}</small>
                </div>
            </article>

        </section>

        <section class="dashboard-operations-grid mb-4">
            <a href="{{ route('items.index') }}" class="dashboard-operation-card">
                <span><i data-lucide="package"></i></span>
                <div>
                    <strong>{{ number_format($itemCount) }}</strong>
                    <small>Total Items</small>
                </div>
            </a>
            <a href="{{ route('items.low-stock') }}" class="dashboard-operation-card {{ $lowStockCount > 0 ? 'is-alert' : '' }}">
                <span><i data-lucide="alert-triangle"></i></span>
                <div>
                    <strong>{{ number_format($lowStockCount) }}</strong>
                    <small>Low Stock</small>
                </div>
            </a>
            <div class="dashboard-operation-card">
                <span><i data-lucide="folder"></i></span>
                <div>
                    <strong>{{ number_format($categoryCount) }}</strong>
                    <small>Categories</small>
                </div>
            </div>
            <a href="{{ route('suppliers.index') }}" class="dashboard-operation-card">
                <span><i data-lucide="truck"></i></span>
                <div>
                    <strong>{{ number_format($supplierCount) }}</strong>
                    <small>Suppliers</small>
                </div>
            </a>
            <div class="dashboard-operation-card">
                <span><i data-lucide="store"></i></span>
                <div>
                    <strong>{{ number_format($branchCount) }}</strong>
                    <small>Active Branches</small>
                </div>
            </div>
            <div class="dashboard-operation-card {{ $outOfStockCount > 0 ? 'is-danger' : '' }}">
                <span><i data-lucide="x-circle"></i></span>
                <div>
                    <strong>{{ number_format($outOfStockCount) }}</strong>
                    <small>Out of Stock</small>
                </div>
            </div>
        </section>

        <section class="row g-4 mb-4 dashboard-chart-row">
            <div class="col-xl-12">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel-header">
                        <div>
                            <h5>Revenue Trend</h5>
                            <span>Last 6 months</span>
                        </div>
                        <i data-lucide="line-chart"></i>
                    </div>
                    <div class="dashboard-chart-container dashboard-chart-large">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-module-grid mb-4">
            <article class="dashboard-module-card">
                <div class="dashboard-module-header">
                    <div class="dashboard-module-title">
                        <span><i data-lucide="list"></i></span>
                        <div>
                            <h5>Inventory Transactions</h5>
                            <small>Total quantity added versus deducted, {{ $analyticsRangeLabel }}</small>
                        </div>
                    </div>
                    <strong class="{{ $inventoryAnalytics['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($inventoryAnalytics['net'], 2) }}
                    </strong>
                </div>
                <div class="dashboard-module-stats">
                    <div>
                        <small>Added</small>
                        <strong class="text-success">{{ number_format($inventoryAnalytics['total_added'], 2) }}</strong>
                        <span>{{ $inventoryAnalytics['added_count'] }} logs</span>
                    </div>
                    <div>
                        <small>Deducted</small>
                        <strong class="text-danger">{{ number_format($inventoryAnalytics['total_deducted'], 2) }}</strong>
                        <span>{{ $inventoryAnalytics['deducted_count'] }} logs</span>
                    </div>
                </div>
                <div class="dashboard-module-chart">
                    <canvas id="inventoryTransactionsChart"></canvas>
                </div>
            </article>

            <article class="dashboard-module-card">
                <div class="dashboard-module-header">
                    <div class="dashboard-module-title">
                        <span><i data-lucide="truck"></i></span>
                        <div>
                            <h5>Deliveries</h5>
                            <small>Total delivered quantity grouped by unit, {{ $analyticsRangeLabel }}</small>
                        </div>
                    </div>
                    <strong>{{ number_format($deliveryAnalytics['total_quantity'], 2) }}</strong>
                </div>
                <div class="dashboard-module-stats">
                    <div>
                        <small>Delivery Items</small>
                        <strong>{{ number_format($deliveryAnalytics['delivery_item_count']) }}</strong>
                        <span>Received</span>
                    </div>
                    <div>
                        <small>Units</small>
                        <strong>{{ number_format($deliveryAnalytics['unit_count']) }}</strong>
                        <span>{{ $deliveryAnalytics['top_unit'] }}</span>
                    </div>
                </div>
                <div class="dashboard-module-chart">
                    <canvas id="deliveryUnitsChart"></canvas>
                </div>
            </article>

            <article class="dashboard-module-card">
                <div class="dashboard-module-header">
                    <div class="dashboard-module-title">
                        <span><i data-lucide="settings"></i></span>
                        <div>
                            <h5>Production Reports</h5>
                            <small>Output trends compared with input usage, {{ $analyticsRangeLabel }}</small>
                        </div>
                    </div>
                    <strong class="{{ $productionAnalytics['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($productionAnalytics['net'], 2) }}
                    </strong>
                </div>
                <div class="dashboard-module-stats">
                    <div>
                        <small>Produced</small>
                        <strong class="text-success">{{ number_format($productionAnalytics['total_outputs'], 2) }}</strong>
                        <span>{{ $productionAnalytics['orders_finished'] }} finished</span>
                    </div>
                    <div>
                        <small>Inputs Used</small>
                        <strong class="text-danger">{{ number_format($productionAnalytics['total_inputs'], 2) }}</strong>
                        <span>{{ $productionAnalytics['orders_started'] }} orders</span>
                    </div>
                </div>
                <div class="dashboard-module-chart">
                    <canvas id="productionTrendsChart"></canvas>
                </div>
            </article>
        </section>

        <section class="dashboard-panel dashboard-low-stock-panel">
            <div class="dashboard-panel-header">
                <div>
                    <h5>Low Stock Watchlist</h5>
                    <span>{{ $lowStockCount > 0 ? 'Items that need replenishment' : 'Inventory levels are healthy' }}</span>
                </div>
                <a href="{{ route('items.low-stock') }}" class="btn btn-outline-primary btn-sm">
                    View All
                </a>
            </div>

            @if($lowStockItems->isNotEmpty())
            <div class="table-responsive dashboard-table-wrap">
                <table class="table dashboard-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th class="text-end">Current Qty</th>
                            <th class="text-end">Threshold</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lowStockItems as $item)
                        <tr>
                            <td>
                                <div class="dashboard-item-cell">
                                    <strong>{{ $item->name }}</strong>
                                    <span>#{{ $item->id }}</span>
                                </div>
                            </td>
                            <td>{{ $item->category?->name ?? '-' }}</td>
                            <td>{{ $item->branch?->name ?? '-' }}</td>
                            <td class="text-end fw-bold text-danger">{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                            <td class="text-end text-muted">{{ number_format($item->low_stock_threshold, 2) }} {{ $item->unit }}</td>
                            <td class="text-center">
                                @if($item->quantity <= 0)
                                <span class="badge-status badge-expired">OUT OF STOCK</span>
                                @else
                                <span class="badge-status badge-pending">LOW STOCK</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="dashboard-empty-state">
                <i data-lucide="check-circle"></i>
                <strong>No low stock items</strong>
                <span>All tracked inventory is above threshold.</span>
            </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let revenueChartInst = null;
    let inventoryTransactionsChartInst = null;
    let deliveryUnitsChartInst = null;
    let productionTrendsChartInst = null;

    const revenueLabels = @json(array_column($revenueChart, 'label'));
    const revenueAmounts = @json(array_column($revenueChart, 'amount'));
    const inventoryAnalytics = @json($inventoryAnalytics);
    const deliveryAnalytics = @json($deliveryAnalytics);
    const productionAnalytics = @json($productionAnalytics);
    const themePrimary = '#f07c59';

    function moneyLabel(value) {
        return '₱' + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function renderCharts(theme) {
        const isDark = theme === 'dark';
        const gridColor = isDark ? '#1e293b' : '#e5edf5';
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const panelColor = isDark ? '#151c2c' : '#ffffff';

        if (revenueChartInst) revenueChartInst.destroy();
        if (inventoryTransactionsChartInst) inventoryTransactionsChartInst.destroy();
        if (deliveryUnitsChartInst) deliveryUnitsChartInst.destroy();
        if (productionTrendsChartInst) productionTrendsChartInst.destroy();

        const revenueCanvas = document.getElementById('revenueChart');
        const inventoryCanvas = document.getElementById('inventoryTransactionsChart');
        const deliveryCanvas = document.getElementById('deliveryUnitsChart');
        const productionCanvas = document.getElementById('productionTrendsChart');
        if (!revenueCanvas || !inventoryCanvas || !deliveryCanvas || !productionCanvas || !window.Chart) return;

        const revenueCtx = revenueCanvas.getContext('2d');
        const trendGradient = revenueCtx.createLinearGradient(0, 0, 0, 320);
        trendGradient.addColorStop(0, 'rgba(240, 124, 89, 0.28)');
        trendGradient.addColorStop(1, 'rgba(240, 124, 89, 0)');

        revenueChartInst = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Revenue',
                    data: revenueAmounts,
                    borderColor: themePrimary,
                    borderWidth: 3,
                    backgroundColor: trendGradient,
                    fill: true,
                    tension: 0.38,
                    pointBackgroundColor: panelColor,
                    pointBorderColor: themePrimary,
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        backgroundColor: isDark ? '#0f172a' : '#ffffff',
                        titleColor: isDark ? '#ffffff' : '#0f172a',
                        bodyColor: isDark ? '#e2e8f0' : '#475569',
                        borderColor: isDark ? '#1e293b' : '#e2e8f0',
                        borderWidth: 1,
                        callbacks: {
                            label: (context) => 'Revenue: ' + moneyLabel(context.raw)
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { family: 'Plus Jakarta Sans', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        border: { display: false },
                        ticks: {
                            color: textColor,
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            callback: (value) => value >= 1000 ? '₱' + (value / 1000) + 'k' : '₱' + value
                        }
                    }
                }
            }
        });

        inventoryTransactionsChartInst = new Chart(inventoryCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: inventoryAnalytics.labels,
                datasets: [
                    {
                        label: 'Added',
                        data: inventoryAnalytics.added,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Deducted',
                        data: inventoryAnalytics.deducted,
                        borderColor: themePrimary,
                        backgroundColor: 'rgba(240, 124, 89, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: quantityTrendOptions(isDark, textColor, gridColor)
        });

        const deliveryLabels = deliveryAnalytics.labels.length ? deliveryAnalytics.labels : ['No deliveries'];
        const deliveryQuantities = deliveryAnalytics.quantities.length ? deliveryAnalytics.quantities : [0];
        deliveryUnitsChartInst = new Chart(deliveryCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: deliveryLabels,
                datasets: [{
                    label: 'Delivered Quantity',
                    data: deliveryQuantities,
                    backgroundColor: ['#f07c59', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#64748b'],
                    borderRadius: 8,
                    maxBarThickness: 38
                }]
            },
            options: quantityBarOptions(isDark, textColor, gridColor, false)
        });

        productionTrendsChartInst = new Chart(productionCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: productionAnalytics.labels,
                datasets: [
                    {
                        label: 'Produced',
                        data: productionAnalytics.outputs,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Inputs Used',
                        data: productionAnalytics.inputs,
                        borderColor: themePrimary,
                        backgroundColor: 'rgba(240, 124, 89, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: quantityTrendOptions(isDark, textColor, gridColor)
        });
    }

    function quantityTrendOptions(isDark, textColor, gridColor) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        font: { family: 'Plus Jakarta Sans', size: 11 }
                    }
                },
                tooltip: quantityTooltipOptions(isDark)
            },
            scales: quantityScales(textColor, gridColor)
        };
    }

    function quantityBarOptions(isDark, textColor, gridColor, showLegend = true) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: showLegend },
                tooltip: quantityTooltipOptions(isDark)
            },
            scales: quantityScales(textColor, gridColor)
        };
    }

    function quantityTooltipOptions(isDark) {
        return {
            padding: 12,
            backgroundColor: isDark ? '#0f172a' : '#ffffff',
            titleColor: isDark ? '#ffffff' : '#0f172a',
            bodyColor: isDark ? '#e2e8f0' : '#475569',
            borderColor: isDark ? '#1e293b' : '#e2e8f0',
            borderWidth: 1,
            callbacks: {
                label: (context) => context.dataset.label + ': ' + Number(context.raw || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })
            }
        };
    }

    function quantityScales(textColor, gridColor) {
        return {
            x: {
                grid: { display: false },
                ticks: { color: textColor, font: { family: 'Plus Jakarta Sans', size: 11 } }
            },
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                border: { display: false },
                ticks: { color: textColor, font: { family: 'Plus Jakarta Sans', size: 11 } }
            }
        };
    }

    const currentTheme = document.documentElement.getAttribute('data-bs-theme') || localStorage.getItem('theme') || 'light';
    renderCharts(currentTheme);

    window.addEventListener('theme-changed', (event) => {
        renderCharts(event.detail);
    });
});
</script>
@endpush
