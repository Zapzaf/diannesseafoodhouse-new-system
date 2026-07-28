@extends('layouts.app')
@section('page_title', 'X Reading - Dianne\'s Seafood House')
@section('content')
<x-page-header title="X Reading" subtitle="Live sales snapshot — does not close the business day or reset counters" icon="activity">
    <div class="d-flex gap-2 d-print-none">
        <a href="{{ route('reports.x-reading.export-excel', request()->query()) }}" class="btn btn-light text-primary">
            <i data-lucide="file-spreadsheet" class="me-1"></i> Export Excel
        </a>
        <a href="{{ route('reports.x-reading.export-pdf', request()->query()) }}" class="btn btn-light text-primary">
            <i data-lucide="file-text" class="me-1"></i> Export PDF
        </a>
        <button type="button" class="btn btn-light text-primary" onclick="window.print()">
            <i data-lucide="printer" class="me-1"></i> Print
        </button>
    </div>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm mb-4 d-print-none">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.x-reading.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Business Date</label>
                    <input type="date" name="business_date" class="form-control" value="{{ $businessDate }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Terminal</label>
                    <select name="pos_terminal_id" class="form-select">
                        <option value="">All Terminals</option>
                        @foreach($terminals as $terminal)
                        <option value="{{ $terminal->id }}" {{ (string) $selectedTerminalId === (string) $terminal->id ? 'selected' : '' }}>{{ $terminal->name }} ({{ $terminal->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('reports.x-reading.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="fw-bold fs-5">X Reading — Current Sales Snapshot</div>
            <div class="text-muted small">
                Business Date: {{ \Carbon\Carbon::parse($businessDate)->format('M d, Y') }}
                &middot; Branch: {{ $selectedBranchId ? ($branches->firstWhere('id', $selectedBranchId)->name ?? 'Unknown') : 'All Branches' }}
                &middot; Terminal: {{ $selectedTerminalId ? ($terminals->firstWhere('id', $selectedTerminalId)->name ?? 'Unknown') : 'All Terminals' }}
            </div>
        </div>
        <div class="text-muted small">Generated: {{ $generatedAt->format('M d, Y h:i A') }}</div>
    </div>

    @include('reports.partials.reading-summary', ['summary' => $summary])

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Transactions So Far Today</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>OR #</th><th>Time</th><th>Branch</th><th>Order #</th><th>Customer</th>
                            <th>Method</th><th>Cashier</th><th class="text-end">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                        <tr>
                            <td class="small">{{ $payment->or_number ?? '—' }}</td>
                            <td class="text-nowrap text-muted small">{{ $payment->created_at->format('h:i A') }}</td>
                            <td class="text-muted small">{{ $payment->branch?->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $payment->order?->order_number ?? '—' }}</td>
                            <td class="small">{{ $payment->order?->customer_name ?: 'Walk-in' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($payment->method) }}</span></td>
                            <td class="text-muted small">{{ $payment->receivedBy?->name ?? '—' }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No transactions recorded yet today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .sidebar, header.glass-header, .d-print-none { display: none !important; }
    .main-content { margin: 0 !important; }
    .container-xl { max-width: 100% !important; }
    .card { break-inside: avoid; }
}
</style>
@endpush
