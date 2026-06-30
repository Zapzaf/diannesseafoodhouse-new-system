@extends('layouts.app')
@section('page_title', 'Expenses — ' . $monthLabel)
@section('content')
<x-page-header title="{{ $monthLabel }}" subtitle="Expense and sales breakdown" icon="file-text">
    <a href="{{ route('expenses.export', $monthYear) }}" class="btn btn-success">
        <i data-lucide="download" class="me-1"></i> Export Excel
    </a>
    <a href="{{ route('expenses.index') }}" class="btn btn-light text-primary ms-2">
        <i data-lucide="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-primary border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Gross Sales</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) $grossSales, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-success border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Net Sales</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) $netSales, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-warning border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Total Purchases</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) ($totalVatable + $totalNonVatable), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-danger border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Disbursements</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) $totalDisbursements, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    @php
        $activeTab = request('tab', 'sales');
        $requiresExpenseBranch = auth()->user()?->isAdmin() && empty($selectedBranchId);
    @endphp

    <ul class="nav nav-tabs mb-0" id="expenseTabs">
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'sales' ? 'active' : '' }}" href="?tab=sales"><i data-lucide="trending-up" style="width:14px;height:14px;" class="me-1"></i> Daily Sales</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'vatable' ? 'active' : '' }}" href="?tab=vatable"><i data-lucide="file-minus" style="width:14px;height:14px;" class="me-1"></i> Vatable</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'nonvatable' ? 'active' : '' }}" href="?tab=nonvatable"><i data-lucide="file" style="width:14px;height:14px;" class="me-1"></i> Non-Vatable</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'disbursements' ? 'active' : '' }}" href="?tab=disbursements"><i data-lucide="credit-card" style="width:14px;height:14px;" class="me-1"></i> Disbursements</a></li>
    </ul>

    <div class="card shadow-sm" style="border-top-left-radius:0;">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                @if($activeTab==='sales') <i data-lucide="trending-up" class="me-1" style="width:16px;height:16px;"></i> Daily Sales (from Payments)
                @elseif($activeTab==='vatable') <i data-lucide="file-minus" class="me-1" style="width:16px;height:16px;"></i> Vatable Purchases
                @elseif($activeTab==='nonvatable') <i data-lucide="file" class="me-1" style="width:16px;height:16px;"></i> Non-Vatable Purchases
                @else <i data-lucide="credit-card" class="me-1" style="width:16px;height:16px;"></i> Cash Disbursements
                @endif
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <div class="input-group input-group-sm" style="max-width:220px;">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i data-lucide="search" style="width:14px;height:14px;"></i></button>
                    </div>
                </form>
                @if($activeTab === 'vatable')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addVatableModal">
                        <i data-lucide="plus" class="me-1" style="width:14px;height:14px;"></i> Add Vatable
                    </button>
                @elseif($activeTab === 'nonvatable')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNonVatableModal">
                        <i data-lucide="plus" class="me-1" style="width:14px;height:14px;"></i> Add Non-Vatable
                    </button>
                @elseif($activeTab === 'disbursements')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDisbursementModal">
                        <i data-lucide="plus" class="me-1" style="width:14px;height:14px;"></i> Add Disbursement
                    </button>
                @endif
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">

                {{-- SALES TAB --}}
                @if($activeTab === 'sales')
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>OR No.</th><th>Customer</th><th>Method</th>
                            <th class="text-end">Amount</th><th class="text-end">Discount</th>
                            <th class="text-end">VAT</th><th class="text-end">Final Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesRecords as $record)
                        <tr>
                            <td>{{ $salesRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->payment_date?->format('M d, Y') }}</td>
                            @if(auth()->user()?->isAdmin())<td class="text-muted small">{{ $record->order?->branch?->name ?? '—' }}</td>@endif
                            <td class="text-muted small">{{ $record->or_number ?? '—' }}</td>
                            <td>{{ $record->order?->customerDisplayName() ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($record->method) }}</span></td>
                            <td class="text-end">₱{{ number_format((float)$record->amount,2) }}</td>
                            <td class="text-end text-muted">₱{{ number_format((float)$record->discount_amount,2) }}</td>
                            <td class="text-end text-muted">₱{{ number_format((float)$record->vat_amount,2) }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float)$record->final_total,2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 10 : 9 }}" class="text-center text-muted py-4">No sales records for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- VATABLE TAB --}}
                @elseif($activeTab === 'vatable')
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>Vendor Name</th><th>Address</th><th>SI No.</th><th>TIN</th>
                            <th class="text-end">Gross</th><th class="text-end">VAT</th><th class="text-end">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vatableRecords as $record)
                        <tr>
                            <td>{{ $vatableRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->date?->format('M d, Y') ?? '—' }}</td>
                            @if(auth()->user()?->isAdmin())<td class="text-muted small">{{ $record->branch?->name ?? '—' }}</td>@endif
                            <td>{{ $record->vendor_name }}</td>
                            <td class="text-muted small">{{ $record->address ?? '—' }}</td>
                            <td class="text-muted small">{{ $record->si_number ?? '—' }}</td>
                            <td class="text-muted small">{{ $record->tin ?? '—' }}</td>
                            <td class="text-end">₱{{ number_format((float)$record->gross_amount,2) }}</td>
                            <td class="text-end">₱{{ number_format((float)$record->vat,2) }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float)$record->net_purchases,2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 10 : 9 }}" class="text-center text-muted py-4">No vatable purchases for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- NON-VATABLE TAB --}}
                @elseif($activeTab === 'nonvatable')
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>Vendor Name</th><th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($nonVatableRecords as $record)
                        <tr>
                            <td>{{ $nonVatableRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->date?->format('M d, Y') ?? '—' }}</td>
                            @if(auth()->user()?->isAdmin())<td class="text-muted small">{{ $record->branch?->name ?? '—' }}</td>@endif
                            <td>{{ $record->vendor_name }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float)$record->gross_amount,2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 5 : 4 }}" class="text-center text-muted py-4">No non-vatable purchases for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- DISBURSEMENTS TAB --}}
                @else
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>Check No.</th><th>Payee</th><th class="text-end">Amount</th><th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($disbursementRecords as $record)
                        <tr>
                            <td>{{ $disbursementRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->date?->format('M d, Y') ?? '—' }}</td>
                            @if(auth()->user()?->isAdmin())<td class="text-muted small">{{ $record->branch?->name ?? '—' }}</td>@endif
                            <td class="text-muted small">{{ $record->check_number ?? '—' }}</td>
                            <td>{{ $record->payee }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float)$record->amount,2) }}</td>
                            <td class="text-muted small">{{ $record->reference ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 7 : 6 }}" class="text-center text-muted py-4">No disbursements for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @endif

            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            @php
                $activePaginator = match($activeTab) {
                    'vatable'       => $vatableRecords,
                    'nonvatable'    => $nonVatableRecords,
                    'disbursements' => $disbursementRecords,
                    default         => $salesRecords,
                };
            @endphp
            <div class="text-muted small">
                Showing {{ $activePaginator->firstItem() ?? 0 }} to {{ $activePaginator->lastItem() ?? 0 }} of {{ $activePaginator->total() }} entries
            </div>
            <div class="mb-0 custom-pagination-wrapper">
                {{ $activePaginator->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
        <style>
            .custom-pagination-wrapper nav { margin-bottom: 0 !important; }
            .custom-pagination-wrapper p.small.text-muted { display: none !important; }
            .custom-pagination-wrapper .pagination { margin-bottom: 0 !important; }
        </style>
    </div>
</div>

<div class="modal fade" id="addVatableModal" tabindex="-1" aria-labelledby="addVatableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('expenses.vatable.store', $monthYear) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addVatableModalLabel">Add Vatable Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @if($requiresExpenseBranch)
                            <div class="col-12">
                                <label class="form-label">Branch <span class="text-danger">*</span></label>
                                <select name="branch_id" class="form-select" required>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="{{ $monthYear }}-01">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" name="vendor_name" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SI Number</label>
                            <input type="text" name="si_number" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">TIN</label>
                            <input type="text" name="tin" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gross Amount <span class="text-danger">*</span></label>
                            <input type="number" name="gross_amount" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">VAT <span class="text-danger">*</span></label>
                            <input type="number" name="vat" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Net Purchases <span class="text-danger">*</span></label>
                            <input type="number" name="net_purchases" class="form-control" min="0" step="0.01" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Vatable Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addNonVatableModal" tabindex="-1" aria-labelledby="addNonVatableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('expenses.nonvatable.store', $monthYear) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addNonVatableModalLabel">Add Non-Vatable Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($requiresExpenseBranch)
                        <div class="mb-3">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select Branch</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="{{ $monthYear }}-01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                        <input type="text" name="vendor_name" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Gross Amount <span class="text-danger">*</span></label>
                        <input type="number" name="gross_amount" class="form-control" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Non-Vatable Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addDisbursementModal" tabindex="-1" aria-labelledby="addDisbursementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('expenses.disbursement.store', $monthYear) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addDisbursementModalLabel">Add Cash Disbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($requiresExpenseBranch)
                        <div class="mb-3">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select Branch</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="{{ $monthYear }}-01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Check Number</label>
                        <input type="text" name="check_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payee <span class="text-danger">*</span></label>
                        <input type="text" name="payee" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" min="0" step="0.01" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Disbursement</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
