@extends('layouts.app')
@section('page_title', 'Y Reading - Dianne\'s Seafood House')
@section('content')
<x-page-header title="Y Reading" :subtitle="'Shift summary for ' . ($shift->cashier->name ?? 'cashier') . ' — does not close the business day'" icon="user-check">
    <div class="d-flex gap-2 d-print-none">
        <a href="{{ route('reports.y-reading.export-excel', ['cash_shift_id' => $shift->id]) }}" class="btn btn-light text-primary">
            <i data-lucide="file-spreadsheet" class="me-1"></i> Export Excel
        </a>
        <a href="{{ route('reports.y-reading.export-pdf', ['cash_shift_id' => $shift->id]) }}" class="btn btn-light text-primary">
            <i data-lucide="file-text" class="me-1"></i> Export PDF
        </a>
        <button type="button" class="btn btn-light text-primary" onclick="window.print()">
            <i data-lucide="printer" class="me-1"></i> Print
        </button>
        <a href="{{ route('cash-shifts.show', $shift) }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Shift
        </a>
    </div>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="row g-4 mb-3">
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-3">Cashier</dt><dd class="col-sm-9">{{ $shift->cashier->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Terminal</dt><dd class="col-sm-9">{{ $shift->terminal->name ?? '—' }} ({{ $shift->terminal->code ?? '—' }})</dd>
                        <dt class="col-sm-3">Branch</dt><dd class="col-sm-9">{{ $shift->branch->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Shift Start</dt><dd class="col-sm-9">{{ $shift->opened_at->format('M d, Y h:i A') }}</dd>
                        <dt class="col-sm-3">Shift End</dt><dd class="col-sm-9">{{ $shift->closed_at?->format('M d, Y h:i A') ?? 'Still open' }}</dd>
                        <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge {{ $shift->isOpen() ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($shift->status) }}</span></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Cash Reconciliation</div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-7">Opening Float</dt><dd class="col-5 text-end">₱{{ number_format($shift->opening_float, 2) }}</dd>
                        <dt class="col-7 fw-bold">Expected Cash</dt><dd class="col-5 text-end fw-bold">₱{{ number_format($expectedCash, 2) }}</dd>
                        @if(!$shift->isOpen())
                        <dt class="col-7">Counted Cash</dt><dd class="col-5 text-end">₱{{ number_format($shift->closing_cash_counted, 2) }}</dd>
                        <dt class="col-7 fw-bold">Variance</dt>
                        <dd class="col-5 text-end fw-bold {{ (float) $shift->cash_variance < 0 ? 'text-danger' : ((float) $shift->cash_variance > 0 ? 'text-warning' : 'text-success') }}">₱{{ number_format($shift->cash_variance, 2) }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @include('reports.partials.reading-summary', ['summary' => $summary])

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Transactions This Shift</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>OR #</th><th>Time</th><th>Order #</th><th>Customer</th><th>Method</th><th class="text-end">Net</th></tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                        <tr>
                            <td class="small">{{ $payment->or_number ?? '—' }}</td>
                            <td class="text-nowrap text-muted small">{{ $payment->created_at->format('h:i A') }}</td>
                            <td class="text-muted small">{{ $payment->order?->order_number ?? '—' }}</td>
                            <td class="small">{{ $payment->order?->customer_name ?: 'Walk-in' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($payment->method) }}</span></td>
                            <td class="text-end fw-semibold">₱{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No transactions recorded this shift.</td></tr>
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
