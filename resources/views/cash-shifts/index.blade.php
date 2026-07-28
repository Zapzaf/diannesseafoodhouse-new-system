@extends('layouts.app')

@section('page_title', 'Cash Shifts - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Cash Shifts" subtitle="Open, close, and track cashier shifts per POS terminal" icon="wallet">
        @if($myOpenShift)
        <a href="{{ route('cash-shifts.show', $myOpenShift) }}" class="btn btn-light text-primary">
            <i data-lucide="wallet" class="me-1"></i> My Open Shift
        </a>
        @else
        <a href="{{ route('cash-shifts.create') }}" class="btn btn-light text-primary">
            <i data-lucide="plus-circle" class="me-1"></i> Open Shift
        </a>
        @endif
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('cash-shifts.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    <div class="col-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="{{ route('cash-shifts.index') }}" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Shifts</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Terminal</th>
                                <th>Cashier</th>
                                <th>Branch</th>
                                <th>Opened</th>
                                <th>Closed</th>
                                <th class="text-end">Opening Float</th>
                                <th class="text-end">Variance</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($shifts as $shift)
                            <tr>
                                <td>{{ $shift->terminal->name ?? '—' }}</td>
                                <td>{{ $shift->cashier->name ?? '—' }}</td>
                                <td class="text-muted small">{{ $shift->branch->name ?? '—' }}</td>
                                <td class="text-nowrap small">{{ $shift->opened_at->format('M d, Y h:i A') }}</td>
                                <td class="text-nowrap small">{{ $shift->closed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                <td class="text-end">₱{{ number_format($shift->opening_float, 2) }}</td>
                                <td class="text-end">
                                    @if($shift->cash_variance !== null)
                                        <span class="{{ (float) $shift->cash_variance < 0 ? 'text-danger' : ((float) $shift->cash_variance > 0 ? 'text-warning' : 'text-success') }} fw-semibold">
                                            ₱{{ number_format($shift->cash_variance, 2) }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="badge {{ $shift->status === 'open' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($shift->status) }}</span></td>
                                <td><a href="{{ route('cash-shifts.show', $shift) }}" class="btn btn-sm btn-outline-info"><i data-lucide="eye"></i></a></td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No cash shifts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($shifts->hasPages())
            <div class="card-footer d-flex justify-content-center">
                {{ $shifts->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
    </div>
@endsection
