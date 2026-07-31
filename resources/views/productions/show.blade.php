@extends('layouts.app')

@section('page_title', 'Production Details - Dianne Seafood House')

@section('content')
    @php
        $scrapItems = $production->wastageReports->flatMap->items->values();
        $totalInputQuantity = $production->inputs->sum(fn ($input) => (float) $input->quantity_used);
        $totalOutputQuantity = $production->outputs->sum(fn ($output) => (float) $output->quantity_produced);
        $totalScrapQuantity = $scrapItems->sum(fn ($item) => (float) $item->quantity_lost);
    @endphp

    <x-page-header :title="'Production #' . $production->id" :subtitle="'Branch: ' . ($production->branch?->name ?? 'N/A')" icon="settings">
        <a href="{{ route('productions.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Status</span>
                        <div class="detail-stat-value text-capitalize">{{ str_replace('_', ' ', $production->status) }}</div>
                        <div class="detail-stat-meta">
                            @if($production->finished_at)
                                Finished {{ $production->finished_at->format('M d, Y h:i A') }}
                            @elseif($production->cancelled_at)
                                Cancelled {{ $production->cancelled_at->format('M d, Y h:i A') }}
                            @else
                                Still in progress
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Created By</span>
                        <div class="detail-stat-value">{{ $production->creator?->name ?? 'N/A' }}</div>
                        <div class="detail-stat-meta">Started {{ $production->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Outputs</span>
                        <div class="detail-stat-value">{{ $production->outputs->count() }}</div>
                        <div class="detail-stat-meta">{{ number_format($totalOutputQuantity, 2) }} total produced</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Scrap Records</span>
                        <div class="detail-stat-value">{{ $scrapItems->count() }}</div>
                        <div class="detail-stat-meta">{{ number_format($totalScrapQuantity, 2) }} total scrap logged</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Raw Inputs</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" data-no-table-enhance="1">
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
                                            <span class="badge bg-warning text-white">Delivery</span>
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
                        <div class="detail-table-footer">
                            Total input quantity: <strong>{{ number_format($totalInputQuantity, 2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($production->outputs->isNotEmpty())
        <div class="row g-4 mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Finished Outputs</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" data-no-table-enhance="1">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity Produced</th>
                                        <th>Unit</th>
                                        <th>Allocated To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($production->outputs as $output)
                                    <tr>
                                        <td>
                                            {{ $output->item?->name ?? 'Unavailable item' }}
                                            @if($output->item)
                                            <div class="small text-muted">#{{ $output->item->id }} - {{ $output->item?->category?->location?->name ?? 'N/A' }} / {{ $output->item?->category?->name ?? 'N/A' }}</div>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $output->quantity_produced, 2) }}</td>
                                        <td>{{ $output->unit ?: ($output->item?->unit ?? 'N/A') }}</td>
                                        <td class="text-capitalize">{{ $output->allocated_to ?? 'inventory' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="detail-table-footer">
                            Total output quantity: <strong>{{ number_format($totalOutputQuantity, 2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($scrapItems->isNotEmpty())
        <div class="row g-4 mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Scrap Details</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" data-no-table-enhance="1">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Scrap Name</th>
                                        <th>Source Item</th>
                                        <th>Qty Lost</th>
                                        <th>Converted To</th>
                                        <th>Converted Qty</th>
                                        <th>Reason</th>
                                        <th>Logged At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($scrapItems as $scrapItem)
                                    <tr>
                                        <td>{{ $scrapItem->scrap_name ?: 'Scrap Conversion' }}</td>
                                        <td>{{ $scrapItem->item?->name ?? 'N/A' }}</td>
                                        <td>
                                            {{ number_format((float) $scrapItem->quantity_lost, 2) }}
                                            {{ $scrapItem->quantity_lost_unit ?? $scrapItem->item?->unit }}
                                        </td>
                                        <td>{{ $scrapItem->convertedItem?->name ?? 'Not converted' }}</td>
                                        <td>
                                            @if($scrapItem->converted_quantity)
                                                {{ number_format((float) $scrapItem->converted_quantity, 2) }}
                                                {{ $scrapItem->convertedItem?->unit }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $scrapItem->reason ?: '—' }}</td>
                                        <td>{{ $scrapItem->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="detail-table-footer">
                            Scrap rows: <strong>{{ $scrapItems->count() }}</strong> |
                            Total scrap quantity: <strong>{{ number_format($totalScrapQuantity, 2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @elseif($production->status === 'finished')
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Scrap Details</div>
            <div class="card-body text-muted">
                No scrap records were logged for this production.
            </div>
        </div>
        @endif

        @if($production->status !== 'finished')
        <div class="card shadow-sm mb-4 production-finish-card">
            <div class="card-header fw-semibold">Finish Production</div>
            <div class="card-body">
                <form method="POST" action="{{ route('productions.finish', $production) }}">
                    @csrf
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Finished Outputs</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-output-row">
                            <i data-lucide="plus"></i> Add Output
                        </button>
                    </div>

                    <div class="table-responsive production-form-table-wrap">
                        <table class="table align-middle production-form-table" id="production-outputs-table" data-no-table-enhance="1">
                            <colgroup>
                                <col class="production-col-item">
                                <col class="production-col-qty">
                                <col class="production-col-unit">
                                <col class="production-col-action">
                            </colgroup>
                            <thead>
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
                                        <input type="search" class="form-control item-picker-search" placeholder="Search item by name or ID" autocomplete="off">
                                            <input type="hidden" name="outputs[0][item_id]" data-output-field="item_id">
                                            <div class="item-picker-results d-none"></div>
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="outputs[0][quantity_produced]" class="form-control"></td>
                                    <td>
                                        <input type="text" class="form-control unit-display" value="Select an item" disabled>
                                        <input type="hidden" name="outputs[0][unit]" data-output-field="unit">
                                    </td>
                                    <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Scrap Conversion</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-wastage-row">
                            <i data-lucide="plus"></i> Add Conversion Row
                        </button>
                    </div>

                    @error('wastage')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <div class="table-responsive production-form-table-wrap">
                        <table class="table align-middle production-form-table production-waste-table" id="wastage-items-table" data-no-table-enhance="1">
                            <colgroup>
                                <col class="production-col-convert">
                                <col class="production-col-converted">
                                <col class="production-col-convert-unit">
                                <col class="production-col-action">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Convert To</th>
                                    <th>Convert Qty</th>
                                    <th>Convert Unit</th>
                                    <th class="table-actions-head">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="item-picker">
                                            <input type="search" class="form-control item-picker-search" placeholder="Search item (optional)" autocomplete="off">
                                            <input type="hidden" name="wastage[0][convert_to_item_id]" data-field="convert_to_item_id">
                                            <div class="item-picker-results d-none"></div>
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="wastage[0][converted_quantity]" class="form-control" data-field="converted_quantity"></td>
                                    <td><input type="text" class="form-control unit-display" value="Select Convert To" data-wastage-unit-display="convert" disabled></td>
                                    <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button></td>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemOptions = @json($items->map(fn ($item) => ['id' => $item->id, 'unit' => $item->unit, 'label' => '#' . $item->id . ' - ' . $item->name . ' (' . ($item->category?->location?->name ?? 'N/A') . ' / ' . ($item->category?->name ?? 'N/A') . ')'])->values());
    let activePickerInput = null;

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
            if (typeof window.refreshLucideIcons === 'function') {
                window.refreshLucideIcons();
            }
        });

        tableBody.addEventListener('click', function (event) {
            const removeButton = event.target.closest('.remove-row');
            if (!removeButton) {
                return;
            }

            if (tableBody.querySelectorAll('tr').length === 1) {
                return;
            }

            hideAllPickerResults();
            removeButton.closest('tr').remove();
            reindex();
        });

        reindex();
    }

    bindDynamicTable('production-outputs-table', 'add-output-row', function () {
        return `
            <td><div class="item-picker"><input type="search" class="form-control item-picker-search" placeholder="Search item by name or ID" autocomplete="off"><input type="hidden" data-output-field="item_id"><div class="item-picker-results d-none"></div></div></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-output-field="quantity_produced"></td>
            <td><input type="text" class="form-control unit-display" value="Select an item" disabled><input type="hidden" data-output-field="unit"></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button></td>
        `;
    }, []);

    bindDynamicTable('wastage-items-table', 'add-wastage-row', function () {
        return `
            <td><div class="item-picker"><input type="search" class="form-control item-picker-search" placeholder="Search item (optional)" autocomplete="off"><input type="hidden" data-field="convert_to_item_id"><div class="item-picker-results d-none"></div></div></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-field="converted_quantity"></td>
            <td><input type="text" class="form-control unit-display" value="Select Convert To" data-wastage-unit-display="convert" disabled></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i data-lucide="trash-2"></i></button></td>
        `;
    }, ['convert_to_item_id', 'converted_quantity']);

    document.addEventListener('input', function (event) {
        if (!event.target.classList.contains('item-picker-search')) return;
        const row = event.target.closest('tr');
        const hidden = row.querySelector('[data-output-field="item_id"], [data-field="convert_to_item_id"]');
        hidden.value = '';
        const unitDisplay = hidden.hasAttribute('data-output-field')
            ? row.querySelector('.unit-display')
            : row.querySelector('[data-wastage-unit-display="convert"]');
        unitDisplay.value = hidden.hasAttribute('data-output-field') ? 'Select an item' : 'Select Convert To';
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
            const unitDisplay = hidden.hasAttribute('data-output-field')
                ? row.querySelector('.unit-display')
                : row.querySelector('[data-wastage-unit-display="convert"]');
            unitDisplay.value = item.unit;
            const unitHidden = row.querySelector('[data-output-field="unit"]');
            if (unitHidden) unitHidden.value = item.unit;
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
        const query = input.value.trim().toLowerCase();
        const results = input.closest('.item-picker').querySelector('.item-picker-results');
        const matches = itemOptions.filter(item => item.label.toLowerCase().includes(query)).slice(0, 20);
        results.innerHTML = matches.length
            ? matches.map(item => `<button type="button" class="item-picker-option" data-item-id="${item.id}"><span>${escapeHtml(item.label)}</span><strong>${escapeHtml(item.unit)}</strong></button>`).join('')
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
});
</script>
@endpush

@push('styles')
<style>
.production-finish-card .card-body {
    padding: 1rem;
}

.detail-stat-card .card-body {
    padding: 1rem 1.1rem;
}

.detail-stat-label {
    display: inline-block;
    margin-bottom: .45rem;
    color: var(--text-muted);
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.detail-stat-value {
    color: var(--text-main);
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.2;
}

.detail-stat-meta {
    margin-top: .45rem;
    color: var(--text-muted);
    font-size: .88rem;
}

.detail-table-footer {
    margin-top: .9rem;
    color: var(--text-muted);
    font-size: .9rem;
}

.production-form-table-wrap {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-card);
}

.production-form-table {
    min-width: 920px;
    table-layout: fixed;
    margin-bottom: 0;
}

.production-waste-table {
    min-width: 1140px;
}

.production-form-table thead th {
    padding: .9rem 1rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-main);
    color: var(--text-muted);
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    white-space: nowrap;
}

.production-form-table tbody td {
    padding: .9rem 1rem;
    border-color: var(--border-color);
    vertical-align: middle;
}

.production-form-table .form-control {
    min-height: 2.55rem;
    border-radius: 8px;
}

.production-form-table .remove-row {
    width: 2.25rem;
    height: 2.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.production-col-item { width: 32%; }
.production-col-qty { width: 29%; }
.production-col-unit { width: 29%; }
.production-col-action { width: 96px; }
.production-col-scrap-name { width: 220px; }
.production-col-scrap-qty { width: 150px; }
.production-col-scrap-unit { width: 150px; }
.production-col-convert { width: 280px; }
.production-col-converted { width: 160px; }
.production-col-convert-unit { width: 170px; }

.item-picker {
    position: relative;
    min-width: 0;
}

.item-picker-results {
    position: fixed;
    z-index: 2055;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: .65rem;
    background: var(--bg-card);
    box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .18);
}

.item-picker-option {
    display: flex;
    width: 100%;
    justify-content: space-between;
    gap: 1rem;
    padding: .7rem .85rem;
    border: 0;
    border-bottom: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-main);
    text-align: left;
    font-size: .85rem;
}

.item-picker-option:hover {
    background: rgba(var(--primary-color-rgb), .12);
    color: var(--primary-color);
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

.item-picker-option span {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    overflow-wrap: normal;
    word-break: normal;
}

.unit-display:disabled {
    background: var(--bg-main);
    color: var(--text-main);
    font-weight: 700;
    opacity: 1;
}

@media (max-width: 767.98px) {
    .production-finish-card .d-flex.justify-content-between {
        align-items: flex-start !important;
        flex-direction: column;
    }
}
</style>
@endpush
