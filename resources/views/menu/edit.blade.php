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
                <div class="card-header fw-semibold"><i data-lucide="info" class="me-1"></i> Menu Details</div>
                <div class="card-body">
                    <div class="row g-4">
                        {{-- Left: fields --}}
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Menu Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                           name="name" value="{{ old('name', $menu->name) }}" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
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

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Selling Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control @error('selling_price') is-invalid @enderror"
                                               name="selling_price" value="{{ old('selling_price', $menu->selling_price) }}"
                                               step="0.01" min="0" required>
                                        @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Branch</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-lucide="map-pin" style="width:15px;height:15px;"></i></span>
                                        <input type="text" class="form-control" value="{{ $menu->branch->name ?? '—' }}" readonly disabled>
                                    </div>
                                    <div class="form-text">The branch cannot be changed after creation.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                                        <span>Description</span>
                                        <span class="text-muted small fw-normal"><span id="descCount">0</span> characters</span>
                                    </label>
                                    <textarea name="menu_description" rows="5" id="descriptionInput"
                                              class="form-control @error('menu_description') is-invalid @enderror"
                                              placeholder="Briefly describe this menu item (optional)…">{{ old('menu_description', $menu->menu_description) }}</textarea>
                                    @error('menu_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Descriptions longer than 32 characters are truncated in the menu list.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Right: image --}}
                        <div class="col-lg-4">
                            <label class="form-label fw-bold">Menu Image</label>
                            <div class="border rounded-3 p-3 text-center h-auto">
                                <div class="d-flex align-items-center justify-content-center mb-3 rounded-3 overflow-hidden mx-auto"
                                     style="aspect-ratio: 4 / 3; max-width: 320px; background: rgba(var(--primary-color-rgb), 0.06);">
                                    <img id="imagePreviewImg"
                                         src="{{ $menu->image ? asset('storage/' . $menu->image) : '' }}"
                                         alt="Menu image preview"
                                         class="{{ $menu->image ? '' : 'd-none' }}"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                    <div id="imagePlaceholder" class="text-muted {{ $menu->image ? 'd-none' : '' }}">
                                        <i data-lucide="image" style="width: 42px; height: 42px; opacity: .45;"></i>
                                        <div class="small mt-2">No image yet</div>
                                    </div>
                                </div>
                                <div id="imageStatus" class="small text-muted mb-2">
                                    {{ $menu->image ? 'Current image' : 'Upload a photo to showcase this dish' }}
                                </div>
                                <input type="file" class="form-control @error('image') is-invalid @enderror"
                                       name="image" id="imageInput" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                                @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">JPEG, PNG or WebP, max 2MB. Leave blank to keep the current image.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">
                        <i data-lucide="list" class="me-1"></i> Ingredients (Recipe / BOM)
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-1" id="ingredientCount"></span>
                    </div>
                    <button type="button" id="addIngredientBtn" class="btn btn-sm btn-outline-primary">
                        <i data-lucide="plus" class="me-1"></i> Add Ingredient
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="no_ingredients" value="1" id="noIngredientsToggle" {{ old('no_ingredients', $menu->no_ingredients) ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="noIngredientsToggle">No Ingredients</label>
                        <div class="form-text">Enable this if this menu item does not consume inventory ingredients (e.g. a beverage add-on or a flat fee).</div>
                    </div>

                    <div id="ingredientsSection">
                    <p class="text-muted small mb-3">Define how much inventory stock is consumed per one unit of this menu item. Ingredients are filtered to the menu's branch.</p>

                    <div class="row g-2 mb-1 d-none d-md-flex text-muted small fw-semibold text-uppercase" style="letter-spacing: .04em;">
                        <div class="col-md-7">Inventory Item</div>
                        <div class="col-md-4">Quantity per Serving</div>
                        <div class="col-md-1"></div>
                    </div>

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
                        <div class="row g-2 mb-2 ingredient-row align-items-start">
                            <div class="col-md-7">
                                <select class="form-select ingredient-select" name="ingredients[{{ $i }}][item_id]" required>
                                    <option value="">-- Select Inventory Item --</option>
                                    @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-branch-id="{{ $item->branch_id }}" data-unit="{{ $item->unit }}" {{ $ing['item_id'] == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }} ({{ $item->unit }}) — Stock: {{ $item->quantity }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="number" class="form-control" name="ingredients[{{ $i }}][quantity_required]"
                                           value="{{ $ing['quantity_required'] }}" step="0.01" min="0.01"
                                           placeholder="Qty required" required>
                                    <span class="input-group-text unit-addon">unit</span>
                                </div>
                            </div>
                            <div class="col-md-1 d-grid">
                                <button type="button" class="btn btn-outline-danger btn-remove-ingredient" title="Remove ingredient">
                                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                                </button>
                            </div>
                        </div>
                        @empty
                        <div class="row g-2 mb-2 ingredient-row align-items-start">
                            <div class="col-md-7">
                                <select class="form-select ingredient-select" name="ingredients[0][item_id]" required>
                                    <option value="">-- Select Inventory Item --</option>
                                    @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-branch-id="{{ $item->branch_id }}" data-unit="{{ $item->unit }}">
                                        {{ $item->name }} ({{ $item->unit }}) — Stock: {{ $item->quantity }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="number" class="form-control" name="ingredients[0][quantity_required]"
                                           step="0.01" min="0.01" placeholder="Qty required" required>
                                    <span class="input-group-text unit-addon">unit</span>
                                </div>
                            </div>
                            <div class="col-md-1 d-grid">
                                <button type="button" class="btn btn-outline-danger btn-remove-ingredient" title="Remove ingredient">
                                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                                </button>
                            </div>
                        </div>
                        @endforelse
                    </div>
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
const itemOptions = {!! $items->map(fn($item) => [
    'id'    => $item->id,
    'label' => $item->name . ' (' . $item->unit . ') — Stock: ' . $item->quantity,
    'unit'  => $item->unit,
    'branch_id' => $item->branch_id,
])->values()->toJson() !!};

let rowIndex = document.querySelectorAll('.ingredient-row').length;

const noIngredientsToggle = document.getElementById('noIngredientsToggle');
const ingredientsSection = document.getElementById('ingredientsSection');
const addIngredientBtnEl = document.getElementById('addIngredientBtn');

function applyNoIngredientsToggle() {
    const enabled = !!noIngredientsToggle?.checked;
    ingredientsSection?.classList.toggle('d-none', enabled);
    if (addIngredientBtnEl) addIngredientBtnEl.disabled = enabled;
    document.querySelectorAll('.ingredient-select').forEach((select) => { select.required = !enabled; });
    document.querySelectorAll('.ingredient-row input[name*="[quantity_required]"]').forEach((input) => { input.required = !enabled; });
}

noIngredientsToggle?.addEventListener('change', applyNoIngredientsToggle);
applyNoIngredientsToggle();

function buildOptions(selectedId = '') {
    const branchId = '{{ $menu->branch_id }}';
    let opts = '<option value="">-- Select Inventory Item --</option>';
    itemOptions.forEach(item => {
        if (item.branch_id != branchId) return;
        const sel = item.id == selectedId ? ' selected' : '';
        opts += `<option value="${item.id}"${sel} data-branch-id="${item.branch_id}" data-unit="${item.unit ?? ''}">${item.label}</option>`;
    });
    return opts;
}

function updateIngredientCount() {
    const count = document.querySelectorAll('.ingredient-row').length;
    const badge = document.getElementById('ingredientCount');
    if (badge) badge.textContent = count + ' item' + (count === 1 ? '' : 's');
}

function updateUnitAddon(select) {
    const row = select.closest('.ingredient-row');
    const addon = row && row.querySelector('.unit-addon');
    if (!addon) return;
    const unit = select.selectedOptions[0]?.dataset.unit || '';
    addon.textContent = unit || 'unit';
}

document.getElementById('addIngredientBtn').addEventListener('click', function () {
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 ingredient-row align-items-start';
    row.innerHTML = `
        <div class="col-md-7">
            <select class="form-select ingredient-select" name="ingredients[${rowIndex}][item_id]" required>
                ${buildOptions()}
            </select>
        </div>
        <div class="col-md-4">
            <div class="input-group">
                <input type="number" class="form-control" name="ingredients[${rowIndex}][quantity_required]"
                       step="0.01" min="0.01" placeholder="Qty required" required>
                <span class="input-group-text unit-addon">unit</span>
            </div>
        </div>
        <div class="col-md-1 d-grid">
            <button type="button" class="btn btn-outline-danger btn-remove-ingredient" title="Remove ingredient">
                <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
            </button>
        </div>`;
    document.getElementById('ingredientsContainer').appendChild(row);
    rowIndex++;
    updateIngredientCount();
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
    updateIngredientCount();
});

document.getElementById('ingredientsContainer').addEventListener('change', function (e) {
    if (e.target.classList.contains('ingredient-select')) {
        updateUnitAddon(e.target);
    }
});

// Live image preview inside the image panel
document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const img = document.getElementById('imagePreviewImg');
    const placeholder = document.getElementById('imagePlaceholder');
    const status = document.getElementById('imageStatus');
    const hasCurrent = @json((bool) $menu->image);
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            img.src = ev.target.result;
            img.classList.remove('d-none');
            placeholder.classList.add('d-none');
            status.textContent = 'New image preview — saved when you update';
        };
        reader.readAsDataURL(file);
    } else {
        if (hasCurrent) {
            img.src = @json($menu->image ? asset('storage/' . $menu->image) : '');
            status.textContent = 'Current image';
        } else {
            img.classList.add('d-none');
            placeholder.classList.remove('d-none');
            status.textContent = 'Upload a photo to showcase this dish';
        }
    }
});

// Description character counter
const descInput = document.getElementById('descriptionInput');
const descCount = document.getElementById('descCount');
function refreshDescCount() { descCount.textContent = descInput.value.length; }
descInput.addEventListener('input', refreshDescCount);
refreshDescCount();

// Show each row's unit on load
document.querySelectorAll('.ingredient-select').forEach(updateUnitAddon);
updateIngredientCount();
</script>
@endpush
