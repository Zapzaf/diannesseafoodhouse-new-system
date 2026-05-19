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
                                        <select name="outputs[0][item_id]" class="form-select" required>
                                            <option value="">Select item</option>
                                            @foreach($items as $item)
                                            <option value="{{ $item->id }}">#{{ $item->id }} - {{ $item->name }} ({{ $item->category?->location?->name ?? 'N/A' }} / {{ $item->category?->name ?? 'N/A' }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="outputs[0][quantity_produced]" class="form-control" required></td>
                                    <td>
                                        <select name="outputs[0][unit]" class="form-select" required>
                                            <option value="pcs">pcs</option>
                                            <option value="kg">kg</option>
                                            <option value="g">g</option>
                                            <option value="lbs">lbs</option>
                                            <option value="oz">oz</option>
                                            <option value="liters">liters</option>
                                            <option value="ml">ml</option>
                                            <option value="boxes">boxes</option>
                                            <option value="bags">bags</option>
                                            <option value="packs">packs</option>
                                            <option value="trays">trays</option>
                                            <option value="rolls">rolls</option>
                                            <option value="cans">cans</option>
                                            <option value="bottles">bottles</option>
                                            <option value="dozens">dozens</option>
                                        </select>
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
                                        <select name="wastage[0][convert_to_item_id]" class="form-select" data-field="convert_to_item_id">
                                            <option value="">No conversion</option>
                                            @foreach($items as $item)
                                            <option value="{{ $item->id }}">#{{ $item->id }} - {{ $item->name }} ({{ $item->category?->location?->name ?? 'N/A' }} / {{ $item->category?->name ?? 'N/A' }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="wastage[0][converted_quantity]" class="form-control" data-field="converted_quantity"></td>
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
    const itemOptions = @json($items->map(fn ($item) => ['id' => $item->id, 'label' => '#' . $item->id . ' - ' . $item->name . ' (' . ($item->category?->location?->name ?? 'N/A') . ' / ' . ($item->category?->name ?? 'N/A') . ')'])->values());
    const unitOptions = ['pcs','kg','g','lbs','oz','liters','ml','boxes','bags','packs','trays','rolls','cans','bottles','dozens'];

    function selectOptions(includeBlankLabel = 'Select item') {
        return [`<option value="">${includeBlankLabel}</option>`]
            .concat(itemOptions.map(item => `<option value="${item.id}">${item.label}</option>`))
            .join('');
    }

    function unitSelectOptions() {
        return unitOptions.map(unit => `<option value="${unit}">${unit}</option>`).join('');
    }

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
            <td><select class="form-select" data-output-field="item_id" required>${selectOptions()}</select></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-output-field="quantity_produced" required></td>
            <td><select class="form-select" data-output-field="unit" required>${unitSelectOptions()}</select></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
        `;
    }, []);

    bindDynamicTable('wastage-items-table', 'add-wastage-row', function () {
        return `
            <td><input type="text" class="form-control" data-field="scrap_name" placeholder="e.g. fish bones, skin"></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-field="quantity_lost"></td>
            <td><input type="text" class="form-control" data-field="reason"></td>
            <td><select class="form-select" data-field="convert_to_item_id">${selectOptions('No conversion')}</select></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control" data-field="converted_quantity"></td>
            <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
        `;
    }, ['scrap_name', 'quantity_lost', 'reason', 'convert_to_item_id', 'converted_quantity']);
});
</script>
@endpush