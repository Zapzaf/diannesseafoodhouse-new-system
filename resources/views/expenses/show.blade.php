@extends('layouts.app')
@section('page_title', 'Expenses — ' . $monthLabel)
@section('content')
<main>
<x-page-header title="{{ $monthLabel }}" subtitle="Expense and sales breakdown" icon="file-text">
    <a href="{{ route('expenses.export', $monthYear) }}" class="btn btn-success">
        <i data-feather="download" class="me-1"></i> Export Excel
    </a>
    <a href="{{ route('expenses.index') }}" class="btn btn-light text-primary ms-2">
        <i data-feather="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4 mt-n10">
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

    @php $activeTab = request('tab', 'sales'); @endphp

    <ul class="nav nav-tabs mb-0" id="expenseTabs">
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'sales' ? 'active' : '' }}" href="?tab=sales"><i data-feather="trending-up" style="width:14px;height:14px;" class="me-1"></i> Daily Sales</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'vatable' ? 'active' : '' }}" href="?tab=vatable"><i data-feather="file-minus" style="width:14px;height:14px;" class="me-1"></i> Vatable</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'nonvatable' ? 'active' : '' }}" href="?tab=nonvatable"><i data-feather="file" style="width:14px;height:14px;" class="me-1"></i> Non-Vatable</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'disbursements' ? 'active' : '' }}" href="?tab=disbursements"><i data-feather="credit-card" style="width:14px;height:14px;" class="me-1"></i> Disbursements</a></li>
    </ul>

    <div class="card shadow-sm" style="border-top-left-radius:0;">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                @if($activeTab==='sales') <i data-feather="trending-up" class="me-1" style="width:16px;height:16px;"></i> Daily Sales (from Payments)
                @elseif($activeTab==='vatable') <i data-feather="file-minus" class="me-1" style="width:16px;height:16px;"></i> Vatable Purchases
                @elseif($activeTab==='nonvatable') <i data-feather="file" class="me-1" style="width:16px;height:16px;"></i> Non-Vatable Purchases
                @else <i data-feather="credit-card" class="me-1" style="width:16px;height:16px;"></i> Cash Disbursements
                @endif
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                {{-- Add New button (not for sales) --}}
                @if($activeTab === 'vatable')
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createVatableModal">
                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add New
                </button>
                @elseif($activeTab === 'nonvatable')
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createNonVatableModal">
                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add New
                </button>
                @elseif($activeTab === 'disbursements')
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createDisbursementModal">
                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add New
                </button>
                @endif
                <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <div class="input-group input-group-sm" style="max-width:220px;">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i data-feather="search" style="width:14px;height:14px;"></i></button>
                    </div>
                </form>
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
                            <th class="text-center">Actions</th>
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
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-warning" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editVatableModal"
                                        data-id="{{ $record->id }}" data-my="{{ $monthYear }}"
                                        data-date="{{ $record->date?->format('Y-m-d') }}"
                                        data-vendor="{{ $record->vendor_name }}"
                                        data-address="{{ $record->address }}"
                                        data-si="{{ $record->si_number }}"
                                        data-tin="{{ $record->tin }}"
                                        data-gross="{{ $record->gross_amount }}"
                                        data-vat="{{ $record->vat }}"
                                        data-net="{{ $record->net_purchases }}">
                                        <i data-feather="edit-2" style="width:13px;height:13px;"></i>
                                    </button>
                                    <form action="{{ route('expenses.vatable.destroy', [$monthYear, $record->id]) }}" method="POST" onsubmit="return confirm('Delete this record?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i data-feather="trash-2" style="width:13px;height:13px;"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 11 : 10 }}" class="text-center text-muted py-4">No vatable purchases for this period.</td></tr>
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
                            <th class="text-center">Actions</th>
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
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-warning" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editNonVatableModal"
                                        data-id="{{ $record->id }}" data-my="{{ $monthYear }}"
                                        data-date="{{ $record->date?->format('Y-m-d') }}"
                                        data-vendor="{{ $record->vendor_name }}"
                                        data-gross="{{ $record->gross_amount }}">
                                        <i data-feather="edit-2" style="width:13px;height:13px;"></i>
                                    </button>
                                    <form action="{{ route('expenses.nonvatable.destroy', [$monthYear, $record->id]) }}" method="POST" onsubmit="return confirm('Delete this record?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i data-feather="trash-2" style="width:13px;height:13px;"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 6 : 5 }}" class="text-center text-muted py-4">No non-vatable purchases for this period.</td></tr>
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
                            <th class="text-center">Actions</th>
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
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-warning" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editDisbursementModal"
                                        data-id="{{ $record->id }}" data-my="{{ $monthYear }}"
                                        data-date="{{ $record->date?->format('Y-m-d') }}"
                                        data-check="{{ $record->check_number }}"
                                        data-payee="{{ $record->payee }}"
                                        data-amount="{{ $record->amount }}"
                                        data-reference="{{ $record->reference }}">
                                        <i data-feather="edit-2" style="width:13px;height:13px;"></i>
                                    </button>
                                    <form action="{{ route('expenses.disbursement.destroy', [$monthYear, $record->id]) }}" method="POST" onsubmit="return confirm('Delete this record?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i data-feather="trash-2" style="width:13px;height:13px;"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">No disbursements for this period.</td></tr>
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

{{-- Modals --}}
@include('expenses.partials.modal-vatable')
@include('expenses.partials.modal-nonvatable')
@include('expenses.partials.modal-disbursement')

</main>
@endsection
