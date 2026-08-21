@extends('layouts.app')
@section('page_title', 'Services')
@section('content')
    <x-page-header title="Services" subtitle="Electricity, water, internet, professional fees, and other service expenses" icon="file-text">
        <a href="{{ route('services.create') }}" class="btn btn-primary">
            <i data-lucide="plus-circle" class="me-1"></i> New Service
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="file-text"></i> Services</div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search Ref #, payor, SI #, or supplier" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 text-white">Filter</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Ref #</th>
                                <th>Supplier</th>
                                <th>Payor</th>
                                <th>Type</th>
                                <th>Branch</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($services as $service)
                            <tr>
                                <td>{{ $service->date->format('M d, Y') }}</td>
                                <td class="fw-semibold">{{ $service->ref_no }}</td>
                                <td>{{ $service->supplier?->name ?? '—' }}</td>
                                <td>{{ $service->payor }}</td>
                                <td>{{ $service->expenseAccount?->name }}</td>
                                <td class="text-muted small">{{ $service->branch?->name ?? '—' }}</td>
                                <td class="text-end">₱{{ number_format($service->total_purchases, 2) }}</td>
                                <td>
                                    <span class="badge {{ match($service->status) { 'paid' => 'bg-success-soft text-success', 'partially_paid' => 'bg-warning-soft text-warning', default => 'bg-danger-soft text-danger' } }}">
                                        {{ ucwords(str_replace('_', ' ', $service->status)) }}
                                    </span>
                                </td>
                                <td class="table-actions-cell text-nowrap">
                                    <a href="{{ route('services.show', $service) }}" class="btn btn-sm btn-info text-white" title="View"><i data-lucide="eye"></i></a>
                                    @if($service->status === 'unpaid')
                                    <a href="{{ route('services.edit', $service) }}" class="btn btn-sm btn-primary text-white" title="Edit"><i data-lucide="edit-2"></i></a>
                                    <form action="{{ route('services.destroy', $service) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this Service?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger text-white" title="Delete"><i data-lucide="trash-2"></i></button>
                                    </form>
                                    @elseif($service->status === 'partially_paid')
                                    <a href="{{ route('check-vouchers.create', ['pay_service' => $service->id]) }}" class="btn btn-sm btn-success text-white">Pay</a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No services found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($services->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $services->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
