@extends('layouts.app')
@section('page_title', 'New Costing Report - Dianne Seafood House')
@section('content')
<x-page-header title="New Costing Report" subtitle="Submit an item price change for admin review" icon="file-plus">
    <a href="{{ route('reports.costing.index') }}" class="btn btn-light">
        <i data-lucide="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card p-4 shadow-sm">
        <div class="card-body p-0">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                <i data-lucide="edit-3" style="width: 20px; height: 20px;"></i>
                <span>Costing Details</span>
            </h5>
            <form method="POST" action="{{ route('reports.costing.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="item_id" class="form-label fw-semibold">Affected Item</label>
                        <select name="item_id" id="item_id" class="form-select @error('item_id') is-invalid @enderror" required>
                            <option value="">Select item</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}"
                                    data-price="{{ number_format((float) ($item->unit_price ?? 0), 4, '.', '') }}"
                                    @selected((int) old('item_id', $selectedItemId) === (int) $item->id)>
                                    {{ $item->name }} - {{ $item->branch?->name ?? 'N/A' }} (&#8369;{{ number_format((float) ($item->unit_price ?? 0), 4) }})
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="current_price_display" class="form-label fw-semibold">Current Item Price</label>
                        <input type="text" id="current_price_display" class="form-control" value="&#8369;0.0000" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="proposed_price" class="form-label fw-semibold">Proposed New Price</label>
                        <input type="number" step="0.0001" min="0.0001" name="proposed_price" id="proposed_price" class="form-control @error('proposed_price') is-invalid @enderror" value="{{ old('proposed_price') }}" required>
                        @error('proposed_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label for="reason" class="form-label fw-semibold">Reason / Justification</label>
                        <textarea name="reason" id="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="costing_details" class="form-label fw-semibold">Supporting Costing Details</label>
                        <textarea name="costing_details" id="costing_details" rows="5" class="form-control @error('costing_details') is-invalid @enderror" placeholder="Ingredients, inventory cost changes, labor, overhead, markup, or other supporting computations">{{ old('costing_details') }}</textarea>
                        @error('costing_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4">
                    <a href="{{ route('reports.costing.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                        <i data-lucide="send" style="width: 18px; height: 18px;"></i>
                        <span>Submit for Review</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemSelect = document.getElementById('item_id');
    const priceDisplay = document.getElementById('current_price_display');

    function updateCurrentPrice() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        const price = Number(selected && selected.dataset.price ? selected.dataset.price : 0);
        priceDisplay.value = '\u20b1' + price.toLocaleString(undefined, {
            minimumFractionDigits: 4,
            maximumFractionDigits: 4
        });
    }

    itemSelect.addEventListener('change', updateCurrentPrice);
    updateCurrentPrice();
});
</script>
@endpush
