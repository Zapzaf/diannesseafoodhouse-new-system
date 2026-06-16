@extends('layouts.app')

@section('page_title', 'Production Details - Dianne Seafood House')

@section('content')
<main>
    <x-page-header :title="'Production #' . $production->id" :subtitle="'Branch: ' . ($production->branch?->name ?? 'N/A')" icon="settings">
        <a href="{{ route('productions.index') }}" class="btn btn-light text-primary">
            <i data-feather="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="row g-4 mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Raw Inputs</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="table-dark"><tr><th>Item</th><th>Source</th><th>Quantity Used</th><th>Unit</th></tr></thead>
                                <tbody>
                                    @foreach($production->inputs as $input)
                                    <tr>
                                        <td>
                                            {{ $input->item_id ? $input->item?->name : ($input->deliveryItem?->description ?? 'Delivery material') }}
                                            @if($input->item_id)
                                            <div class="small text-muted">#{{ $input->item_id }} - {{ $input->item?->category?->location?->name ?? 'N/A' }} / {{ $input->item?->category?->name ?? 'N/A' }}</div>
                                            @elseif($input->delivery_item_id)
                                            <div class="small text-muted">Delivery Item #{{ $input->delivery_item_id }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($input->delivery_item_id)
                                            <span class="badge bg-warning text-dark">Delivery</span>
                                            <div class="small text-muted mt-1">
                                                Batch {{ $input->deliveryItem?->delivery?->reference_number ?? ('#' . $input->deliveryItem?->delivery_id) }}
                                            </div>
                                            @else
                                            <span class="badge bg-primary">Inventory</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $input->quantity_used, 2) }}</td>
                                        <td>{{ $input->unit }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($production->status !== 'finished')
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Finish Production</div>
            <div class="card-body">
                <form method="POST" action="{{ route('productions.finish', $production) }}">
                    @csrf
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Finished Outputs</h6>
                        <button type="button" class="btn btn-sm btn-light" id="add-output-row">
                            <i data-feather="plus"></i> Add Output
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="production-outputs-table">
                            <thead class="table-dark">
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity Produced</th>
                                    <th>Unit</th>
                                    <th class="table-actions-head">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="item-picker">
                                            <input type="search" class="form-control item-picker-search" placeholder="Search item by name or ID" autocomplete="off" required>
                                            <input type="hidden" name="outputs[0][item_id]" data-output-field="item_id">
                                            <div class="item-picker-results d-none"></div>
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="outputs[0][quantity_produced]" class="form-control" required></td>
                                    <td>
                                        <input type="text" class="form-control unit-display" value="Select an item" disabled>
                                        <input type="hidden" name="outputs[0][unit]" data-output-field="unit">
                                    </td>
                                    <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Scrap and Waste</h6>
                        <button type="button" class="btn btn-sm btn-light" id="add-wastage-row">
                            <i data-feather="plus"></i> Add Waste Row
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="wastage-items-table">
                            <thead class="table-dark">
                                <tr>
                                    <th>Scrap / Waste Material</th>
                                    <th>Qty Lost</th>
                                    <th>Reason</th>
                                    <th>Convert To</th>
                                    <th>Converted Qty</th>
                                    <th>Convert Unit</th>
                                    <th class="table-actions-head">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="text" name="wastage[0][scrap_name]" class="form-control" data-field="scrap_name" placeholder="e.g. chicken bones, fat trims">
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="wastage[0][quantity_lost]" class="form-control" data-field="quantity_lost"></td>
                                    <td><input type="text" name="wastage[0][reason]" class="form-control" data-field="reason"></td>
                                    <td>
                                        <div class="item-picker">
                                            <input type="search" class="form-control item-picker-search" placeholder="Search item (optional)" autocomplete="off">
                                            <input type="hidden" name="wastage[0][convert_to_item_id]" data-field="convert_to_item_id">
                                            <div class="item-picker-results d-none"></div>
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="wastage[0][converted_quantity]" class="form-control" data-field="converted_quantity"></td>
                                    <td><input type="text" class="form-control unit-display" value="No conversion" disabled></td>
                                    <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">Finish Production</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemOptions = @json($items->map(fn ($item) => ['id' => $item->id, 'unit' => $item->unit, 'label' => '#' . $item->id . ' - ' . $item->name . ' (' . ($item->category?->location?->name ?? 'N/A') . ' / ' . ($item->category?->name ?? 'N/A') . ')'])->values());

    function bindDynamicTable(tableId, addButtonId, rowFactory, inputKeys) {
        const tableBody = document.querySelector(`#${tableId} tbody`);
        const addButton = document.getElementById(addButtonId);
        if (!tableBody || !addButton) {
            return;
        }

        function reindex() {
            Array.from(tableBody.querySelectorAll('tr')).forEach((row, index) => {
                inputKeys.forEach((key) => {
                    const field = row.querySelector(`[data-field="${key}"]`);
                    if (field) {
                        field.name = `wastage[${index}][${key}]`;
                    }
                });

                ['item_id', 'quantity_produced', 'unit'].forEach((key) => {
                    const field = row.querySelector(`[data-output-field="${key}"]`);
                    if (field) {
                        field.name = `outputs[${index}][${key}]`;
                    }
                });
            });
        }

        addButton.addEventListener('click', function () {
            const row = document.createElement('tr');
            row.innerHTML = rowFactory();
            tableBody.appendChild(row);
            reindex();
        });

        tableBody.addEventListener('click', function (event) {
            if (!event.target.classList.contains('remove-row')) {
                return;
            }

            if (tableBody.querySelectorAll('tr').length === 1) {
                return;
            }

            event.target.closest('tr').remove();
            reindex();
        });

        reindex();
    }

    bindDynamicTable('production-outputs-table', 'add-output-row', function () {
        return `
            <td><div class="item-picker"><input type="search" class="form-control item-picker-search" placeholder="Search item by name or ID" autocomplete="off" required><input type="hidden" data-output-field="item_id"><div class="item-picker-results d-none"></div></div></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-output-field="quantity_produced" required></td>
            <td><input type="text" class="form-control unit-display" value="Select an item" disabled><input type="hidden" data-output-field="unit"></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
        `;
    }, []);

    bindDynamicTable('wastage-items-table', 'add-wastage-row', function () {
        return `
            <td><input type="text" class="form-control" data-field="scrap_name" placeholder="e.g. fish bones, skin"></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-field="quantity_lost"></td>
            <td><input type="text" class="form-control" data-field="reason"></td>
            <td><div class="item-picker"><input type="search" class="form-control item-picker-search" placeholder="Search item (optional)" autocomplete="off"><input type="hidden" data-field="convert_to_item_id"><div class="item-picker-results d-none"></div></div></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-field="converted_quantity"></td>
            <td><input type="text" class="form-control unit-display" value="No conversion" disabled></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
        `;
    }, ['scrap_name', 'quantity_lost', 'reason', 'convert_to_item_id', 'converted_quantity']);

    document.addEventListener('input', function (event) {
        if (!event.target.classList.contains('item-picker-search')) return;
        const row = event.target.closest('tr');
        const hidden = row.querySelector('[data-output-field="item_id"], [data-field="convert_to_item_id"]');
        hidden.value = '';
        const unitDisplay = row.querySelector('.unit-display');
        unitDisplay.value = hidden.hasAttribute('data-output-field') ? 'Select an item' : 'No conversion';
        const unitHidden = row.querySelector('[data-output-field="unit"]');
        if (unitHidden) unitHidden.value = '';
        renderResults(event.target);
    });

    document.addEventListener('focusin', function (event) {
        if (event.target.classList.contains('item-picker-search')) renderResults(event.target);
    });

    document.addEventListener('click', function (event) {
        const option = event.target.closest('.item-picker-option');
        if (option) {
            const row = option.closest('tr');
            const item = itemOptions.find(candidate => String(candidate.id) === option.dataset.itemId);
            const hidden = row.querySelector('[data-output-field="item_id"], [data-field="convert_to_item_id"]');
            row.querySelector('.item-picker-search').value = item.label;
            hidden.value = item.id;
            row.querySelector('.unit-display').value = item.unit;
            const unitHidden = row.querySelector('[data-output-field="unit"]');
            if (unitHidden) unitHidden.value = item.unit;
            row.querySelector('.item-picker-results').classList.add('d-none');
            return;
        }

        if (!event.target.closest('.item-picker')) {
            document.querySelectorAll('.item-picker-results').forEach(results => results.classList.add('d-none'));
        }
    });

    function renderResults(input) {
        const query = input.value.trim().toLowerCase();
        const results = input.closest('.item-picker').querySelector('.item-picker-results');
        const matches = itemOptions.filter(item => item.label.toLowerCase().includes(query)).slice(0, 20);
        results.innerHTML = matches.length
            ? matches.map(item => `<button type="button" class="item-picker-option" data-item-id="${item.id}"><span>${escapeHtml(item.label)}</span><strong>${escapeHtml(item.unit)}</strong></button>`).join('')
            : '<div class="p-3 text-muted small">No matching items.</div>';
        results.classList.remove('d-none');
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }
});
</script>
@endpush

@push('styles')
<style>
.item-picker { position: relative; min-width: 280px; }
.item-picker-results { position: absolute; z-index: 1050; top: calc(100% + .25rem); left: 0; right: 0; max-height: 280px; overflow-y: auto; border: 1px solid #d4dae3; border-radius: .5rem; background: #fff; box-shadow: 0 .5rem 1rem rgba(33, 40, 50, .12); }
.item-picker-option { display: flex; width: 100%; justify-content: space-between; gap: 1rem; padding: .65rem .75rem; border: 0; border-bottom: 1px solid #edf0f4; background: #fff; text-align: left; font-size: .85rem; }
.item-picker-option:hover { background: #f2f6fc; color: #0061f2; }
.unit-display:disabled { background: #f2f6fc; color: #475569; font-weight: 600; }
</style>
@endpush
