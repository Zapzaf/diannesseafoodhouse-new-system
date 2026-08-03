@extends('layouts.app')
@section('page_title', 'Outstanding Payables')
@section('content')
    <x-page-header title="Outstanding Payables" subtitle="Unpaid Purchases and Services across the accounting module" icon="alert-triangle">
        <a href="{{ route('reports.payables.export.pdf', request()->query()) }}" class="btn btn-outline-secondary">
            <i data-lucide="file-down" class="me-1"></i> Export PDF
        </a>
        <a href="{{ route('reports.payables.advances') }}" class="btn btn-light text-primary">
            <i data-lucide="wallet" class="me-1"></i> Advances
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-3 shadow-sm mb-4" style="max-width: 320px;">
            <div class="small text-muted">Total Outstanding</div>
            <div class="h4 fw-bold mb-0">₱{{ number_format($totalOutstanding, 2) }}</div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="filter"></i> Filters</div>
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-2">
                        <select name="supplier_id" class="form-select">
                            <option value="">All Suppliers</option>
                            @foreach(\App\Models\Supplier::orderBy('name')->get() as $supplier)
                            <option value="{{ $supplier->id }}" {{ (string) request('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="party" class="form-control" placeholder="Buyer / Payor" value="{{ request('party') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="alert-triangle"></i> Outstanding Obligations</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Ref #</th>
                                <th>Invoice #</th>
                                <th>Supplier</th>
                                <th>Buyer / Payor</th>
                                <th>Date</th>
                                <th class="text-end">Original Amount</th>
                                <th class="text-end">Amount Paid</th>
                                <th class="text-end">Remaining Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payables as $row)
                            <tr>
                                <td><span class="badge bg-secondary-soft text-secondary">{{ $row->module }}</span></td>
                                <td class="fw-semibold">
                                    @if($row->module === 'Purchase')
                                        <a href="{{ route('purchase-vouchers.show', $row->record) }}">{{ $row->ref_no }}</a>
                                    @else
                                        <a href="{{ route('services.show', $row->record) }}">{{ $row->ref_no }}</a>
                                    @endif
                                </td>
                                <td>{{ $row->si_no ?? '—' }}</td>
                                <td>{{ $row->supplier ?? '—' }}</td>
                                <td>{{ $row->party ?? '—' }}</td>
                                <td>{{ $row->date->format('M d, Y') }}</td>
                                <td class="text-end">₱{{ number_format($row->original_amount, 2) }}</td>
                                <td class="text-end">₱{{ number_format($row->amount_paid, 2) }}</td>
                                <td class="text-end fw-semibold">₱{{ number_format($row->remaining_balance, 2) }}</td>
                                <td>
                                    <span class="badge {{ $row->status === 'partially_paid' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger' }}">
                                        {{ ucwords(str_replace('_', ' ', $row->status)) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No outstanding payables found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
