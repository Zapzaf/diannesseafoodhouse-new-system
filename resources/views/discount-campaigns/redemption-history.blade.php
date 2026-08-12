@extends('layouts.app')
@section('page_title', 'Coupon Redemption History - Dianne\'s Seafood House')
@section('content')
<x-page-header title="Coupon Redemption History" subtitle="Every promo discount ever applied, across all campaigns" icon="history">
    <a class="btn btn-primary" href="{{ route('discount-campaigns.index') }}">
        <i data-lucide="arrow-left" class="me-1"></i> Back to Campaigns
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('discount-campaigns.redemption-history') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Campaign</label>
                    <select name="campaign_id" class="form-select form-select-sm">
                        <option value="">All Campaigns</option>
                        @foreach($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" {{ (string) $filters['campaign_id'] === (string) $campaign->id ? 'selected' : '' }}>{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Source</label>
                    <select name="source" class="form-select form-select-sm">
                        <option value="">All Sources</option>
                        <option value="coupon" {{ $filters['source'] === 'coupon' ? 'selected' : '' }}>Coupon</option>
                        <option value="automatic" {{ $filters['source'] === 'automatic' ? 'selected' : '' }}>Automatic</option>
                        <option value="manual" {{ $filters['source'] === 'manual' ? 'selected' : '' }}>Manual</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Code, order #, or guest name" value="{{ $filters['search'] }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('discount-campaigns.redemption-history') }}" class="btn btn-sm btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Redemptions ({{ $redemptions->total() }})</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Campaign</th>
                            <th>Source</th>
                            <th>Code Used</th>
                            <th>Customer / Guest</th>
                            <th class="text-end">Discount Amount</th>
                            <th>Applied By</th>
                            <th>Status</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($redemptions as $redemption)
                        <tr>
                            <td class="fw-semibold small">
                                @if($redemption->order)
                                <a href="{{ route('menu-orders.show', $redemption->menu_order_id) }}">{{ $redemption->order->order_number }}</a>
                                @else
                                #{{ $redemption->menu_order_id }}
                                @endif
                            </td>
                            <td class="small">
                                @if($redemption->campaign)
                                <a href="{{ route('discount-campaigns.redemptions', $redemption->campaign) }}">{{ $redemption->campaign->name }}</a>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary">{{ ucfirst($redemption->source) }}</span></td>
                            <td class="text-muted small">{{ $redemption->code_used ?? '—' }}</td>
                            <td class="small">{{ $redemption->order?->customerDisplayName() ?? '—' }}</td>
                            <td class="text-end">₱{{ number_format($redemption->discount_amount, 2) }}</td>
                            <td class="text-muted small">{{ $redemption->appliedBy->name ?? '—' }}</td>
                            <td><span class="badge {{ $redemption->status === 'applied' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($redemption->status) }}</span></td>
                            <td class="text-muted small text-nowrap">{{ $redemption->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No redemptions found for these filters.</td></tr>
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
