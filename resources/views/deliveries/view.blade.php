@extends('layouts.app')

@section('page_title', 'Delivery ' . $delivery->reference_number . ' - Dianne Seafood House')

@section('content')
    <x-page-header title="Delivery Details" subtitle="{{ $delivery->reference_number }}" icon="truck">
        @can('delete', $delivery)
        <form action="{{ route('deliveries.destroy', $delivery) }}" method="POST" class="d-inline m-0"
              onsubmit="return confirm('Delete delivery {{ $delivery->reference_number }}?\n\nThis will reverse ALL inventory changes from this delivery, and delete any linked production records (including finished ones) after reversing their inputs, outputs, and scrap.\n\nThis cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i data-lucide="trash-2" class="me-1"></i> Delete Delivery
            </button>
        </form>
        @endcan
        <a href="{{ route('deliveries.index') }}" class="btn btn-secondary text-white">
            <i data-lucide="arrow-left" class="me-1"></i> All Deliveries
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        {{-- Delivery Info Card --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Delivery Information</span>
                @php
                    $statusLabels = ['pending' => 'PENDING REVIEW', 'received' => 'APPROVED', 'rejected' => 'REJECTED'];
                    $statusClass = $delivery->status === 'rejected' ? 'badge-expired' : 'badge-'.$delivery->status;
                @endphp
                <span class="badge-status {{ $statusClass }}">{{ $statusLabels[$delivery->status] ?? strtoupper($delivery->status) }}</span>
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
                @if($delivery->status === 'rejected' && $delivery->rejection_remarks)
                <div class="alert alert-danger mt-3 mb-0">
                    <div class="fw-semibold">Rejection Remarks</div>
                    <div>{{ $delivery->rejection_remarks }}</div>
                </div>
                @endif
                @if($delivery->approval_remarks)
                <div class="alert alert-secondary mt-3 mb-0">
                    <div class="fw-semibold">Approval Remarks</div>
                    <div>{{ $delivery->approval_remarks }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- BIR / Tax Details Card --}}
        @if($delivery->tin || $delivery->address || $delivery->si_no || (float) $delivery->amount_w_vat > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">BIR / Tax Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Supplier TIN</div>
                        <div>{{ $delivery->tin ?: '—' }}</div>
                    </div>
                    <div class="col-md-5">
                        <div class="small text-muted">Supplier Address</div>
                        <div>{{ $delivery->address ?: '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Invoice / Receipt No.</div>
                        <div>{{ $delivery->si_no ?: '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Amount w/ VAT</div>
                        <div>&#8369;{{ number_format((float) $delivery->amount_w_vat, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Net Purchases</div>
                        <div>&#8369;{{ number_format((float) $delivery->net_purchases, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">VAT</div>
                        <div>&#8369;{{ number_format((float) $delivery->vat, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">EWT Amount</div>
                        <div>&#8369;{{ number_format((float) $delivery->ewt_amount, 2) }} ({{ number_format((float) $delivery->ewt_rate * 100, 0) }}%)</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">VAT-Exempt</div>
                        <div>&#8369;{{ number_format((float) $delivery->vat_exempt, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Non-VAT Purchase</div>
                        <div>&#8369;{{ number_format((float) $delivery->non_vat_purchase, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

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
                    <button type="submit" class="btn btn-sm btn-primary text-white">
                        <i data-lucide="save" class="me-1"></i> Save Prices
                    </button>
                </div>
                </form>
            @endif
            </div>
        </div>

        {{-- Edit (pending deliveries the creator/approver can still fix) --}}
        @if($delivery->status === 'pending')
            @can('update', $delivery)
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex justify-content-end">
                    <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-outline-primary">
                        <i data-lucide="pencil" class="me-1"></i> Edit Delivery
                    </a>
                </div>
            </div>
            @endcan
        @endif

        {{-- Approve / Reject (pending deliveries only, approvers only) --}}
        @if($delivery->status === 'pending')
            @can('approve', $delivery)
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Review This Delivery</div>
                <div class="card-body d-flex justify-content-end gap-2 flex-wrap">
                    <form method="POST" action="{{ route('deliveries.reject', $delivery) }}" id="reject-form" class="d-inline">
                        @csrf
                        <input type="hidden" name="rejection_remarks" id="rejectionRemarksInput">
                        <button type="button" class="btn btn-outline-danger" id="rejectBtn">
                            <i data-lucide="x-circle" class="me-1"></i> Reject
                        </button>
                    </form>
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
                @error('rejection_remarks') <div class="alert alert-danger py-2 mx-3 mb-3">{{ $message }}</div> @enderror
            </div>
            @endcan
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rejectBtn = document.getElementById('rejectBtn');
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function () {
            const remarks = prompt('Please enter the reason for rejecting this delivery:');
            if (remarks === null) return;
            if (!remarks.trim()) {
                alert('Rejection remarks are required.');
                return;
            }
            document.getElementById('rejectionRemarksInput').value = remarks.trim();
            document.getElementById('reject-form').submit();
        });
    }
});
</script>
@endpush
