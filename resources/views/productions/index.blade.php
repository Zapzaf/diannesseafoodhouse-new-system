@extends('layouts.app')

@section('page_title', 'Production - Dianne Seafood House')

@section('content')
    <x-page-header title="Production Orders" subtitle="Track production batches from raw inputs to finished outputs" icon="wrench">
        <a href="{{ route('productions.create') }}" class="btn btn-light">
            <i data-lucide="plus" class="me-1"></i> Start Production
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>Production Orders</div>
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
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i data-lucide="search" style="width: 14px; height: 14px;"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" data-server-page-sort="1">
                        <thead>
                            <tr>
                                <th data-sort-key="id">Production Info</th>
                                @if(auth()->user()?->isAdmin())
                                <th data-sort-key="branch_name">Branch</th>
                                @endif
                                <th data-sort-key="status">Status</th>
                                <th data-sort-key="created_at">Created Date</th>
                                <th data-sort-key="finished_at">Finished Date</th>
                                <th data-sort-key="inputs_count">Input Details</th>
                                <th data-sort-key="outputs_count">Output Details</th>
                                <th class="table-actions-head text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($productions as $production)
                            <tr>
                                <td>
                                    <div class="fw-semibold">Production #{{ $production->id }}</div>
                                    <div class="small text-muted">Created by {{ $production->creator?->name ?? 'System' }}</div>
                                </td>
                                @if(auth()->user()?->isAdmin())
                                <td>{{ $production->branch?->name ?? 'N/A' }}</td>
                                @endif
                                <td><span class="badge-status badge-{{ $production->status }}">{{ strtoupper(str_replace('_', ' ', $production->status)) }}</span></td>
                                <td class="text-muted small">{{ $production->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td class="text-muted small">{{ $production->finished_at?->format('M d, Y') ?? '—' }}</td>
                                <td>
                                    <x-production-item-summary :items="$production->inputs" quantity-field="quantity_used" empty="No inputs" variant="input" />
                                </td>
                                <td>
                                    <x-production-item-summary :items="$production->outputs" quantity-field="quantity_produced" empty="No outputs yet" variant="output" />
                                </td>
                                <td class="table-actions-cell text-end">
                                    <a href="{{ route('productions.show', $production) }}" class="btn btn-sm btn-outline-secondary" title="Open"><i data-lucide="eye"></i></a>
                                    @if($production->status === 'in_progress' && $production->outputs_count === 0)
                                    <form action="{{ route('productions.cancel', $production) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel Production #{{ $production->id }}? Pulled inputs will be restocked to inventory.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Production"><i data-lucide="x-circle"></i></button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ auth()->user()?->isAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">No production orders yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Showing {{ $productions->firstItem() ?? 0 }} to {{ $productions->lastItem() ?? 0 }} of {{ $productions->total() }} entries
                </div>
                <div class="mb-0 custom-pagination-wrapper">
                    {{ $productions->onEachSide(1)->links('pagination::bootstrap-5') }}
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
