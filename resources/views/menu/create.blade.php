@extends('layouts.app')

@section('page_title', 'Add Menu Item - Dianne\'s Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="plus-circle"></i></div>
                            Add Menu Item
                        </h1>
                        <div class="page-header-subtitle">Create a new menu item with ingredient recipe</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a class="btn btn-light text-primary" href="{{ route('menus.index') }}">
                            <i data-feather="arrow-left" class="me-1"></i> Back to Menu
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <form action="{{ route('menus.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if(!auth()->user()->isAdmin())
                <input type="hidden" name="branch_id" id="branchSelect" value="{{ auth()->user()->branch_id }}">
            @elseif(session('selected_branch_id'))
                {{-- Admin with a branch already selected in the top nav --}}
                <input type="hidden" name="branch_id" id="branchSelect" value="{{ session('selected_branch_id') }}">
            @endif

            <div class="card mb-4">
                <div class="card-header"><i data-feather="info" class="me-1"></i> Menu Details</div>
                <div class="card-body">
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
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name') }}" placeholder="e.g. Sweet & Sour Chicken" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="menu_description" rows="3"
                                      class="form-control @error('menu_description') is-invalid @enderror"
                                      placeholder="Briefly describe this menu item (optional)…">{{ old('menu_description') }}</textarea>
                            @error('menu_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Selling Price (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('selling_price') is-invalid @enderror"
                                   name="selling_price" value="{{ old('selling_price') }}" step="0.01" min="0" required>
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
                            <input type="file" class="form-control @error('image') is-invalid @enderror"
                                   name="image" id="imageInput" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                            <div class="form-text">Upload a photo of the menu item (JPEG, PNG, WebP, max 2MB).</div>
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
                    <div><i data-feather="list" class="me-1"></i> Ingredients (Recipe / BOM)</div>
                    <button type="button" id="addIngredientBtn" class="btn btn-sm btn-outline-primary">
                        <i data-feather="plus" class="me-1"></i> Add Ingredient
                    </button>
                </div>
                <div class="card-body">
                    <div id="branchWarning" class="alert alert-warning {{ auth()->user()->isAdmin() && !old('branch_id', $branchId) ? '' : 'd-none' }}">
                        <i data-feather="alert-triangle" class="me-1"></i> Please select a branch first before choosing ingredients.
                    </div>
                    <p class="text-muted small mb-3">Define how much inventory stock is consumed per one unit of this menu item. Only items with stock are shown.</p>

                    <div id="ingredientsContainer">
                        @forelse(old('ingredients', []) as $i => $ing)
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
                                       value="{{ $ing['quantity_required'] ?? '' }}" step="0.01" min="0.01"
                                       placeholder="Qty required" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger w-100 btn-remove-ingredient">
                                    <i data-feather="trash-2"></i> Remove
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
                                    <i data-feather="trash-2"></i> Remove
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
                    <i data-feather="save" class="me-1"></i> Save Menu Item
                </button>
            </div>
        </form>
    </div>
</main>
@endsection

@push('scripts')
<script>
const itemOptions = `{!! json_encode($items->map(fn($item) => [
    'id'    => $item->id,
    'label' => $item->name . ' (' . $item->unit . ') — Stock: ' . $item->quantity,
    'branch_id' => $item->branch_id,
])->values()) !!}`;

let rowIndex = document.querySelectorAll('.ingredient-row').length;

function getBranchId() {
    const branchSelect = document.getElementById('branchSelect');
    return branchSelect ? branchSelect.value : '{{ $branchId ?? '' }}';
}

function buildOptions(selectedId = '') {
    const items = JSON.parse(itemOptions);
    const branchId = getBranchId();
    let opts = '<option value="">-- Select Inventory Item --</option>';
    items.forEach(item => {
        if (branchId && item.branch_id != branchId) return;
        const sel = item.id == selectedId ? ' selected' : '';
        opts += `<option value="${item.id}"${sel} data-branch-id="${item.branch_id}">${item.label}</option>`;
    });
    return opts;
}

document.getElementById('addIngredientBtn').addEventListener('click', function () {
    const branchId = getBranchId();
    if (!branchId) {
        alert('Please select a branch first before adding ingredients.');
        return;
    }
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
                <i data-feather="trash-2"></i> Remove
            </button>
        </div>`;
    document.getElementById('ingredientsContainer').appendChild(row);
    rowIndex++;
    if (typeof feather !== 'undefined') feather.replace();
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

// Branch-based filtering
(function () {
    const branchSelect = document.getElementById('branchSelect');
    const categorySelect = document.getElementById('categorySelect');
    const branchWarning = document.getElementById('branchWarning');

    function filterByBranch() {
        const branchId = branchSelect ? branchSelect.value : '{{ $branchId ?? '' }}';

        // Show/hide warning
        if (branchSelect && branchWarning) {
            branchWarning.classList.toggle('d-none', !!branchId);
        }

        // Filter categories
        if (categorySelect) {
            Array.from(categorySelect.options).forEach((option, index) => {
                if (index === 0) { option.hidden = false; return; }
                option.hidden = branchId && option.dataset.branchId !== branchId;
            });
            if (categorySelect.selectedOptions[0]?.hidden) {
                categorySelect.value = '';
            }
        }

        // Filter all ingredient selects
        document.querySelectorAll('.ingredient-select').forEach(select => {
            Array.from(select.options).forEach((option, index) => {
                if (index === 0) { option.hidden = false; return; }
                option.hidden = branchId && option.dataset.branchId !== branchId;
            });
            if (select.selectedOptions[0]?.hidden) {
                select.value = '';
            }
        });
    }

    if (branchSelect) {
        branchSelect.addEventListener('change', filterByBranch);
        filterByBranch();
    }
})();

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