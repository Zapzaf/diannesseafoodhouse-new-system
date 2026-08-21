@extends('layouts.app')
@section('page_title', 'Add Inventory Item')
@section('content')
    <x-page-header title="Add Inventory Item" subtitle="Register a stock item with opening balance and supplier details" icon="plus-circle">
        <a href="{{ route('inventory.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Inventory
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        @php
            $lockedCategoryId = old('category_id', $selectedCategoryId ?? null);
            $lockedCategoryId = $lockedCategoryId !== null && $lockedCategoryId !== '' ? (int) $lockedCategoryId : null;
            $hasLockedCategory = $lockedCategoryId !== null;
            $lockedBranchId = old('branch_id', $selectedCategoryBranchId ?? null);
        @endphp

        <form action="{{ route('inventory.store') }}" method="POST">
            @csrf
            
            <div class="row g-4">
                <!-- Left Column: Details -->
                <div class="col-lg-8">
                    <div class="card p-4 shadow-sm h-100">
                        <div class="card-body p-0">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                                <i data-lucide="info" style="width: 20px; height: 20px;"></i>
                                <span>General Information</span>
                            </h5>

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
                                <div class="form-text text-muted">Branch is locked because category was selected from Location > Category.</div>
                                @endif
                                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @endif

                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Fresh Salmon" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
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
                                    <div class="form-text text-muted">Subcategory is auto-selected from Location > Category.</div>
                                    @else
                                    <div class="form-text text-muted">Items can only be assigned to subcategories.</div>
                                    @endif
                                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Beginning Stock <span class="text-danger">*</span></label>
                                    <input type="number" name="beginning_item" class="form-control @error('beginning_item') is-invalid @enderror" value="{{ old('beginning_item', 0) }}" min="0" required>
                                    @error('beginning_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Unit</label>
                                    @php($selectedUnit = (string) old('unit', ''))
                                    <select name="unit" class="form-select">
                                        <option value="">Select Unit</option>
                                        @foreach($unitOptions as $value => $label)
                                        <option value="{{ $value }}" {{ $selectedUnit === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                        @if($selectedUnit !== '' && !array_key_exists($selectedUnit, $unitOptions))
                                        <option value="{{ $selectedUnit }}" selected>{{ strtolower($selectedUnit) }} (Current)</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Low Stock Threshold</label>
                                    <input type="number" name="low_stock_threshold" class="form-control" value="{{ old('low_stock_threshold', 5) }}" min="0">
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Unit Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">PHP</span>
                                        <input type="number" name="unit_price" class="form-control @error('unit_price') is-invalid @enderror" value="{{ old('unit_price') }}" min="0" step="0.01" placeholder="0.00">
                                        @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Notes -->
                <div class="col-lg-4">
                    <div class="card p-4 shadow-sm h-100">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                                <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
                                <span>Notes & Remarks</span>
                            </h5>
                            <div class="flex-grow-1 mb-3">
                                <label class="form-label fw-semibold d-none">Notes</label>
                                <textarea name="notes" class="form-control h-100" rows="6" placeholder="Specify storage instructions, vendor contact details, or other notes..." style="min-height: 150px;">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom CTA Buttons -->
            <div class="d-flex justify-content-end gap-3 mt-4">
                <a href="{{ route('inventory.index') }}" class="btn btn-secondary text-white px-4">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                    <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                    <span>Save Item</span>
                </button>
            </div>
        </form>
    </div>
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
