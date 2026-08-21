@extends('layouts.app')
@section('page_title', 'Discounts & Coupons - Dianne\'s Seafood House')
@section('content')
<x-page-header title="Discounts & Coupons" subtitle="Promotional discounts and coupon codes — separate from PWD/Senior discounts" icon="tag">
    <a class="btn btn-secondary text-white" href="{{ route('discount-campaigns.redemption-history') }}">
        <i data-lucide="history" class="me-1"></i> Redemption History
    </a>
    <a class="btn btn-light text-primary" href="{{ route('discount-campaigns.create') }}">
        <i data-lucide="plus-circle" class="me-1"></i> New Campaign
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('discount-campaigns.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('discount-campaigns.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Campaigns</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Branch</th>
                            <th>Type</th>
                            <th class="text-end">Value</th>
                            <th>Validity</th>
                            <th class="text-end">Usage</th>
                            <th>Status</th>
                            <th class="table-actions-head">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campaigns as $campaign)
                        <tr>
                            <td class="fw-semibold">{{ $campaign->name }}</td>
                            <td>
                                @if($campaign->codes->isNotEmpty())
                                @if($campaign->unified_usage_limit)
                                <div class="small text-muted mb-1"><i data-lucide="link" style="width:11px;height:11px;"></i> Unified limit — shared by all codes</div>
                                @endif
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($campaign->codes as $code)
                                    <span class="badge {{ $code->isExhausted() ? 'bg-secondary text-decoration-line-through' : 'bg-secondary' }}" title="{{ $code->usage_count }}{{ $code->usage_limit ? ' / '.$code->usage_limit : '' }} used">
                                        {{ $code->code }}{{ $campaign->unified_usage_limit ? '' : ' ('.$code->usage_count.($code->usage_limit ? '/'.$code->usage_limit : '').')' }}
                                    </span>
                                    @endforeach
                                </div>
                                @else
                                <span class="text-muted small fst-italic">Automatic</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $campaign->branch->name ?? 'All Branches' }}</td>
                            <td class="text-muted small">{{ ucfirst($campaign->type) }}</td>
                            <td class="text-end">
                                {{ $campaign->type === 'percentage' ? number_format($campaign->value, 2) . '%' : '₱' . number_format($campaign->value, 2) }}
                            </td>
                            <td class="text-muted small text-nowrap">
                                {{ $campaign->starts_at?->format('M d, Y') ?? 'Anytime' }} &ndash; {{ $campaign->ends_at?->format('M d, Y') ?? 'No expiry' }}
                            </td>
                            <td class="text-end small">
                                {{ $campaign->redemptions_count }}{{ $campaign->usage_limit ? ' / ' . $campaign->usage_limit : '' }}
                            </td>
                            <td><span class="badge {{ $campaign->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $campaign->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="table-actions-cell text-nowrap">
                                <a href="{{ route('discount-campaigns.redemptions', $campaign) }}" class="btn btn-sm btn-info text-white" title="Redemption History"><i data-lucide="history"></i></a>
                                <a href="{{ route('discount-campaigns.edit', $campaign) }}" class="btn btn-sm btn-primary text-white" title="Edit"><i data-lucide="edit"></i></a>
                                <form action="{{ route('discount-campaigns.toggle-active', $campaign) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $campaign->is_active ? 'btn-warning' : 'btn-success' }} text-white" title="{{ $campaign->is_active ? 'Deactivate' : 'Activate' }}">
                                        <i data-lucide="{{ $campaign->is_active ? 'toggle-right' : 'toggle-left' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('discount-campaigns.destroy', $campaign) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this campaign?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger text-white" title="Delete"><i data-lucide="trash-2"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No discount campaigns yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($campaigns->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $campaigns->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
