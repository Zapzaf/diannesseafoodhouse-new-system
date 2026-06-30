@extends('layouts.app')
@section('page_title', 'Processing - Dianne Seafood House')
@section('content')
<x-page-header title="Processing" subtitle="Active production orders currently in progress" icon="loader">
    <a href="{{ route('productions.index') }}" class="btn btn-light text-primary">
        <i data-lucide="arrow-left" class="me-1"></i> All Productions
    </a>
    <a href="{{ route('productions.create') }}" class="btn btn-primary">
        <i data-lucide="plus" class="me-1"></i> Start Production
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="loader" class="me-1"></i> In-Progress Orders</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-server-page-sort="1">
                    <thead>
                        <tr>
                            <th data-sort-key="id">Order</th>
                            <th data-sort-key="branch_name">Branch</th>
                            <th data-sort-key="created_at">Started</th>
                            <th data-sort-key="inputs_count">Input Details</th>
                            <th data-sort-key="outputs_count">Output Details</th>
                            <th class="table-actions-head">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productions as $production)
                        <tr>
                            <td>
                                <div class="fw-semibold">Production #{{ $production->id }}</div>
                                <div class="small text-muted">by {{ $production->creator?->name ?? 'System' }}</div>
                            </td>
                            <td>{{ $production->branch?->name ?? '—' }}</td>
                            <td class="text-nowrap text-muted small">{{ $production->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <x-production-item-summary :items="$production->inputs" quantity-field="quantity_used" empty="No inputs" variant="input" />
                            </td>
                            <td>
                                <x-production-item-summary :items="$production->outputs" quantity-field="quantity_produced" empty="No outputs yet" variant="output" />
                            </td>
                            <td class="table-actions-cell">
                                <a href="{{ route('productions.show', $production) }}" class="btn btn-sm btn-primary">Open</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No production orders currently in progress.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($productions->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $productions->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
