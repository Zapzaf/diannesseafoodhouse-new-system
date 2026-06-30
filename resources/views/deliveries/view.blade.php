@extends('layouts.app')

@section('page_title', 'Delivery ' . $delivery->reference_number . ' - Dianne Seafood House')

@section('content')
    <x-page-header title="Delivery Details" subtitle="{{ $delivery->reference_number }}" icon="truck">
        <a href="{{ route('deliveries.index') }}" class="btn btn-outline-primary">
            <i data-lucide="arrow-left" class="me-1"></i> All Deliveries
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        {{-- Delivery Info Card --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Delivery Information</span>
                <span class="badge-status badge-{{ $delivery->status }}">{{ strtoupper($delivery->status) }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Reference</div>
                        <div class="fw-semibold">{{ $delivery->reference_number }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Supplier</div>
                        <div>
                            @if($delivery->sourceBranch)
                                {{ $delivery->sourceBranch->name }}
                            @else
                                {{ $delivery->supplier?->name ?? '—' }}
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Destination Branch</div>
                        <div>{{ $delivery->destinationBranch?->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Date</div>
                        <div>{{ $delivery->created_at->format('M d, Y H:i') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Created By</div>
                        <div>{{ $delivery->creator?->name ?? 'System' }}</div>
                    </div>
                    @if($delivery->approver)
                    <div class="col-md-3">
                        <div class="small text-muted">Approved By</div>
                        <div>{{ $delivery->approver->name }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Approved At</div>
                        <div>{{ $delivery->approved_at?->format('M d, Y H:i') ?? '—' }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Items Card --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Delivery Items</div>
            <div class="card-body">
            @if(auth()->user()?->isAdmin())
                <form method="POST" action="{{ route('deliveries.prices.update', $delivery) }}" class="mb-3">
                    @csrf
            @endif
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item Description</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Price</th>
                            <th>Destination</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalPrice = 0.0;
                        @endphp
                        @foreach($delivery->items as $index => $item)
                        @php
                            $price = $item->price !== null ? (float) $item->price : null;
                            $qty   = (float) $item->quantity;
                            if ($price !== null) { $totalPrice += $price; }
                        @endphp
                        <tr>
                            <td class="text-muted small">{{ $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->item?->name ?? $item->description ?? 'Unknown item' }}</div>
                                @if($item->item)
                                <div class="small text-muted">#{{ $item->item->id }} &mdash; {{ $item->item->category?->name ?? '' }}</div>
                                @endif
                                @if($item->sourceItem)
                                <div class="small text-muted text-primary"><i data-lucide="corner-down-right" class="me-1" style="width: 12px; height: 12px;"></i>From: {{ $item->sourceItem->name }}</div>
                                @endif
                                @if($item->description && $item->description !== ($item->item?->name ?? ''))
                                <div class="small text-muted fst-italic">Note: {{ $item->description }}</div>
                                @endif
                            </td>
                            <td>{{ number_format($qty, 2) }}</td>
                            <td>{{ $item->unit }}</td>
                            <td>
                                @if(auth()->user()?->isAdmin())
                                    <input type="hidden" name="items[{{ $index }}][delivery_item_id]" value="{{ $item->id }}">
                                    <div class="input-group input-group-sm" style="max-width: 180px;">
                                        <span class="input-group-text">&#8369;</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form-control"
                                            name="items[{{ $index }}][price]"
                                            value="{{ $item->price !== null ? number_format((float) $item->price, 2, '.', '') : '' }}"
                                            placeholder="0.00"
                                        >
                                    </div>
                                @else
                                    @if($price !== null)
                                        &#8369;{{ number_format($price, 2) }}
                                    @else
                                        &mdash;
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($item->allocated_to === 'production')
                                    <span class="badge bg-warning text-white">Production</span>
                                @elseif($item->allocated_to === 'inventory')
                                    <span class="badge bg-primary">Inventory</span>
                                @else
                                    <span class="text-muted small">— pending</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <div class="fw-semibold">
                    Total Price: &#8369;{{ number_format($totalPrice, 2) }}
                </div>
            </div>
            @if(auth()->user()?->isAdmin())
                <div class="d-flex justify-content-end mt-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i data-lucide="save" class="me-1"></i> Save Prices
                    </button>
                </div>
                </form>
            @endif
            </div>
        </div>

        {{-- Approve form (pending deliveries only) --}}
        @if($delivery->status === 'pending')
            @can('approve', $delivery)
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex justify-content-end">
                    <form method="POST" action="{{ route('deliveries.approve', $delivery) }}">
                        @csrf
                        @foreach($delivery->items as $index => $item)
                        <input type="hidden" name="items[{{ $index }}][delivery_item_id]" value="{{ $item->id }}">
                        <input type="hidden" name="items[{{ $index }}][allocated_to]" value="{{ $item->allocated_to ?? 'inventory' }}">
                        @endforeach
                        <button type="submit" class="btn btn-success">
                            <i data-lucide="check-circle" class="me-1"></i> Approve Delivery
                        </button>
                    </form>
                </div>
            </div>
            @endcan
        @endif
    </div>
@endsection
