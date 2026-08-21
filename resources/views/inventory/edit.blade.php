@extends('layouts.app')
@section('page_title', 'Edit Inventory Item')
@section('content')
    <x-page-header :title="'Edit Inventory Item - ' . $inventory->name" subtitle="Update stock metadata while preserving beginning and remaining counts" icon="edit">
        <a href="{{ route('inventory.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Inventory
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('inventory.update', $inventory) }}" method="POST">
            @csrf @method('PUT')
            
            <div class="row g-4">
                <!-- Left Column: Details -->
                <div class="col-lg-8">
                    <div class="card p-4 shadow-sm h-100">
                        <div class="card-body p-0">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                                <i data-lucide="info" style="width: 20px; height: 20px;"></i>
                                <span>General Information</span>
                            </h5>

                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $inventory->name) }}" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Subcategory <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                        <option value="">Select Subcategory</option>
                                        @foreach($categoryOptions as $option)
                                        <option value="{{ $option['id'] }}" {{ (string) old('category_id', $inventory->category_id) === (string) $option['id'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text text-muted">Items can only be assigned to subcategories.</div>
                                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Beginning Stock</label>
                                    <input type="number" class="form-control" value="{{ $inventory->beginning_item }}" disabled>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Remaining Stock</label>
                                    <input type="number" class="form-control" value="{{ $inventory->currentStock() }}" disabled>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Unit</label>
                                    @php($selectedUnit = (string) old('unit', $inventory->unit))
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
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Unit Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">PHP</span>
                                        <input type="number" name="unit_price" class="form-control @error('unit_price') is-invalid @enderror" value="{{ old('unit_price', $inventory->unit_price) }}" min="0" step="0.01" placeholder="0.00">
                                        @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Low Stock Threshold</label>
                                    <input type="number" name="low_stock_threshold" class="form-control" value="{{ old('low_stock_threshold', $inventory->low_stock_threshold) }}" min="0">
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
                                <textarea name="notes" class="form-control h-100" rows="6" placeholder="Specify storage instructions, vendor contact details, or other notes..." style="min-height: 150px;">{{ old('notes', $inventory->notes) }}</textarea>
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
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
@endsection
