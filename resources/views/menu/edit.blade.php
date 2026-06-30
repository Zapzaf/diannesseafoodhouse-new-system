@extends('layouts.app')

@section('page_title', 'Edit Menu Item - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Edit Menu Item" :subtitle="$menu->name" icon="edit">
        <a class="btn btn-outline-primary" href="{{ route('menus.show', $menu) }}">
            <i data-lucide="eye" class="me-1"></i> View
        </a>
        <a class="btn btn-primary" href="{{ route('menus.index') }}">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('menus.update', $menu) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="card mb-4">
                <div class="card-header"><i data-lucide="info" class="me-1"></i> Menu Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Menu Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name', $menu->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="menu_description" rows="3"
                                      class="form-control @error('menu_description') is-invalid @enderror"
                                      placeholder="Briefly describe this menu item (optional)…">{{ old('menu_description', $menu->menu_description) }}</textarea>
                            @error('menu_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Selling Price (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('selling_price') is-invalid @enderror"
                                   name="selling_price" value="{{ old('selling_price', $menu->selling_price) }}"
                                   step="0.01" min="0" required>
                            @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Branch</label>
                            <input type="text" class="form-control" value="{{ $menu->branch->name ?? '—' }}" readonly disabled>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Menu Category <span class="text-danger">*</span></label>
                            <select class="form-select @error('menu_category_id') is-invalid @enderror" name="menu_category_id" required>
                                <option value="">Select Menu Category</option>
                                @foreach($menuCategories as $cat)
                                <option value="{{ $cat->id }}" {{ (string) old('menu_category_id', $menu->menu_category_id) === (string) $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('menu_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Menu Image</label>
                            @if($menu->image)
                            <div class="mb-2">
                                <img src="{{ asset('storage/' . $menu->image) }}" style="max-height:100px;border-radius:8px;border:1px solid #ddd;">
                                <span class="text-muted small ms-2">Current image</span>
                            </div>
                            @endif
                            <input type="file" class="form-control @error('image') is-invalid @enderror"
                                   name="image" id="imageInput" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                            <div class="form-text">Upload a new photo (JPEG, PNG, WebP, max 2MB). Leave blank to keep the current image.</div>
                            <div id="imagePreview" class="mt-2" style="display:none;">
                                <img src="" style="max-height:120px;border-radius:8px;border:1px solid #ddd;">
                            </div>
                            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i data-lucide="list" class="me-1"></i> Ingredients (Recipe / BOM)</div>
                    <button type="button" id="addIngredientBtn" class="btn btn-sm btn-outline-primary">
                        <i data-lucide="plus" class="me-1"></i> Add Ingredient
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Define how much inventory stock is consumed per one unit of this menu item. Ingredients are filtered to the menu's branch.</p>

                    <div id="ingredientsContainer">
                        @php
                            $existingIngredients = old('ingredients')
                                ? collect(old('ingredients'))
                                : $menu->items->map(fn($item) => [
                                    'item_id'      => $item->id,
                                    'quantity_required' => $item->pivot->quantity_required,
                                  ]);
                        @endphp

                        @forelse($existingIngredients as $i => $ing)
                        <div class="row g-2 mb-2 ingredient-row">
                            <div class="col-md-7">
                                <select class="form-select ingredient-select" name="ingredients[{{ $i }}][item_id]" required>
                                    <option value="">-- Select Inventory Item --</option>
                                    @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-branch-id="{{ $item->branch_id }}" {{ $ing['item_id'] == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }} ({{ $item->unit }}) — Stock: {{ $item->quantity }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control" name="ingredients[{{ $i }}][quantity_required]"
                                       value="{{ $ing['quantity_required'] }}" step="0.01" min="0.01"
                                       placeholder="Qty required" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger w-100 btn-remove-ingredient">
                                    <i data-lucide="trash-2"></i> Remove
                                </button>
                            </div>
                        </div>
                        @empty
                        <div class="row g-2 mb-2 ingredient-row">
                            <div class="col-md-7">
                                <select class="form-select ingredient-select" name="ingredients[0][item_id]" required>
                                    <option value="">-- Select Inventory Item --</option>
                                    @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-branch-id="{{ $item->branch_id }}">
                                        {{ $item->name }} ({{ $item->unit }}) — Stock: {{ $item->quantity }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control" name="ingredients[0][quantity_required]"
                                       step="0.01" min="0.01" placeholder="Qty required" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger w-100 btn-remove-ingredient">
                                    <i data-lucide="trash-2"></i> Remove
                                </button>
                            </div>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="{{ route('menus.index') }}" class="btn btn-secondary text-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="me-1"></i> Update Menu Item
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
const itemOptions = `{!! json_encode($items->map(fn($item) => [
    'id'    => $item->id,
    'label' => $item->name . ' (' . $item->unit . ') — Stock: ' . $item->quantity,
    'branch_id' => $item->branch_id,
])->values()) !!}`;

let rowIndex = document.querySelectorAll('.ingredient-row').length;

function buildOptions(selectedId = '') {
    const branchId = '{{ $menu->branch_id }}';
    let opts = '<option value="">-- Select Inventory Item --</option>';
    JSON.parse(itemOptions).forEach(item => {
        if (item.branch_id != branchId) return;
        const sel = item.id == selectedId ? ' selected' : '';
        opts += `<option value="${item.id}"${sel} data-branch-id="${item.branch_id}">${item.label}</option>`;
    });
    return opts;
}

document.getElementById('addIngredientBtn').addEventListener('click', function () {
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 ingredient-row';
    row.innerHTML = `
        <div class="col-md-7">
            <select class="form-select ingredient-select" name="ingredients[${rowIndex}][item_id]" required>
                ${buildOptions()}
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" class="form-control" name="ingredients[${rowIndex}][quantity_required]"
                   step="0.01" min="0.01" placeholder="Qty required" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger w-100 btn-remove-ingredient">
                <i data-lucide="trash-2"></i> Remove
            </button>
        </div>`;
    document.getElementById('ingredientsContainer').appendChild(row);
    rowIndex++;
    if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
});

document.getElementById('ingredientsContainer').addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-remove-ingredient');
    if (!btn) return;
    const rows = document.querySelectorAll('.ingredient-row');
    if (rows.length <= 1) {
        alert('A menu item must have at least one ingredient.');
        return;
    }
    btn.closest('.ingredient-row').remove();
});

// Image preview
document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    const img = preview.querySelector('img');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            img.src = ev.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        img.src = '';
        preview.style.display = 'none';
    }
});
</script>
@endpush