@extends('layouts.app')
@section('page_title', 'Expenses')
@section('content')
<x-page-header title="Expenses" subtitle="Monthly expense and sales summary by branch" icon="trending-down">
    {{-- Jump to month --}}
    <form method="GET" id="goToMonthForm" class="d-inline-flex align-items-center gap-2">
        <input type="month" id="goToMonthInput" class="form-control form-control-md" style="width:160px;" required>
        <button type="submit" class="btn btn-success btn-md">
            <i data-lucide="arrow-right" class="me-1"></i> Go to Month
        </button>
    </form>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
        <i data-lucide="upload" class="me-1"></i> Import
    </button>
</x-page-header>
<script>
document.getElementById('goToMonthForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const val = document.getElementById('goToMonthInput').value;
    if (val) window.location.href = '/expenses/' + val;
});
</script>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- Months summary table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i data-lucide="calendar" class="me-1" style="width:16px;height:16px;"></i> Imported Months
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Month</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th class="text-end">Gross Sales</th>
                            <th class="text-end">Net Sales</th>
                            <th class="text-end">Vatable Purchases</th>
                            <th class="text-end">Non-Vatable</th>
                            <th class="text-end">Disbursements</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($months as $month)
                        <tr>
                            <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $month->month_year)->format('F Y') }}</td>
                            @if(auth()->user()?->isAdmin())
                            <td class="text-muted small">{{ $month->branch_name ?? 'All' }}</td>
                            @endif
                            <td class="text-end">₱{{ number_format((float) $month->gross_sales, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->net_sales, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->total_vatable, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->total_non_vatable, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->total_disbursements, 2) }}</td>
                            <td class="text-center">
                                <a href="{{ route('expenses.show', $month->month_year) }}" class="btn btn-sm btn-outline-primary">
                                    <i data-lucide="eye" style="width:14px;height:14px;"></i> View
                                </a>
                                <a href="{{ route('expenses.export', $month->month_year) }}{{ $selectedBranchId ? '?branch_id='.$selectedBranchId : '' }}"
                                   class="btn btn-sm btn-outline-success">
                                    <i data-lucide="download" style="width:14px;height:14px;"></i> Export
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->isAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">
                                No expense data imported yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('expenses.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="upload" class="me-1"></i> Import Expense File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                        <input type="month" name="month_year" class="form-control" required>
                        <div class="form-text">Select the month this file covers.</div>
                    </div>
                    @if(auth()->user()?->isAdmin())
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">-- Select Branch --</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File (.xlsx / .xls) <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        <div class="form-text">
                            File must have 3 sheets in order: Vatable Purchases, Non-Vatable, Cash Disbursement.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="upload" class="me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
