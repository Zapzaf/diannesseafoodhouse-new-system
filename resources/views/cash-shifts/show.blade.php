@extends('layouts.app')

@section('page_title', 'Cash Shift - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Cash Shift" :subtitle="($shift->terminal->name ?? '—') . ' · ' . ($shift->cashier->name ?? '—')" icon="wallet">
        <div class="d-flex gap-2">
            @if($shift->isOpen())
            <a href="{{ route('reports.y-reading.index', ['cash_shift_id' => $shift->id]) }}" class="btn btn-light text-primary">
                <i data-lucide="file-text" class="me-1"></i> Y Reading
            </a>
            @endif
            <a href="{{ route('cash-shifts.index') }}" class="btn btn-light text-primary">
                <i data-lucide="arrow-left" class="me-1"></i> Back
            </a>
        </div>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Shift Details</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8"><span class="badge {{ $shift->isOpen() ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($shift->status) }}</span></dd>

                            <dt class="col-sm-4">Terminal</dt>
                            <dd class="col-sm-8">{{ $shift->terminal->name ?? '—' }} ({{ $shift->terminal->code ?? '—' }})</dd>

                            <dt class="col-sm-4">Branch</dt>
                            <dd class="col-sm-8">{{ $shift->branch->name ?? '—' }}</dd>

                            <dt class="col-sm-4">Cashier</dt>
                            <dd class="col-sm-8">{{ $shift->cashier->name ?? '—' }}</dd>

                            <dt class="col-sm-4">Opened</dt>
                            <dd class="col-sm-8">{{ $shift->opened_at->format('M d, Y h:i A') }} by {{ $shift->openedBy->name ?? '—' }}</dd>

                            @if(!$shift->isOpen())
                            <dt class="col-sm-4">Closed</dt>
                            <dd class="col-sm-8">{{ $shift->closed_at?->format('M d, Y h:i A') }} by {{ $shift->closedBy->name ?? '—' }}</dd>
                            @endif

                            @if($shift->notes)
                            <dt class="col-sm-4">Notes</dt>
                            <dd class="col-sm-8 text-muted">{{ $shift->notes }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Cash Summary</div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-7">Opening Float</dt>
                            <dd class="col-5 text-end">₱{{ number_format($shift->opening_float, 2) }}</dd>
                            <dt class="col-7">Cash Sales</dt>
                            <dd class="col-5 text-end">₱{{ number_format($cashSales, 2) }}</dd>
                            <dt class="col-7">Cash In</dt>
                            <dd class="col-5 text-end text-success">+₱{{ number_format($cashIn, 2) }}</dd>
                            <dt class="col-7">Cash Out</dt>
                            <dd class="col-5 text-end text-danger">-₱{{ number_format($cashOut, 2) }}</dd>
                            <dt class="col-7 fw-bold border-top pt-2 mt-2">Expected Cash</dt>
                            <dd class="col-5 text-end fw-bold border-top pt-2 mt-2">₱{{ number_format($expectedCash, 2) }}</dd>
                            @if(!$shift->isOpen())
                            <dt class="col-7">Counted Cash</dt>
                            <dd class="col-5 text-end">₱{{ number_format($shift->closing_cash_counted, 2) }}</dd>
                            <dt class="col-7 fw-bold">Variance</dt>
                            <dd class="col-5 text-end fw-bold {{ (float) $shift->cash_variance < 0 ? 'text-danger' : ((float) $shift->cash_variance > 0 ? 'text-warning' : 'text-success') }}">
                                ₱{{ number_format($shift->cash_variance, 2) }}
                            </dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        @if($shift->isOpen())
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Record Cash In / Out</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('cash-shifts.cash-movement', $shift) }}" class="row g-2">
                            @csrf
                            <div class="col-4">
                                <select name="type" class="form-select" required>
                                    <option value="in">Cash In</option>
                                    <option value="out">Cash Out</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="Amount" required>
                            </div>
                            <div class="col-4">
                                <button type="submit" class="btn btn-primary w-100">Save</button>
                            </div>
                            <div class="col-12">
                                <input type="text" name="reason" class="form-control" placeholder="Reason (e.g. petty cash, drawer top-up)" required>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Close Shift</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('cash-shifts.close', $shift) }}" onsubmit="return confirm('Close this shift? This cannot be undone.')">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label fw-bold">Counted Cash (PHP) <span class="text-danger">*</span></label>
                                <input type="number" name="closing_cash_counted" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="mb-2">
                                <textarea name="notes" rows="2" class="form-control" placeholder="Closing notes (optional)"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">
                                <i data-lucide="lock" class="me-1"></i> Close Shift
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Cash Movements</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Reason</th>
                                <th class="text-end">Amount</th>
                                <th>Recorded By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($shift->movements as $movement)
                            <tr>
                                <td><span class="badge {{ $movement->type === 'in' ? 'bg-success' : 'bg-danger' }}">{{ strtoupper($movement->type) }}</span></td>
                                <td>{{ $movement->reason }}</td>
                                <td class="text-end">₱{{ number_format($movement->amount, 2) }}</td>
                                <td class="text-muted small">{{ $movement->recordedBy->name ?? '—' }}</td>
                                <td class="text-muted small">{{ $movement->created_at->format('M d, Y h:i A') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No cash movements recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
