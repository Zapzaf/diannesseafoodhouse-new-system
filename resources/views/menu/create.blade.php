@extends('layouts.app')

@section('page_title', 'Add Menu Item - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Add Menu Item" subtitle="Create a new menu item with ingredient recipe" icon="plus-circle">
        <a class="btn btn-primary" href="{{ route('menus.index') }}">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Menu
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div id="menuFormAlert" class="alert alert-danger d-none" role="alert"></div>

        <form action="{{ route('menus.store') }}" method="POST" enctype="multipart/form-data" id="menuCreateForm" novalidate>
            @csrf

            @if(!auth()->user()->isAdmin())
                <input type="hidden" name="branch_id" id="branchSelect" value="{{ auth()->user()->branch_id }}">
            @elseif(session('selected_branch_id'))
                <input type="hidden" name="branch_id" id="branchSelect" value="{{ session('selected_branch_id') }}">
            @endif

            <div class="card mb-4">
                <div class="card-header"><i data-lucide="info" class="me-1"></i> Menu Details</div>
                <div class="card-body">
                    <div class="alert alert-primary text-white">
                        Set the branch, choose the menu category, then build the recipe from inventory ingredients. Stock availability is checked during ordering, so recipe setup stays available even when an ingredient is currently low or out of stock.
                    </div>

                    <div class="row">
                        @if(auth()->user()->isAdmin() && !session('selected_branch_id'))
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                            <select class="form-select @error('branch_id') is-invalid @enderror" name="branch_id" id="branchSelect" required>
                                <option value="">-- Select Branch --</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id', $branchId) == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @endif

                        <div class="{{ auth()->user()->isAdmin() && !session('selected_branch_id') ? 'col-md-6' : 'col-md-12' }} mb-3">
                            <label class="form-label fw-bold">Menu Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="e.g. Sweet & Sour Chicken"
                                required
                            >
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea
                                name="menu_description"
                                rows="3"
                                class="form-control @error('menu_description') is-invalid @enderror"
                                placeholder="Briefly describe this menu item (optional)..."
                            >{{ old('menu_description') }}</textarea>
                            @error('menu_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Selling Price (PHP) <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                class="form-control @error('selling_price') is-invalid @enderror"
                                name="selling_price"
                                value="{{ old('selling_price') }}"
                                step="0.01"
                                min="0"
                                required
                            >
                            @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Menu Category <span class="text-danger">*</span></label>
                            <select class="form-select @error('menu_category_id') is-invalid @enderror" name="menu_category_id" id="categorySelect" required>
                                <option value="">Select Menu Category</option>
                                @foreach($menuCategories as $cat)
                                <option value="{{ $cat->id }}" data-branch-id="{{ $cat->branch_id }}" {{ (string) old('menu_category_id') === (string) $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('menu_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Menu Image</label>
                            <input
                                type="file"
                                class="form-control @error('image') is-invalid @enderror"
                                name="image"
                                id="imageInput"
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                            >
                            <div class="form-text">Upload a photo of the menu item (JPEG, PNG, WebP, max 2MB).</div>
                            <div id="imagePreview" class="mt-2" style="display:none;">
                                <img src="" alt="Preview" style="max-height:120px;border-radius:8px;border:1px solid #ddd;">
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
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="no_ingredients" value="1" id="noIngredientsToggle" {{ old('no_ingredients') ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="noIngredientsToggle">No Ingredients</label>
                        <div class="form-text">Enable this if this menu item does not consume inventory ingredients (e.g. a beverage add-on or a flat fee).</div>
                    </div>

                    <div id="ingredientsSection">
                    <div id="branchWarning" class="alert alert-warning {{ auth()->user()->isAdmin() && !old('branch_id', $branchId) ? '' : 'd-none' }}">
                        <i data-lucide="alert-triangle" class="me-1"></i> Please select a branch first before choosing ingredients.
                    </div>
                    <p class="text-muted small mb-3">Define how much inventory stock is consumed per one unit of this menu item. Ingredients are filtered by branch, but zero-stock items can still be included in the recipe.</p>

                    <div id="ingredientsContainer">
                        @forelse(old('ingredients', []) as $i => $ing)
                        <div class="row g-2 mb-2 ingredient-row">
                            <div class="col-md-7">
                                <select class="form-select ingredient-select" name="ingredients[{{ $i }}][item_id]" required>
                                    <option value="">-- Select Inventory Item --</option>
                                    @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-branch-id="{{ $item->branch_id }}" {{ ($ing['item_id'] ?? null) == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }} ({{ $item->unit }}) - Stock: {{ $item->quantity }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="form-text ingredient-stock-label mt-1">Available stock: --</div>
                            </div>
                            <div class="col-md-3">
                                <input
                                    type="number"
                                    class="form-control"
                                    name="ingredients[{{ $i }}][quantity_required]"
                                    value="{{ $ing['quantity_required'] ?? '' }}"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="Qty required"
                                    required
                                >
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
                                        {{ $item->name }} ({{ $item->unit }}) - Stock: {{ $item->quantity }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="form-text ingredient-stock-label mt-1">Available stock: --</div>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control" name="ingredients[0][quantity_required]" step="0.01" min="0.01" placeholder="Qty required" required>
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
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="{{ route('menus.index') }}" class="btn btn-secondary text-white">Cancel</a>
                <button type="submit" class="btn btn-primary text-white" id="menuCreateSubmitBtn">
                    <i data-lucide="save" class="me-1"></i> Save Menu Item
                </button>
            </div>
        </form>
    </div>
@endsection

@php
    $menuItemOptions = $items->map(fn($item) => [
        'id' => $item->id,
        'label' => $item->name . ' (' . $item->unit . ') - Stock: ' . $item->quantity,
        'branch_id' => $item->branch_id,
        'stock' => (float) $item->quantity,
        'unit' => (string) $item->unit,
    ])->values();
@endphp

@push('scripts')
<script>
const itemOptions = @json($menuItemOptions);

let rowIndex = document.querySelectorAll('.ingredient-row').length;

const menuCreateForm = document.getElementById('menuCreateForm');
const menuCreateSubmitBtn = document.getElementById('menuCreateSubmitBtn');
const menuFormAlert = document.getElementById('menuFormAlert');
const ingredientsContainer = document.getElementById('ingredientsContainer');
const addIngredientBtn = document.getElementById('addIngredientBtn');
const noIngredientsToggle = document.getElementById('noIngredientsToggle');
const ingredientsSection = document.getElementById('ingredientsSection');

function isNoIngredients() {
    return !!noIngredientsToggle?.checked;
}

function applyNoIngredientsToggle() {
    const enabled = isNoIngredients();
    ingredientsSection?.classList.toggle('d-none', enabled);
    if (addIngredientBtn) addIngredientBtn.disabled = enabled;
    document.querySelectorAll('.ingredient-select').forEach((select) => { select.required = !enabled; });
    document.querySelectorAll('.ingredient-row input[name*="[quantity_required]"]').forEach((input) => { input.required = !enabled; });
}

noIngredientsToggle?.addEventListener('change', function () {
    applyNoIngredientsToggle();
    showMenuFormAlert([]);
});

function getBranchId() {
    const branchSelect = document.getElementById('branchSelect');
    return branchSelect ? branchSelect.value : '{{ $branchId ?? '' }}';
}

function showMenuFormAlert(messages = []) {
    if (!menuFormAlert) return;

    if (!messages.length) {
        menuFormAlert.classList.add('d-none');
        menuFormAlert.innerHTML = '';
        return;
    }

    menuFormAlert.classList.remove('d-none');
    menuFormAlert.innerHTML = `<strong>Please fix the following:</strong><ul class="mb-0 mt-2">${messages.map((message) => `<li>${escapeHtml(message)}</li>`).join('')}</ul>`;
}

function buildOptions(selectedId = '') {
    const branchId = getBranchId();
    let options = '<option value="">-- Select Inventory Item --</option>';

    itemOptions.forEach((item) => {
        if (branchId && Number(item.branch_id) !== Number(branchId)) return;
        const selected = Number(item.id) === Number(selectedId) ? ' selected' : '';
        options += `<option value="${Number(item.id)}"${selected} data-branch-id="${Number(item.branch_id)}">${escapeHtml(item.label)}</option>`;
    });

    return options;
}

function updateIngredientStockLabel(select) {
    if (!select) return;

    const row = select.closest('.ingredient-row');
    const label = row ? row.querySelector('.ingredient-stock-label') : null;
    if (!label) return;

    const selectedOption = select.selectedOptions[0];
    if (!selectedOption || !select.value) {
        label.textContent = 'Available stock: --';
        return;
    }

    const item = itemOptions.find((entry) => Number(entry.id) === Number(select.value));
    if (!item) {
        label.textContent = 'Available stock: --';
        return;
    }

    label.textContent = `Available stock: ${Number(item.stock).toFixed(2)} ${item.unit || ''}`.trim();
}

function refreshAllIngredientStockLabels() {
    document.querySelectorAll('.ingredient-select').forEach((select) => updateIngredientStockLabel(select));
}

function validateMenuCreateForm() {
    const messages = [];
    const branchId = getBranchId();
    const categorySelect = document.getElementById('categorySelect');
    const ingredientSelects = Array.from(document.querySelectorAll('.ingredient-select'));
    const selectedIngredients = [];

    if (!branchId) {
        messages.push('Please select a branch.');
    }

    if (!categorySelect || !categorySelect.value) {
        messages.push('Please select a menu category.');
    }

    if (isNoIngredients()) {
        showMenuFormAlert(messages);
        return messages.length === 0;
    }

    if (!ingredientSelects.length) {
        messages.push('Add at least one ingredient to save this menu item.');
    }

    ingredientSelects.forEach((select, index) => {
        const rowLabel = `Ingredient row ${index + 1}`;
        const quantityInput = select.closest('.ingredient-row')?.querySelector('input[name*="[quantity_required]"]');

        if (!select.value) {
            messages.push(`${rowLabel}: choose an inventory item.`);
            return;
        }

        if (selectedIngredients.includes(select.value)) {
            messages.push(`${rowLabel}: duplicate ingredients are not allowed.`);
        }

        selectedIngredients.push(select.value);

        if (!quantityInput || Number(quantityInput.value) <= 0) {
            messages.push(`${rowLabel}: enter a required quantity greater than zero.`);
        }
    });

    showMenuFormAlert(messages);

    return messages.length === 0;
}

addIngredientBtn?.addEventListener('click', function () {
    const branchId = getBranchId();
    if (!branchId) {
        showMenuFormAlert(['Please select a branch first before adding ingredients.']);
        return;
    }

    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 ingredient-row';
    row.innerHTML = `
        <div class="col-md-7">
            <select class="form-select ingredient-select" name="ingredients[${rowIndex}][item_id]" required>
                ${buildOptions()}
            </select>
            <div class="form-text ingredient-stock-label mt-1">Available stock: --</div>
        </div>
        <div class="col-md-3">
            <input type="number" class="form-control" name="ingredients[${rowIndex}][quantity_required]" step="0.01" min="0.01" placeholder="Qty required" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger w-100 btn-remove-ingredient">
                <i data-lucide="trash-2"></i> Remove
            </button>
        </div>`;

    ingredientsContainer?.appendChild(row);
    rowIndex++;
    showMenuFormAlert([]);
    refreshAllIngredientStockLabels();

    if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
});

ingredientsContainer?.addEventListener('click', function (event) {
    const btn = event.target.closest('.btn-remove-ingredient');
    if (!btn) {
        const select = event.target.closest('.ingredient-select');
        if (select) {
            updateIngredientStockLabel(select);
        }
        return;
    }

    const rows = document.querySelectorAll('.ingredient-row');
    if (rows.length <= 1) {
        showMenuFormAlert(['A menu item must have at least one ingredient.']);
        return;
    }

    btn.closest('.ingredient-row')?.remove();
    showMenuFormAlert([]);
});

ingredientsContainer?.addEventListener('change', function (event) {
    const select = event.target.closest('.ingredient-select');
    if (select) {
        updateIngredientStockLabel(select);
    }
});

(function () {
    const branchSelect = document.getElementById('branchSelect');
    const categorySelect = document.getElementById('categorySelect');
    const branchWarning = document.getElementById('branchWarning');

    function filterByBranch() {
        const branchId = branchSelect ? branchSelect.value : '{{ $branchId ?? '' }}';

        if (branchSelect && branchWarning) {
            branchWarning.classList.toggle('d-none', !!branchId);
        }

        if (categorySelect) {
            Array.from(categorySelect.options).forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                option.hidden = !!branchId && option.dataset.branchId !== branchId;
            });

            if (categorySelect.selectedOptions[0]?.hidden) {
                categorySelect.value = '';
            }
        }

        document.querySelectorAll('.ingredient-select').forEach((select) => {
            Array.from(select.options).forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                option.hidden = !!branchId && option.dataset.branchId !== branchId;
            });

            if (select.selectedOptions[0]?.hidden) {
                select.value = '';
            }

            updateIngredientStockLabel(select);
        });
    }

    if (branchSelect) {
        branchSelect.addEventListener('change', filterByBranch);
        filterByBranch();
    }
})();

refreshAllIngredientStockLabels();
applyNoIngredientsToggle();

menuCreateForm?.addEventListener('submit', function (event) {
    if (!validateMenuCreateForm()) {
        event.preventDefault();
        return;
    }

    if (menuCreateSubmitBtn?.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }

    if (menuCreateSubmitBtn) {
        menuCreateSubmitBtn.dataset.submitting = 'true';
        menuCreateSubmitBtn.disabled = true;
        menuCreateSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
    }
});

document.getElementById('imageInput')?.addEventListener('change', function (event) {
    const file = event.target.files[0];
    const preview = document.getElementById('imagePreview');
    const img = preview?.querySelector('img');

    if (!preview || !img) return;

    if (file) {
        const reader = new FileReader();
        reader.onload = function (loadEvent) {
            img.src = loadEvent.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        img.src = '';
        preview.style.display = 'none';
    }
});

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
</script>
@endpush
