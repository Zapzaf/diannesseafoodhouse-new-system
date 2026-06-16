@extends('layouts.app')
@section('page_title', 'Edit Inventory Item')
@section('content')
<main>
    <x-page-header :title="'Edit Inventory Item - ' . $inventory->name" subtitle="Update stock metadata while preserving beginning and remaining counts" icon="edit">
        <a href="{{ route('inventory.index') }}" class="btn btn-light text-primary">
            <i data-feather="arrow-left" class="me-1"></i> Back to Inventory
        </a>
    </x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('inventory.update', $inventory) }}" method="POST">
                @csrf @method('PUT')
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $inventory->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Subcategory <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                            <option value="">Select Subcategory</option>
                            @foreach($categoryOptions as $option)
                            <option value="{{ $option['id'] }}" {{ (string) old('category_id', $inventory->category_id) === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                            @endforeach
                        </select>
                        <div class="form-text">Items can only be assigned to subcategories.</div>
                        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Beginning Item</label>
                        <input type="number" class="form-control" value="{{ $inventory->beginning_item }}" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Remaining Item</label>
                        <input type="number" class="form-control" value="{{ $inventory->currentStock() }}" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
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
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" class="form-control" value="{{ old('low_stock_threshold', $inventory->low_stock_threshold) }}" min="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $inventory->notes) }}</textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('inventory.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</main>
@endsection
