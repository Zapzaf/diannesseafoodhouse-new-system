@extends('layouts.app')
@section('page_title', 'Advances')
@section('content')
    <x-page-header title="Advances" subtitle="Cash advances to persons or the KDs fund, and their liquidation status" icon="wallet">
        <a href="{{ route('reports.payables.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Outstanding Payables
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-3 shadow-sm mb-4" style="max-width: 320px;">
            <div class="small text-muted">Total Outstanding Advances</div>
            <div class="h4 fw-bold mb-0">₱{{ number_format($totalOutstanding, 2) }}</div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="wallet"></i> Advances</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference #</th>
                                <th>Payee</th>
                                <th>Advance Account</th>
                                <th class="text-end">Advance Amount</th>
                                <th class="text-end">Liquidated</th>
                                <th class="text-end">Outstanding</th>
                                @if(auth()->user()->isAdmin())
                                <th class="table-actions-head">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($advances as $advance)
                            <tr>
                                <td>{{ $advance->date->format('M d, Y') }}</td>
                                <td><a href="{{ route('check-vouchers.show', $advance) }}">{{ $advance->reference_no ?? $advance->cv_no }}</a></td>
                                <td>{{ $advance->payee_name }}</td>
                                <td>{{ $advance->advanceAccount?->name }}</td>
                                <td class="text-end">₱{{ number_format($advance->amount_w_vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($advance->liquidated_amount, 2) }}</td>
                                <td class="text-end fw-semibold">₱{{ number_format($advance->outstanding_advance, 2) }}</td>
                                @if(auth()->user()->isAdmin())
                                <td class="table-actions-cell text-nowrap">
                                    @if($advance->liquidations->isEmpty())
                                    @php
                                        $deleteConfirm = $advance->status === 'draft'
                                            ? 'Delete this advance? This cannot be undone.'
                                            : 'This advance has already been paid out. Deleting it will also remove its Check Register entry, if any. This cannot be undone. Continue?';
                                    @endphp
                                    <form action="{{ route('check-vouchers.destroy', $advance) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $deleteConfirm }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger text-white" title="Delete"><i data-lucide="trash-2"></i></button>
                                    </form>
                                    @else
                                    <span class="text-muted">—</span>
                                    @endif
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No advances recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
