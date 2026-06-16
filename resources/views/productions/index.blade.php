@extends('layouts.app')

@section('page_title', 'Production - Dianne Seafood House')

@section('content')
<main>
    <x-page-header title="Production Orders" subtitle="Track production batches from raw inputs to finished outputs" icon="tool">
        <a href="{{ route('productions.create') }}" class="btn btn-light">
            <i data-feather="plus" class="me-1"></i> Start Production
        </a>
    </x-page-header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>Production Orders</div>
                <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                    <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="5" {{ request('per_page') == 5 ? 'selected' : '' }}>5</option>
                        <option value="10" {{ request('per_page', 10) == 10 || request('per_page', 12) == 10 || request('per_page', 15) == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i data-feather="search" style="width: 14px; height: 14px;"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Order</th>
                                @if(auth()->user()?->isAdmin())
                                <th>Branch</th>
                                @endif
                                <th>Status</th>
                                <th>Inputs</th>
                                <th>Outputs</th>
                                <th class="table-actions-head">Action</th>
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
                                <td>{{ $production->inputs->count() }}</td>
                                <td>{{ $production->outputs->count() }}</td>
                                <td class="table-actions-cell">
                                    <a href="{{ route('productions.show', $production) }}" class="btn btn-sm btn-primary">Open</a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="{{ auth()->user()?->isAdmin() ? 6 : 5 }}" class="text-center text-muted py-4">No production orders yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($productions->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $productions->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</main>
@endsection
