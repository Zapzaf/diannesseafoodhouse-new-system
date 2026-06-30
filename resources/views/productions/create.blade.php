@extends('layouts.app')

@section('page_title', 'Start Production Order - Dianne Seafood House')

@section('content')
    <x-page-header title="Start Production Order" subtitle="Select inventory items to begin a new production batch" icon="wrench">
        <a href="{{ route('productions.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> All Productions
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-4 shadow-sm">
            <div class="card-body p-0">
                <form method="POST" action="{{ route('productions.store') }}">
                    <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                        <i data-lucide="settings" style="width: 20px; height: 20px;"></i>
                        <span>Production Details</span>
                    </h5>
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select branch</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between align-items-center border-top pt-4">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2 text-primary">
                            <i data-lucide="database" style="width: 20px; height: 20px;"></i>
                            <span>Raw Inputs <span class="text-muted fw-normal small">(inventory items to consume)</span></span>
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" id="add-production-input">
                            <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Add Input
                        </button>
                    </div>

                    @error('inputs') <div class="alert alert-danger py-2 mt-3 mb-0">{{ $message }}</div> @enderror

                    <div class="table-responsive mt-3">
                        <table class="table table-hover align-middle" id="production-inputs-table">
                            <thead>
                                <tr>
                                    <th>Inventory Item</th>
                                    <th>Quantity Used</th>
                                    <th>Unit</th>
                                    <th class="table-actions-head">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="item-picker">
                                            <input type="search" class="form-control item-picker-search" placeholder="Search item by name or ID" autocomplete="off" required>
                                            <input type="hidden" data-field="item_id" name="inputs[0][item_id]">
                                            <div class="item-picker-results d-none"></div>
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="inputs[0][quantity_used]" class="form-control" required></td>
                                    <td>
                                        <input type="text" class="form-control unit-display" value="Select an item" disabled>
                                        <input type="hidden" data-field="unit" name="inputs[0][unit]">
                                    </td>
                                    <td class="table-actions-cell text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-3">
                        <a href="{{ route('productions.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                            <i data-lucide="play" style="width: 18px; height: 18px;"></i>
                            <span>Start Production</span>
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
    const tableBody = document.querySelector('#production-inputs-table tbody');
    const addButton = document.getElementById('add-production-input');
    const itemOptions = @json($itemOptions);
    let activePickerInput = null;

    function reindexRows() {
        Array.from(tableBody.querySelectorAll('tr')).forEach(function (row, index) {
            row.querySelector('[data-field="item_id"]').name = `inputs[${index}][item_id]`;
            row.querySelector('input[name$="[quantity_used]"]').name = `inputs[${index}][quantity_used]`;
            row.querySelector('[data-field="unit"]').name = `inputs[${index}][unit]`;
        });
    }

    addButton.addEventListener('click', function () {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><div class="item-picker"><input type="search" class="form-control item-picker-search" placeholder="Search item by name or ID" autocomplete="off" required><input type="hidden" data-field="item_id"><div class="item-picker-results d-none"></div></div></td>
            <td><input type="number" step="0.01" min="0.01" name="quantity_used" class="form-control" required></td>
            <td><input type="text" class="form-control unit-display" value="Select an item" disabled><input type="hidden" data-field="unit"></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button></td>`;
        tableBody.appendChild(row);
        reindexRows();
        if (typeof window.refreshLucideIcons === 'function') {
            window.refreshLucideIcons();
        }
    });

    tableBody.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-row');
        if (removeButton && tableBody.querySelectorAll('tr').length > 1) {
            hideAllPickerResults();
            removeButton.closest('tr').remove();
            reindexRows();
            return;
        }

        const option = event.target.closest('.item-picker-option');
        if (!option) return;

        const row = option.closest('tr');
        const item = itemOptions.find(candidate => String(candidate.value) === option.dataset.itemId);
        row.querySelector('.item-picker-search').value = item.label;
        row.querySelector('[data-field="item_id"]').value = item.value;
        row.querySelector('.unit-display').value = item.unit;
        row.querySelector('[data-field="unit"]').value = item.unit;
        hideAllPickerResults();
    });

    tableBody.addEventListener('input', function (event) {
        if (!event.target.classList.contains('item-picker-search')) return;
        event.target.closest('tr').querySelector('[data-field="item_id"]').value = '';
        event.target.closest('tr').querySelector('[data-field="unit"]').value = '';
        event.target.closest('tr').querySelector('.unit-display').value = 'Select an item';
        renderResults(event.target);
    });

    tableBody.addEventListener('focusin', function (event) {
        if (event.target.classList.contains('item-picker-search')) renderResults(event.target);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.item-picker')) {
            hideAllPickerResults();
        }
    });

    window.addEventListener('resize', repositionActivePicker);
    window.addEventListener('scroll', repositionActivePicker, true);

    function renderResults(input) {
        const query = input.value.trim().toLowerCase();
        const results = input.closest('.item-picker').querySelector('.item-picker-results');
        const matches = itemOptions.filter(item => item.label.toLowerCase().includes(query)).slice(0, 20);
        results.innerHTML = matches.length
            ? matches.map(item => `<button type="button" class="item-picker-option" data-item-id="${item.value}"><span>${escapeHtml(item.label)}</span><strong>${escapeHtml(item.unit)}</strong></button>`).join('')
            : '<div class="p-3 text-muted small">No matching items.</div>';
        activePickerInput = input;
        positionPickerResults(input);
        results.classList.remove('d-none');
    }

    function positionPickerResults(input) {
        const results = input.closest('.item-picker')?.querySelector('.item-picker-results');
        if (!results) {
            return;
        }

        const rect = input.getBoundingClientRect();
        const gap = 6;
        const viewportPadding = 12;
        const preferredHeight = 280;
        const availableBelow = window.innerHeight - rect.bottom - gap - viewportPadding;
        const availableAbove = rect.top - gap - viewportPadding;
        const openAbove = availableBelow < 160 && availableAbove > availableBelow;
        const maxHeight = Math.max(140, Math.min(preferredHeight, openAbove ? availableAbove : availableBelow));
        const width = Math.min(rect.width, window.innerWidth - (viewportPadding * 2));
        const left = Math.min(Math.max(rect.left, viewportPadding), window.innerWidth - width - viewportPadding);

        results.style.width = `${width}px`;
        results.style.left = `${left}px`;
        results.style.maxHeight = `${maxHeight}px`;

        if (openAbove) {
            results.style.top = 'auto';
            results.style.bottom = `${window.innerHeight - rect.top + gap}px`;
        } else {
            results.style.top = `${rect.bottom + gap}px`;
            results.style.bottom = 'auto';
        }
    }

    function repositionActivePicker() {
        if (!activePickerInput || !document.body.contains(activePickerInput)) {
            activePickerInput = null;
            return;
        }

        const results = activePickerInput.closest('.item-picker')?.querySelector('.item-picker-results');
        if (!results || results.classList.contains('d-none')) {
            return;
        }

        positionPickerResults(activePickerInput);
    }

    function hideAllPickerResults() {
        document.querySelectorAll('.item-picker-results').forEach(results => results.classList.add('d-none'));
        activePickerInput = null;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    reindexRows();
});
</script>
@endpush

@push('styles')
<style>
.item-picker { position: relative; min-width: 320px; }
.item-picker-results { position: fixed; z-index: 2055; max-height: 280px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: .5rem; background: var(--bg-card); box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15); }
.item-picker-option { display: flex; width: 100%; justify-content: space-between; gap: 1rem; padding: .65rem .75rem; border: 0; border-bottom: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); text-align: left; font-size: .85rem; }
.item-picker-option:hover { background: rgba(var(--primary-color-rgb), 0.08); color: var(--primary-color); }
.item-picker-option span { flex: 1 1 auto; min-width: 0; max-width: 100%; overflow: hidden; text-overflow: ellipsis; overflow-wrap: normal; word-break: normal; }
.item-picker-option strong { display: inline-flex; flex: 0 0 auto; min-width: max-content; white-space: nowrap; overflow-wrap: normal; word-break: keep-all; text-align: right; }
.unit-display:disabled { background: var(--bg-body) !important; color: var(--text-muted) !important; font-weight: 600; border-color: var(--border-color) !important; }
</style>
@endpush
