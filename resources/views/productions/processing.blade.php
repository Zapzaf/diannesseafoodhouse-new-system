@extends('layouts.app')
@section('page_title', 'Processing - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Processing" subtitle="Active production orders currently in progress" icon="loader">
    <a href="{{ route('productions.index') }}" class="btn btn-light text-primary">
        <i data-feather="arrow-left" class="me-1"></i> All Productions
    </a>
    <a href="{{ route('productions.create') }}" class="btn btn-primary">
        <i data-feather="plus" class="me-1"></i> Start Production
    </a>
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-feather="loader" class="me-1"></i> In-Progress Orders</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Order</th>
                            <th>Branch</th>
                            <th>Started</th>
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
                                <div class="small text-muted">by {{ $production->creator?->name ?? 'System' }}</div>
                            </td>
                            <td>{{ $production->branch?->name ?? '—' }}</td>
                            <td class="text-nowrap text-muted small">{{ $production->created_at->format('M d, Y H:i') }}</td>
                            <td>{{ $production->inputs->count() }} item(s)</td>
                            <td>{{ $production->outputs->count() }} item(s)</td>
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
</main>
@endsection
