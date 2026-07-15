@extends('layouts.app')
@section('page_title', 'New Costing Report - Dianne Seafood House')
@section('content')
<x-page-header title="New Costing Report" subtitle="Submit an item price change for admin review" icon="file-plus">
    <a href="{{ route('reports.costing.index') }}" class="btn btn-light">
        <i data-lucide="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <form method="POST" action="{{ route('reports.costing.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Item & pricing --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold"><i data-lucide="package" class="me-1"></i> Item & Pricing</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="item_id" class="form-label fw-semibold">Affected Item <span class="text-danger">*</span></label>
                                <select name="item_id" id="item_id" class="form-select @error('item_id') is-invalid @enderror" required>
                                    <option value="">Select item</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}"
                                            data-price="{{ number_format((float) ($item->unit_price ?? 0), 4, '.', '') }}"
                                            @selected((int) old('item_id', $selectedItemId) === (int) $item->id)>
                                            {{ $item->name }} - {{ $item->branch?->name ?? 'N/A' }} (&#8369;{{ number_format((float) ($item->unit_price ?? 0), 4) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="current_price_display" class="form-label fw-semibold">Current Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="text" id="current_price_display" class="form-control" value="0.0000" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="proposed_price" class="form-label fw-semibold">Proposed New Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.0001" min="0.0001" name="proposed_price" id="proposed_price"
                                           class="form-control @error('proposed_price') is-invalid @enderror"
                                           value="{{ old('proposed_price') }}" required>
                                    @error('proposed_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Difference</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="text" id="price_diff_display" class="form-control" value="—" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reason --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold"><i data-lucide="message-square" class="me-1"></i> Reason / Justification</div>
                    <div class="card-body">
                        <div class="btn-group w-100 mb-3" role="group" aria-label="Reason source">
                            @foreach([
                                'delivery' => ['truck', 'From Delivery'],
                                'production' => ['factory', 'From Production'],
                                'others' => ['pencil', 'Others'],
                            ] as $value => [$icon, $label])
                            <input type="radio" class="btn-check" name="reason_type" id="reason_{{ $value }}" value="{{ $value }}"
                                   @checked(old('reason_type', 'others') === $value) required>
                            <label class="btn btn-outline-primary" for="reason_{{ $value }}">
                                <i data-lucide="{{ $icon }}" class="me-1" style="width:15px;height:15px;"></i> {{ $label }}
                            </label>
                            @endforeach
                        </div>
                        @error('reason_type')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                        <div data-reason-panel="delivery" class="d-none">
                            <label for="delivery_id" class="form-label fw-semibold">Select Delivery <span class="text-danger">*</span></label>
                            <select name="delivery_id" id="delivery_id" class="form-select @error('delivery_id') is-invalid @enderror">
                                <option value="">— Select a delivery —</option>
                                @foreach($deliveries as $delivery)
                                <option value="{{ $delivery->id }}" @selected((string) old('delivery_id') === (string) $delivery->id)>
                                    Delivery #{{ $delivery->id }} — {{ $delivery->supplier?->name ?? 'No supplier' }} — {{ $delivery->created_at?->format('M d, Y') }} ({{ strtoupper($delivery->status ?? '') }})
                                </option>
                                @endforeach
                            </select>
                            @error('delivery_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">The price change will be justified by this delivery's costs.</div>
                        </div>

                        <div data-reason-panel="production" class="d-none">
                            <label for="production_id" class="form-label fw-semibold">Select Production Order <span class="text-danger">*</span></label>
                            <select name="production_id" id="production_id" class="form-select @error('production_id') is-invalid @enderror">
                                <option value="">— Select a production order —</option>
                                @foreach($productions as $production)
                                <option value="{{ $production->id }}" @selected((string) old('production_id') === (string) $production->id)>
                                    Production #{{ $production->id }} — {{ $production->created_at?->format('M d, Y') }} ({{ strtoupper($production->status ?? '') }})
                                </option>
                                @endforeach
                            </select>
                            @error('production_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">The price change will be justified by this production run's costs.</div>
                        </div>

                        <div data-reason-panel="others" class="d-none">
                            <label for="reason_text" class="form-label fw-semibold">Describe the Reason <span class="text-danger">*</span></label>
                            <textarea name="reason_text" id="reason_text" rows="4"
                                      class="form-control @error('reason_text') is-invalid @enderror"
                                      placeholder="Explain why this item's price should change...">{{ old('reason_text') }}</textarea>
                            @error('reason_text')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Costing details --}}
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold"><i data-lucide="list" class="me-1"></i> Supporting Costing Details</div>
                    <div class="card-body">
                        <textarea name="costing_details" id="costing_details" rows="5"
                                  class="form-control @error('costing_details') is-invalid @enderror"
                                  placeholder="Ingredients, inventory cost changes, labor, overhead, markup, or other supporting computations">{{ old('costing_details') }}</textarea>
                        @error('costing_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Optional — but detailed computations help the admin review faster.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Supporting documents --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold"><i data-lucide="paperclip" class="me-1"></i> Supporting Documents</div>
                    <div class="card-body">
                        <label for="attachmentsInput" class="d-block border border-2 border-dashed rounded-3 text-center p-4 mb-2" style="cursor: pointer;">
                            <i data-lucide="upload-cloud" class="text-primary mb-2" style="width:32px;height:32px;"></i>
                            <div class="fw-semibold small">Click to add files</div>
                            <div class="text-muted" style="font-size:.75rem;">Receipts, quotations, computations…</div>
                        </label>
                        <input type="file" name="attachments[]" id="attachmentsInput" class="d-none"
                               multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.xls,.xlsx,.doc,.docx,.csv">
                        <ul class="list-group list-group-flush" id="attachmentList"></ul>
                        @error('attachments')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        @error('attachments.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        <div class="form-text mt-2">Up to 10 files, 5MB each. Images, PDF, Excel, Word, or CSV.</div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="card shadow-sm">
                    <div class="card-body d-grid gap-2">
                        <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
                            <i data-lucide="send" style="width: 17px; height: 17px;"></i>
                            <span>Submit for Review</span>
                        </button>
                        <a href="{{ route('reports.costing.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <div class="text-muted small text-center mt-1">The item price only changes after admin approval.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemSelect = document.getElementById('item_id');
    const priceDisplay = document.getElementById('current_price_display');
    const proposedInput = document.getElementById('proposed_price');
    const diffDisplay = document.getElementById('price_diff_display');

    function currentPrice() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        return Number(selected && selected.dataset.price ? selected.dataset.price : 0);
    }

    function refreshPrices() {
        const price = currentPrice();
        priceDisplay.value = price.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 4 });

        const proposed = parseFloat(proposedInput.value);
        if (!isNaN(proposed) && itemSelect.value) {
            const diff = proposed - price;
            diffDisplay.value = (diff >= 0 ? '+' : '') + diff.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 4 });
            diffDisplay.classList.toggle('text-success', diff > 0);
            diffDisplay.classList.toggle('text-danger', diff < 0);
        } else {
            diffDisplay.value = '—';
            diffDisplay.classList.remove('text-success', 'text-danger');
        }
    }

    itemSelect.addEventListener('change', refreshPrices);
    proposedInput.addEventListener('input', refreshPrices);
    refreshPrices();

    // Reason radios: show only the panel for the chosen source
    const panels = document.querySelectorAll('[data-reason-panel]');
    function refreshReasonPanels() {
        const chosen = document.querySelector('input[name="reason_type"]:checked')?.value || 'others';
        panels.forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.reasonPanel !== chosen);
        });
    }
    document.querySelectorAll('input[name="reason_type"]').forEach((radio) => {
        radio.addEventListener('change', refreshReasonPanels);
    });
    refreshReasonPanels();

    // Supporting documents: keep an accumulating list across multiple picks
    const input = document.getElementById('attachmentsInput');
    const list = document.getElementById('attachmentList');
    const store = new DataTransfer();

    function renderAttachmentList() {
        list.innerHTML = '';
        Array.from(store.files).forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center px-0';
            const sizeKb = file.size >= 1048576
                ? (file.size / 1048576).toFixed(1) + ' MB'
                : Math.max(1, Math.round(file.size / 1024)) + ' KB';
            const label = document.createElement('span');
            label.className = 'small text-truncate me-2';
            label.style.maxWidth = '75%';
            label.textContent = file.name + ' (' + sizeKb + ')';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger py-0 px-2';
            remove.innerHTML = '&times;';
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.addEventListener('click', function () {
                store.items.remove(index);
                input.files = store.files;
                renderAttachmentList();
            });
            li.append(label, remove);
            list.appendChild(li);
        });
    }

    input.addEventListener('change', function () {
        Array.from(input.files).forEach((file) => {
            const exists = Array.from(store.files).some((f) => f.name === file.name && f.size === file.size);
            if (!exists && store.files.length < 10) store.items.add(file);
        });
        input.files = store.files;
        renderAttachmentList();
    });
});
</script>
@endpush
