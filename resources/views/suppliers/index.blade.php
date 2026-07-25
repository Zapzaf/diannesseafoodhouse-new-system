@extends('layouts.app')
@section('page_title', 'Suppliers - Dianne Seafood House')

@section('content')
    <x-page-header title="Suppliers" subtitle="Manage external suppliers and contact details" icon="truck">
        <a href="{{ route('suppliers.create') }}" class="btn btn-light text-primary">
            <i data-lucide="plus-circle" class="me-1"></i> Add Supplier
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><i data-lucide="truck" class="me-1" style="width: 16px; height: 16px;"></i> All Suppliers</div>
                <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                    @if(request('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if(request('direction'))
                        <input type="hidden" name="direction" value="{{ request('direction') }}">
                    @endif
                    <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="5" {{ request('per_page') == 5 ? 'selected' : '' }}>5</option>
                        <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search suppliers..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i data-lucide="search" style="width: 14px; height: 14px;"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" data-server-page-sort="1">
                        <thead>
                            <tr>
                                <th data-sort-key="name">Name</th>
                                <th data-sort-key="type">Type</th>
                                <th>Owner</th>
                                <th data-sort-key="contact_person">Contact Person</th>
                                <th data-sort-key="phone">Phone</th>
                                <th data-sort-key="email">Email</th>
                                <th>Address</th>
                                <th class="table-actions-head text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($suppliers as $supplier)
                            <tr>
                                <td class="fw-semibold">{{ $supplier->name }}</td>
                                <td>
                                    <span class="badge {{ $supplier->type === 'sole_proprietorship' ? 'bg-info text-dark' : 'bg-secondary' }}">
                                        {{ $supplier->type === 'sole_proprietorship' ? 'Single Proprietorship' : 'Company' }}
                                    </span>
                                </td>
                                <td class="text-muted small">{{ $supplier->owner_name ?? '—' }}</td>
                                <td>{{ $supplier->contact_person ?? '—' }}</td>
                                <td>{{ $supplier->phone ?? '—' }}</td>
                                <td>{{ $supplier->email ?? '—' }}</td>
                                <td class="text-muted small">{{ $supplier->address ?? '—' }}</td>
                                <td class="table-actions-cell text-end">
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No suppliers found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Showing {{ $suppliers->firstItem() ?? 0 }} to {{ $suppliers->lastItem() ?? 0 }} of {{ $suppliers->total() }} entries
                </div>
                <div class="mb-0 custom-pagination-wrapper">
                    {{ $suppliers->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
            <style>
                .custom-pagination-wrapper nav { margin-bottom: 0 !important; }
                .custom-pagination-wrapper p.small.text-muted { display: none !important; }
                .custom-pagination-wrapper .pagination { margin-bottom: 0 !important; }
            </style>
        </div>
    </div>
@endsection
