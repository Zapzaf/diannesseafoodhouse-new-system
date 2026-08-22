@extends('layouts.app')
@section('page_title', 'Check Register')
@section('content')
    <x-page-header title="Check Register" subtitle="Log of every check actually issued" icon="check-square">
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="check-square"></i> Issued Checks</div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end mb-3 filter-form" id="checkRegisterFilterForm">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search check # or payee" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="issued" {{ request('status') === 'issued' ? 'selected' : '' }}>Issued</option>
                            <option value="cleared" {{ request('status') === 'cleared' ? 'selected' : '' }}>Cleared</option>
                            <option value="voided" {{ request('status') === 'voided' ? 'selected' : '' }}>Voided</option>
                        </select>
                    </div>
                    @include('reports.partials.period-filter', ['filters' => request()->only(['period', 'date_from', 'date_to'])])
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 text-white">Filter</button>
                        <a href="{{ route('check-register.index') }}" class="btn btn-secondary text-white text-nowrap">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Check Date</th>
                                <th>Check #</th>
                                <th>Branch</th>
                                <th>Payee</th>
                                <th>Particulars</th>
                                <th class="text-end">Amount</th>
                                <th>CV #</th>
                                <th>Status</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($checks as $check)
                            <tr>
                                <td>{{ $check->check_date->format('M d, Y') }}</td>
                                <td class="fw-semibold">{{ $check->check_no }}</td>
                                <td class="text-muted small">{{ $check->branch?->name ?? '—' }}</td>
                                <td>{{ $check->payee }}</td>
                                <td>{{ $check->particulars }}</td>
                                <td class="text-end">₱{{ number_format($check->amount, 2) }}</td>
                                <td><a href="{{ route('check-vouchers.show', $check->check_voucher_id) }}">{{ $check->checkVoucher->cv_no }}</a></td>
                                <td>
                                    <span class="badge {{ match($check->status) { 'cleared' => 'bg-success-soft text-success', 'voided' => 'bg-secondary-soft text-secondary', default => 'bg-primary-soft text-primary' } }}">
                                        {{ ucfirst($check->status) }}
                                    </span>
                                </td>
                                <td class="table-actions-cell text-nowrap">
                                    @if($check->status === 'issued')
                                    <form action="{{ route('check-register.mark-cleared', $check) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success text-white">Mark Cleared</button>
                                    </form>
                                    <form action="{{ route('check-register.void', $check) }}" method="POST" class="d-inline" onsubmit="return confirm('Void this check?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger text-white">Void</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No checks issued yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($checks->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $checks->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/reports-init.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    ReportUtils.initPeriodFilter('#checkRegisterFilterForm');
});
</script>
@endpush
