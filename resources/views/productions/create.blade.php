@extends('layouts.app')

@section('page_title', 'Start Production Order - Dianne Seafood House')

@section('content')
<main>
    <x-page-header title="Start Production Order" subtitle="Select inventory items to begin a new production batch" icon="tool">
        <a href="{{ route('productions.index') }}" class="btn btn-light text-primary">
            <i data-feather="arrow-left" class="me-1"></i> All Productions
        </a>
    </x-page-header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Production Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('productions.store') }}">
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
                        <h6 class="mb-0 fw-semibold">Raw Inputs <span class="text-muted fw-normal small">(inventory items to consume)</span></h6>
                        <button type="button" class="btn btn-sm btn-light" id="add-production-input">
                            <i data-feather="plus"></i> Add Input
                        </button>
                    </div>

                    @error('inputs') <div class="alert alert-danger py-2 mt-3 mb-0">{{ $message }}</div> @enderror

                    <div class="table-responsive mt-3">
                        <table class="table table-bordered align-middle" id="production-inputs-table">
                            <thead class="table-light">
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
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end gap-2">
                        <a href="{{ route('productions.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="play" class="me-1"></i> Start Production
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.querySelector('#production-inputs-table tbody');
    const addButton = document.getElementById('add-production-input');
    const itemOptions = @json($itemOptions);

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
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>`;
        tableBody.appendChild(row);
        reindexRows();
    });

    tableBody.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-row');
        if (removeButton && tableBody.querySelectorAll('tr').length > 1) {
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
        row.querySelector('.item-picker-results').classList.add('d-none');
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
            document.querySelectorAll('.item-picker-results').forEach(results => results.classList.add('d-none'));
        }
    });

    function renderResults(input) {
        const query = input.value.trim().toLowerCase();
        const results = input.closest('.item-picker').querySelector('.item-picker-results');
        const matches = itemOptions.filter(item => item.label.toLowerCase().includes(query)).slice(0, 20);
        results.innerHTML = matches.length
            ? matches.map(item => `<button type="button" class="item-picker-option" data-item-id="${item.value}"><span>${escapeHtml(item.label)}</span><strong>${escapeHtml(item.unit)}</strong></button>`).join('')
            : '<div class="p-3 text-muted small">No matching items.</div>';
        results.classList.remove('d-none');
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
.item-picker-results { position: absolute; z-index: 1050; top: calc(100% + .25rem); left: 0; right: 0; max-height: 280px; overflow-y: auto; border: 1px solid #d4dae3; border-radius: .5rem; background: #fff; box-shadow: 0 .5rem 1rem rgba(33, 40, 50, .12); }
.item-picker-option { display: flex; width: 100%; justify-content: space-between; gap: 1rem; padding: .65rem .75rem; border: 0; border-bottom: 1px solid #edf0f4; background: #fff; text-align: left; font-size: .85rem; }
.item-picker-option:hover { background: #f2f6fc; color: #0061f2; }
.unit-display:disabled { background: #f2f6fc; color: #475569; font-weight: 600; }
</style>
@endpush
