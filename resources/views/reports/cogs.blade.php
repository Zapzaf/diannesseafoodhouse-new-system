@extends('layouts.app')
@section('page_title', 'COGS Report - Dianne Seafood House')
@section('content')
<x-page-header title="Cost of Goods Sold Report" subtitle="Unit and total cost of inventory consumed within the selected period" icon="receipt">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.cogs.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.cogs.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Formula summary: Beginning + Purchases − Ending = COGS --}}
    <div class="card shadow-sm mb-4 border-0" style="background: linear-gradient(135deg,#fff7f5,#fff);">
        <div class="card-body">
            <div class="row g-3 text-center align-items-center">
                <div class="col-6 col-md">
                    <div class="text-muted small">Beginning Inventory</div>
                    <div class="fs-5 fw-bold">₱{{ number_format($totalBeginningValue, 2) }}</div>
                </div>
                <div class="col-auto d-none d-md-block fs-4 text-muted">+</div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Purchases</div>
                    <div class="fs-5 fw-bold text-success">₱{{ number_format($totalPurchasesValue, 2) }}</div>
                </div>
                <div class="col-auto d-none d-md-block fs-4 text-muted">&minus;</div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Ending Inventory</div>
                    <div class="fs-5 fw-bold">₱{{ number_format($totalEndingValue, 2) }}</div>
                </div>
                <div class="col-auto d-none d-md-block fs-4 text-muted">=</div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Cost of Goods Sold</div>
                    <div class="fs-5 fw-bold text-danger">₱{{ number_format($totalCogsFormula, 2) }}</div>
                </div>
            </div>
            <div class="form-text mt-2 mb-0 text-center">
                Beginning Inventory + Purchases &minus; Ending Inventory = Cost of Goods Sold, for the selected period and branch. Beginning/Ending stock is valued at each item's latest purchase cost; Purchases are valued at the actual price paid on each stock-in.
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i data-lucide="receipt" class="text-danger" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Cost of Goods Sold (by item)</div>
                        <div class="fs-4 fw-bold">₱{{ number_format($totalCogs, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i data-lucide="package-minus" class="text-info" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Items with Consumption</div>
                        <div class="fs-4 fw-bold">{{ number_format($items->total()) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-secondary bg-opacity-10 p-3">
                        <i data-lucide="calendar-range" class="text-secondary" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Period</div>
                        <div class="fs-6 fw-bold">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm" data-static-pagination="1">
        <div class="card-header fw-semibold"><i data-lucide="receipt" class="me-1"></i> Cost of Goods Sold by Item</div>
        <div class="card-body">
            <div class="form-text mb-3">Quantity is the total approved stock-out for each item within the period (production input, waste, order deduction, etc.). Unit Cost is that item's most recent purchase price. Items with no consumption in the period are not shown.</div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Branch</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th class="text-end">Quantity Consumed</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                        @php
                            $unitCost = (float) ($item->latest_unit_cost ?? $item->unit_price);
                            $quantitySold = (float) $item->quantity_sold;
                            $totalCost = $unitCost * $quantitySold;
                        @endphp
                        <tr>
                            <td>{{ $items->firstItem() + $index }}</td>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td class="text-muted small">{{ $item->branch?->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $item->category?->name ?? '—' }}</td>
                            <td class="text-muted">{{ $item->unit }}</td>
                            <td class="text-end">{{ number_format($quantitySold, 2) }}</td>
                            <td class="text-end">₱{{ number_format($unitCost, 4) }}</td>
                            <td class="text-end fw-bold">₱{{ number_format($totalCost, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No inventory consumption recorded for this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($items->isNotEmpty())
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="7" class="text-end">Total (this page)</td>
                            <td class="text-end">₱{{ number_format($items->sum(fn($item) => (float) ($item->latest_unit_cost ?? $item->unit_price) * (float) $item->quantity_sold), 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
        @if($items->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $items->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
