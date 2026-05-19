@extends('layouts.app')
@section('page_title', 'Add Inventory Item')
@section('content')
<main>
    <x-page-header title="Add Inventory Item" subtitle="Register a stock item with opening balance and supplier details" icon="plus-circle">
        <a href="{{ route('inventory.index') }}" class="btn btn-light text-primary">
            <i data-feather="arrow-left" class="me-1"></i> Back to Inventory
        </a>
    </x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')
    <div class="card shadow-sm">
        <div class="card-body">
            @php
                $lockedCategoryId = old('category_id', $selectedCategoryId ?? null);
                $lockedCategoryId = $lockedCategoryId !== null && $lockedCategoryId !== '' ? (int) $lockedCategoryId : null;
                $hasLockedCategory = $lockedCategoryId !== null;
                $lockedBranchId = old('branch_id', $selectedCategoryBranchId ?? null);
            @endphp
            <form action="{{ route('inventory.store') }}" method="POST">
                @csrf
                @if(auth()->user()->isAdmin())
                <div class="mb-3">
                    <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
                    <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required {{ $hasLockedCategory ? 'disabled' : '' }}>
                        <option value="">Select Branch</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (string) $lockedBranchId === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @if($hasLockedCategory)
                    <input type="hidden" name="branch_id" value="{{ $lockedBranchId }}">
                    <div class="form-text">Branch is locked because category was selected from Location > Category.</div>
                    @endif
                    @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @endif
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Subcategory <span class="text-danger">*</span></label>
                        <select name="category_id" id="categorySelect" class="form-select @error('category_id') is-invalid @enderror" required {{ $hasLockedCategory ? 'disabled' : '' }}>
                            <option value="">Select Subcategory</option>
                            @foreach($categoryOptions as $option)
                            <option value="{{ $option['id'] }}" data-branch-id="{{ $option['branch_id'] }}" {{ (string) $lockedCategoryId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                            @endforeach
                        </select>
                        @if($hasLockedCategory)
                        <input type="hidden" name="category_id" value="{{ $lockedCategoryId }}">
                        <div class="form-text">Subcategory is auto-selected from Location > Category.</div>
                        @else
                        <div class="form-text">Items can only be assigned to subcategories.</div>
                        @endif
                        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Beginning Item <span class="text-danger">*</span></label>
                        <input type="number" name="beginning_item" class="form-control @error('beginning_item') is-invalid @enderror" value="{{ old('beginning_item', 0) }}" min="0" required>
                        @error('beginning_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Unit</label>
                        @php
                            $unitOptions = [
                                'pcs',        // pieces (individual items)
                                'kg',         // kilograms (weight)
                                'g',          // grams (small weight)
                                'l',          // liters (liquid)
                                'ml',         // milliliters (small liquid)
                                'gal',        // gallons
                                'box',        // boxed items
                                'pack',       // packs/bundles
                                'bottle',     // bottled items
                                'can',        // canned goods
                                'roll',       // tissue, paper rolls
                                'set',        // grouped items
                                'pair',       // items in twos (e.g., slippers)
                                'dozen',      // 12 pieces
                                'tray'        // eggs, supplies, etc.
                            ];
                            $selectedUnit = (string) old('unit', '');
                        @endphp
                        <select name="unit" class="form-select">
                            <option value="">Select Unit</option>
                            @foreach($unitOptions as $unit)
                            <option value="{{ $unit }}" {{ $selectedUnit === $unit ? 'selected' : '' }}>{{ strtolower($unit) }}</option>
                            @endforeach
                            @if($selectedUnit !== '' && !in_array($selectedUnit, $unitOptions, true))
                            <option value="{{ $selectedUnit }}" selected>{{ strtolower($selectedUnit) }} (Current)</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" class="form-control" value="{{ old('low_stock_threshold', 5) }}" min="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Item</button>
                    <a href="{{ route('inventory.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</main>
@endsection

@push('scripts')
<script>
(function () {
    const branchSelect = document.querySelector('select[name="branch_id"]');
    const categorySelect = document.getElementById('categorySelect');
    if (!categorySelect) return;

    function filterCategoryOptions() {
        if (!branchSelect) return;
        const branchId = branchSelect.value;
        Array.from(categorySelect.options).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            option.hidden = branchId && option.dataset.branchId !== branchId;
        });

        if (categorySelect.selectedOptions[0]?.hidden) {
            categorySelect.value = '';
        }
    }

    if (branchSelect) {
        branchSelect.addEventListener('change', filterCategoryOptions);
        filterCategoryOptions();
    }
})();
</script>
@endpush
