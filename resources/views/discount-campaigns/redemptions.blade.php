@extends('layouts.app')
@section('page_title', 'Redemption History - Dianne\'s Seafood House')
@section('content')
<x-page-header title="Redemption History" :subtitle="$campaign->name" icon="history">
    <a class="btn btn-primary" href="{{ route('discount-campaigns.index') }}">
        <i data-lucide="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Redemptions ({{ $redemptions->total() }})</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Source</th>
                            <th class="text-end">Discount Amount</th>
                            <th>Applied By</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($redemptions as $redemption)
                        <tr>
                            <td class="fw-semibold small">{{ $redemption->order?->order_number ?? ('#' . $redemption->menu_order_id) }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($redemption->source) }}</span></td>
                            <td class="text-end">₱{{ number_format($redemption->discount_amount, 2) }}</td>
                            <td class="text-muted small">{{ $redemption->appliedBy->name ?? '—' }}</td>
                            <td><span class="badge {{ $redemption->status === 'applied' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($redemption->status) }}</span></td>
                            <td class="text-muted small text-nowrap">{{ $redemption->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No redemptions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($redemptions->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $redemptions->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
