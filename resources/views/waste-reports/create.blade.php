@extends('layouts.app')

@section('page_title', 'Create Waste Report - Dianne Seafood House')

@section('content')
@php
    $lockedBranchId = $selectedBranchId;
    $selectedBranch = old('branch_id', $selectedBranchId);
    $branchName = $lockedBranchId ? optional($branches->firstWhere('id', (int) $lockedBranchId))->name : null;
    $oldRows = old('items', [[
        'item_id' => '',
        'quantity' => '',
        'reason' => '',
        'notes' => '',
    ]]);
    $itemOptionsById = collect($itemOptions)->keyBy('id');
@endphp

    <x-page-header title="Create Waste Report" subtitle="Deduct unusable inventory due to spoilage, expiration, odors, or damage" icon="alert-triangle">
        <a href="{{ route('waste-reports.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form method="POST" action="{{ route('waste-reports.store') }}">
            @csrf

            <div class="card p-4 shadow-sm mb-4">
                <div class="card-body p-0">
                    <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                        <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
                        <span>Report Details</span>
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            @if($lockedBranchId)
                                <input type="hidden" name="branch_id" id="branch-id" value="{{ $lockedBranchId }}">
                                <input type="text" class="form-control" value="{{ $branchName ?? 'Selected branch' }}" disabled>
                            @else
                                <select name="branch_id" id="branch-id" class="form-select @error('branch_id') is-invalid @enderror" required>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((int) $selectedBranch === (int) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('branch_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Report Date <span class="text-danger">*</span></label>
                            <input type="date" name="report_date" class="form-control @error('report_date') is-invalid @enderror" value="{{ old('report_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            @error('report_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" class="form-control @error('remarks') is-invalid @enderror" value="{{ old('remarks') }}" placeholder="Optional summary">
                            @error('remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card p-4 shadow-sm">
                <div class="card-body p-0">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2 text-primary">
                            <i data-lucide="trash-2" style="width: 20px; height: 20px;"></i>
                            <span>Wasted Items</span>
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" id="add-waste-row">
                            <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Add Item
                        </button>
                    </div>
                    @error('items')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="waste-items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Available</th>
                                    <th>Qty Wasted</th>
                                    <th>Unit</th>
                                    <th>Reason</th>
                                    <th>Notes</th>
                                    <th class="table-actions-head">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($oldRows as $index => $row)
                                    @php
                                        $selectedItem = ! empty($row['item_id']) ? $itemOptionsById->get((int) $row['item_id']) : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="item-picker">
                                                <input type="search" class="form-control item-picker-search @error("items.$index.item_id") is-invalid @enderror" placeholder="Search inventory item" value="{{ $selectedItem['label'] ?? '' }}" autocomplete="off" required>
                                                <input type="hidden" name="items[{{ $index }}][item_id]" data-field="item_id" value="{{ $row['item_id'] ?? '' }}">
                                                <div class="item-picker-results d-none"></div>
                                            </div>
                                            @error("items.$index.item_id")
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td><input type="text" class="form-control available-display" value="{{ $selectedItem ? number_format((float) $selectedItem['quantity'], 2) : 'Select item' }}" disabled></td>
                                        <td>
                                            <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" class="form-control @error("items.$index.quantity") is-invalid @enderror" data-field="quantity" value="{{ $row['quantity'] ?? '' }}" required>
                                            @error("items.$index.quantity")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td><input type="text" class="form-control unit-display" value="{{ $selectedItem['unit'] ?? 'Select item' }}" disabled></td>
                                        <td>
                                            <select name="items[{{ $index }}][reason]" class="form-select @error("items.$index.reason") is-invalid @enderror" data-field="reason" required>
                                                <option value="">Select Reason</option>
                                                @foreach($reasons as $reason)
                                                    <option value="{{ $reason }}" @selected(($row['reason'] ?? '') === $reason)>{{ $reason }}</option>
                                                @endforeach
                                            </select>
                                            @error("items.$index.reason")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <input type="text" name="items[{{ $index }}][notes]" class="form-control @error("items.$index.notes") is-invalid @enderror" data-field="notes" value="{{ $row['notes'] ?? '' }}" placeholder="Optional">
                                            @error("items.$index.notes")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="table-actions-cell text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-3">
                        <a href="{{ route('waste-reports.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-danger px-4 d-flex align-items-center gap-2">
                            <i data-lucide="alert-triangle" style="width: 18px; height: 18px;"></i>
                            <span>Save Waste Report</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemOptions = @json($itemOptions);
    const reasons = @json($reasons);
    const tableBody = document.querySelector('#waste-items-table tbody');
    const addButton = document.getElementById('add-waste-row');
    const branchField = document.getElementById('branch-id');
    let activePickerInput = null;

    function branchId() {
        return branchField?.value || '';
    }

    function reindexRows() {
        Array.from(tableBody.querySelectorAll('tr')).forEach((row, index) => {
            ['item_id', 'quantity', 'reason', 'notes'].forEach((key) => {
                const field = row.querySelector(`[data-field="${key}"]`);
                if (field) {
                    field.name = `items[${index}][${key}]`;
                }
            });
        });
    }

    function reasonOptions() {
        return reasons.map((reason) => `<option value="${escapeHtml(reason)}">${escapeHtml(reason)}</option>`).join('');
    }

    function rowTemplate() {
        return `
            <td><div class="item-picker"><input type="search" class="form-control item-picker-search" placeholder="Search inventory item" autocomplete="off" required><input type="hidden" data-field="item_id"><div class="item-picker-results d-none"></div></div></td>
            <td><input type="text" class="form-control available-display" value="Select item" disabled></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-field="quantity" required></td>
            <td><input type="text" class="form-control unit-display" value="Select item" disabled></td>
            <td><select class="form-select" data-field="reason" required><option value="">Select Reason</option>${reasonOptions()}</select></td>
            <td><input type="text" class="form-control" data-field="notes" placeholder="Optional"></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button></td>
        `;
    }

    addButton?.addEventListener('click', function () {
        const row = document.createElement('tr');
        row.innerHTML = rowTemplate();
        tableBody.appendChild(row);
        reindexRows();
        if (typeof window.refreshLucideIcons === 'function') {
            window.refreshLucideIcons();
        }
    });

    tableBody?.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-row');
        if (!removeButton) {
            return;
        }

        if (tableBody.querySelectorAll('tr').length === 1) {
            return;
        }

        hideAllPickerResults();
        removeButton.closest('tr').remove();
        reindexRows();
    });

    branchField?.addEventListener('change', function () {
        clearItemSelections();
    });

    document.addEventListener('input', function (event) {
        if (!event.target.classList.contains('item-picker-search')) {
            return;
        }

        const row = event.target.closest('tr');
        row.querySelector('[data-field="item_id"]').value = '';
        row.querySelector('.available-display').value = 'Select item';
        row.querySelector('.unit-display').value = 'Select item';
        renderResults(event.target);
    });

    document.addEventListener('focusin', function (event) {
        if (event.target.classList.contains('item-picker-search')) {
            renderResults(event.target);
        }
    });

    document.addEventListener('click', function (event) {
        const option = event.target.closest('.item-picker-option');
        if (option) {
            const item = itemOptions.find((candidate) => String(candidate.id) === option.dataset.itemId);
            const row = option.closest('tr');
            row.querySelector('.item-picker-search').value = item.label;
            row.querySelector('[data-field="item_id"]').value = item.id;
            row.querySelector('.available-display').value = Number(item.quantity).toFixed(2);
            row.querySelector('.unit-display').value = item.unit || '';
            hideAllPickerResults();
            return;
        }

        if (!event.target.closest('.item-picker')) {
            hideAllPickerResults();
        }
    });

    window.addEventListener('resize', repositionActivePicker);
    window.addEventListener('scroll', repositionActivePicker, true);

    function renderResults(input) {
        const results = input.closest('.item-picker').querySelector('.item-picker-results');

        if (!branchId()) {
            results.innerHTML = '<div class="p-3 text-muted small">Select a branch first.</div>';
            results.classList.remove('d-none');
            return;
        }

        const query = input.value.trim().toLowerCase();
        const matches = itemOptions
            .filter((item) => String(item.branch_id) === String(branchId()))
            .filter((item) => item.label.toLowerCase().includes(query))
            .slice(0, 20);

        results.innerHTML = matches.length
            ? matches.map((item) => `<button type="button" class="item-picker-option" data-item-id="${item.id}"><span>${escapeHtml(item.label)}</span><strong>${escapeHtml(item.unit || '')}</strong></button>`).join('')
            : '<div class="p-3 text-muted small">No matching items in this branch.</div>';
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
        document.querySelectorAll('.item-picker-results').forEach((results) => results.classList.add('d-none'));
        activePickerInput = null;
    }

    function clearItemSelections() {
        hideAllPickerResults();
        tableBody.querySelectorAll('tr').forEach((row) => {
            row.querySelector('.item-picker-search').value = '';
            row.querySelector('[data-field="item_id"]').value = '';
            row.querySelector('.available-display').value = 'Select item';
            row.querySelector('.unit-display').value = 'Select item';
        });
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    reindexRows();
});
</script>
@endpush

@push('styles')
<style>
.item-picker { position: relative; min-width: 320px; }
.item-picker-results {
    position: fixed;
    z-index: 2055;
    max-height: 280px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: .5rem;
    background: var(--bg-card);
    box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
}
.item-picker-option {
    display: flex;
    width: 100%;
    justify-content: space-between;
    gap: 1rem;
    padding: .7rem .8rem;
    border: 0;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-main);
    text-align: left;
    font-size: .85rem;
}
.item-picker-option:hover { background: rgba(var(--primary-color-rgb), 0.08); color: var(--primary-color); }
.item-picker-option span {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    overflow-wrap: normal;
    word-break: normal;
}
.item-picker-option strong {
    display: inline-flex;
    flex: 0 0 auto;
    min-width: max-content;
    white-space: nowrap;
    overflow-wrap: normal;
    word-break: keep-all;
    text-align: right;
}
.available-display:disabled,
.unit-display:disabled { background: var(--bg-body) !important; color: var(--text-muted) !important; font-weight: 600; border-color: var(--border-color) !important; }
</style>
@endpush
