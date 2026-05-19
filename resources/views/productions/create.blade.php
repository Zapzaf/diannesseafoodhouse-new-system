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

                    <div class="mt-4 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Raw Inputs <span class="text-muted fw-normal small">(inventory items to consume)</span></h6>
                        <button type="button" class="btn btn-sm btn-light" id="add-production-input">
                            <i data-feather="plus"></i> Add Input
                        </button>
                    </div>

                    @error('inputs') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                    <div class="table-responsive mt-3">
                        <table class="table table-bordered align-middle" id="production-inputs-table">
                            <thead class="table-dark">
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
                                        <select name="inputs[0][item_id]" class="form-select" required>
                                            <option value="">Select item</option>
                                            @foreach($items as $item)
                                            <option value="{{ $item->id }}">
                                                #{{ $item->id }} - {{ $item->name }}
                                                ({{ $item->category?->location?->name ?? 'N/A' }} / {{ $item->category?->name ?? 'N/A' }})
                                                — {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" min="0.01" name="inputs[0][quantity_used]" class="form-control" required></td>
                                    <td>
                                        <select name="inputs[0][unit]" class="form-select" required>
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
    const unitOptions = ['pcs','kg','g','lbs','oz','liters','ml','boxes','bags','packs','trays','rolls','cans','bottles','dozens'];

    function renderItemOptions() {
        var html = '<option value="">Select item</option>';
        itemOptions.forEach(function (item) {
            html += '<option value="' + item.value + '">' + item.label + '</option>';
        });
        return html;
    }

    function renderUnitOptions() {
        return unitOptions.map(function (unit) {
            return '<option value="' + unit + '">' + unit + '</option>';
        }).join('');
    }

    function reindexRows() {
        var selItem = 'select[name$="[item_id]"]';
        var inpQty  = 'input[name$="[quantity_used]"]';
        var selUnit = 'select[name$="[unit]"]';
        Array.from(tableBody.querySelectorAll('tr')).forEach(function (row, index) {
            row.querySelector(selItem).name = 'inputs[' + index + '][item_id]';
            row.querySelector(inpQty).name  = 'inputs[' + index + '][quantity_used]';
            row.querySelector(selUnit).name = 'inputs[' + index + '][unit]';
        });
    }

    addButton.addEventListener('click', function () {
        var idx = tableBody.querySelectorAll('tr').length;
        var row = document.createElement('tr');
        row.innerHTML =
            '<td><select class="form-select" name="inputs[' + idx + '][item_id]" required>' + renderItemOptions() + '</select></td>' +
            '<td><input type="number" step="0.01" min="0.01" name="inputs[' + idx + '][quantity_used]" class="form-control" required></td>' +
            '<td><select class="form-select" name="inputs[' + idx + '][unit]" required>' + renderUnitOptions() + '</select></td>' +
            '<td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>';
        tableBody.appendChild(row);
        reindexRows();
    });

    tableBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row')) {
            if (tableBody.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
                reindexRows();
            }
        }
    });

    reindexRows();
});
</script>
@endpush
