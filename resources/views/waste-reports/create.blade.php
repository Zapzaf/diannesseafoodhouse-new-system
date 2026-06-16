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

<main>
    <x-page-header title="Create Waste Report" subtitle="Deduct unusable inventory due to spoilage, expiration, odors, or damage" icon="alert-triangle">
        <a href="{{ route('waste-reports.index') }}" class="btn btn-light text-primary">
            <i data-feather="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <form method="POST" action="{{ route('waste-reports.store') }}">
            @csrf

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Report Details</div>
                <div class="card-body">
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

            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">Wasted Items</div>
                    <button type="button" class="btn btn-sm btn-light" id="add-waste-row">
                        <i data-feather="plus"></i> Add Item
                    </button>
                </div>
                <div class="card-body">
                    @error('items')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="waste-items-table">
                            <thead class="table-dark">
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
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('waste-reports.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">Save Waste Report</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemOptions = @json($itemOptions);
    const reasons = @json($reasons);
    const tableBody = document.querySelector('#waste-items-table tbody');
    const addButton = document.getElementById('add-waste-row');
    const branchField = document.getElementById('branch-id');

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
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
        `;
    }

    addButton?.addEventListener('click', function () {
        const row = document.createElement('tr');
        row.innerHTML = rowTemplate();
        tableBody.appendChild(row);
        reindexRows();
    });

    tableBody?.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-row')) {
            return;
        }

        if (tableBody.querySelectorAll('tr').length === 1) {
            return;
        }

        event.target.closest('tr').remove();
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
            row.querySelector('.item-picker-results').classList.add('d-none');
            return;
        }

        if (!event.target.closest('.item-picker')) {
            document.querySelectorAll('.item-picker-results').forEach((results) => results.classList.add('d-none'));
        }
    });

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
        results.classList.remove('d-none');
    }

    function clearItemSelections() {
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
    position: absolute;
    z-index: 1050;
    top: calc(100% + .25rem);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    border: 1px solid #d4dae3;
    border-radius: .5rem;
    background: #fff;
    box-shadow: 0 .5rem 1rem rgba(33, 40, 50, .12);
}
.item-picker-option {
    display: flex;
    width: 100%;
    justify-content: space-between;
    gap: 1rem;
    padding: .7rem .8rem;
    border: 0;
    border-bottom: 1px solid #edf0f4;
    background: #fff;
    text-align: left;
    font-size: .85rem;
}
.item-picker-option:hover { background: #f2f6fc; color: #0061f2; }
.available-display:disabled,
.unit-display:disabled { background: #f2f6fc; color: #475569; font-weight: 600; }
</style>
@endpush
