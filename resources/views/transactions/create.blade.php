@extends('layouts.app')
@section('page_title', 'Log Transaction - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="New Transaction" subtitle="Manually record a stock-in or stock-out movement" icon="list">
    <a href="{{ route('transactions.index') }}" class="btn btn-light text-primary">
        <i data-feather="arrow-left" class="me-1"></i> All Transactions
    </a>
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    <div class="card shadow-sm">
        <div class="card-header"><i data-feather="edit-3" class="me-1"></i> Transaction Details</div>
        <div class="card-body">
            <form action="{{ route('transactions.store') }}" method="POST" id="transactionForm">
                @csrf

                @php $isAdminAllBranches = auth()->user()->isAdmin() && !session('selected_branch_id'); @endphp

                @if($isAdminAllBranches)
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i data-feather="git-branch" class="me-1" style="width:15px;height:15px"></i>
                        Filter by Branch <span class="text-danger">*</span>
                        <span class="badge bg-primary ms-1" style="font-size:.65rem">Admin</span>
                    </label>
                    <select id="branch_filter" class="form-select" required>
                        <option value="">— Select a branch first —</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('_branch_filter') == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                        @endforeach
                    </select>
                    <div class="form-text">Selecting a branch will filter the item list below.</div>
                </div>
                @endif

                <div class="mb-3">
                    <label class="form-label fw-semibold">Transaction Type <span class="text-danger">*</span></label>
                    <select name="type" id="transaction_type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">— Select type —</option>
                        <option value="in" {{ old('type') === 'in' ? 'selected' : '' }}>Stock In (Add)</option>
                        <option value="out" {{ old('type') === 'out' ? 'selected' : '' }}>Stock Out (Deduct)</option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Item rows --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Items <span class="text-danger">*</span></label>
                    <div id="itemRowsContainer"></div>
                    <button type="button" id="addItemBtn" class="btn btn-sm btn-outline-primary mt-2">
                        <i data-feather="plus" class="me-1" style="width:14px;height:14px"></i> Add Item
                    </button>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                    <select name="reason" id="reasonSelect" class="form-select @error('reason') is-invalid @enderror" required>
                        <option value="">— Select a reason —</option>
                        @php
                            $reasons = [
                                'Customer Complaint Replacement',
                                'Damaged / Spoilage / Wastage',
                                'Expired / Unusable Stock',
                                'Internal Use (Kitchen / Office)',
                                'Inventory Adjustment / Stock Correction',
                                'Lost / Theft Item',
                                'Recipe / Production Use',
                                'Returned to Supplier',
                                'Sale (Catering / Online)',
                                'Sale (Dine-in / Takeout / Delivery)',
                                'Sale (Staff Meal / Promo)',
                                'System / Encoding Error',
                                'Transfer to Another Branch',
                                'Withdrawal',
                                'Others',
                            ];
                        @endphp
                        @foreach($reasons as $reason)
                        <option value="{{ $reason }}" {{ old('reason') === $reason ? 'selected' : '' }}>{{ $reason }}</option>
                        @endforeach
                    </select>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4" id="customReasonWrapper" style="display:none;">
                    <label class="form-label fw-semibold">Please specify <span class="text-danger">*</span></label>
                    <input type="text" name="custom_reason" id="customReasonInput" maxlength="255"
                           class="form-control" value="{{ old('custom_reason') }}"
                           placeholder="Enter your reason...">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Transaction Date</label>
                    <input type="datetime-local" name="transaction_date"
                           class="form-control @error('transaction_date') is-invalid @enderror"
                           value="{{ old('transaction_date', now()->format('Y-m-d\TH:i')) }}">
                    <div class="form-text">Use a past date for delayed entries (e.g. items received at the main branch on a different day).</div>
                    @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i data-feather="save" class="me-1"></i> Save Transaction
                    </button>
                    <a href="{{ route('transactions.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</main>
@endsection

@push('styles')
<style>
/* Searchable dropdown styles */
.item-search-wrapper {
    position: relative;
    flex: 1;
}
.item-search-input {
    width: 100%;
}
.item-search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    max-height: 320px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 .375rem .375rem;
    box-shadow: 0 6px 16px rgba(0,0,0,.12);
    display: none;
}
.item-search-dropdown.show { display: block; }

.item-search-option {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background .15s;
}
.item-search-option:last-child { border-bottom: none; }
.item-search-option:hover,
.item-search-option.active { background: #e8f0fe; }
.item-search-option .item-name { font-weight: 600; color: #1e293b; }
.item-search-option .item-meta {
    font-size: .78rem;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    gap: 6px 14px;
    margin-top: 2px;
}
.item-search-option .item-meta span {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.item-search-option .badge-stock {
    font-size: .7rem;
    padding: 2px 6px;
    border-radius: 4px;
}
.item-search-option .badge-stock.sufficient { background: #dcfce7; color: #166534; }
.item-search-option .badge-stock.low        { background: #fef9c3; color: #854d0e; }
.item-search-option .badge-stock.empty      { background: #fecaca; color: #991b1b; }
.item-search-no-results {
    padding: 12px;
    text-align: center;
    color: #94a3b8;
    font-size: .85rem;
}

/* Item row card */
.item-row {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: .5rem;
    padding: 14px;
    margin-bottom: 10px;
    position: relative;
    transition: box-shadow .2s;
}
.item-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.item-row .remove-item-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    border: none;
    background: none;
    color: #ef4444;
    cursor: pointer;
    opacity: .6;
    transition: opacity .15s;
}
.item-row .remove-item-btn:hover { opacity: 1; }

.selected-item-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e0f2fe;
    color: #0369a1;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: .82rem;
    font-weight: 500;
}
.selected-item-badge .clear-selection {
    cursor: pointer;
    color: #0369a1;
    font-weight: 700;
    margin-left: 2px;
}
.selected-item-badge .clear-selection:hover { color: #ef4444; }

.stock-warning {
    color: #dc2626;
    font-size: .78rem;
    margin-top: 4px;
    display: none;
}
.stock-warning.show { display: block; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ────────────────────────────────────
    //  Build the item data from server
    // ────────────────────────────────────
    @php
        $itemsJson = $items->map(fn ($item) => [
            'id'        => $item->id,
            'name'      => $item->name,
            'branch_id' => $item->branch_id,
            'location'  => $item->category?->location?->name ?? '—',
            'category'  => $item->category?->name ?? '—',
            'quantity'  => (float) $item->quantity,
            'unit'      => $item->unit ?? 'N/A',
            'price'     => $item->unit_price !== null ? (float) $item->unit_price : null,
        ])->values();
    @endphp
    const allItems = @json($itemsJson);

    const isAdminAll = {{ $isAdminAllBranches ? 'true' : 'false' }};
    const branchFilter = document.getElementById('branch_filter');
    const container = document.getElementById('itemRowsContainer');
    const addBtn = document.getElementById('addItemBtn');
    const form = document.getElementById('transactionForm');
    const typeSelect = document.getElementById('transaction_type');
    const MAX_SEARCH_RESULTS = 30;
    const itemsByBranch = new Map();
    const itemsById = new Map();
    const alertIcon = ico('alert-triangle');

    let rowIndex = 0;

    allItems.forEach(item => {
        itemsById.set(item.id, item);
        item.searchText = `${item.name} ${item.location} ${item.category} ${item.unit}`.toLowerCase();
        const branchItems = itemsByBranch.get(item.branch_id) || [];
        branchItems.push(item);
        itemsByBranch.set(item.branch_id, branchItems);
    });

    // ────────────────────────────────────
    //  Get currently visible items
    // ────────────────────────────────────
    function getFilteredItems() {
        if (isAdminAll && branchFilter) {
            const bid = parseInt(branchFilter.value);
            if (!bid) return [];
            return itemsByBranch.get(bid) || [];
        }

        return allItems;
    }

    // ────────────────────────────────────
    //  Get item IDs already selected
    // ────────────────────────────────────
    function getSelectedItemIds() {
        const ids = new Set();
        container.querySelectorAll('input[data-selected-item-id]').forEach(input => {
            const val = parseInt(input.value);
            if (val) ids.add(val);
        });
        return ids;
    }

    // ────────────────────────────────────
    //  Render a single option element
    // ────────────────────────────────────
    function renderOption(item) {
        const div = document.createElement('div');
        div.className = 'item-search-option';
        div.dataset.itemId = item.id;

        let stockClass = 'sufficient';
        if (item.quantity <= 0) stockClass = 'empty';
        else if (item.quantity <= 5) stockClass = 'low';

        div.innerHTML = `
            <div class="item-name">${escapeHtml(item.name)}</div>
            <div class="item-meta">
                <span>${escapeHtml(item.location)}</span>
                <span>${escapeHtml(item.category)}</span>
                <span class="badge-stock ${stockClass}">${item.quantity} ${escapeHtml(item.unit)}</span>
                <span>${item.price !== null ? '₱' + Number(item.price).toFixed(2) : '—'}</span>
            </div>
        `;
        return div;
    }

    // ────────────────────────────────────
    //  Create an item row
    // ────────────────────────────────────
    function addItemRow() {
        const idx = rowIndex++;
        const row = document.createElement('div');
        row.className = 'item-row';
        row.dataset.rowIndex = idx;

        row.innerHTML = `
            <button type="button" class="remove-item-btn" title="Remove item">&times;</button>
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">Search & Select Item</label>
                    <div class="item-search-wrapper">
                        <input type="text" class="form-control item-search-input"
                               placeholder="${isAdminAll && (!branchFilter || !branchFilter.value) ? '— Select a branch first —' : 'Type to search items...'}"
                               autocomplete="off"
                               ${isAdminAll && (!branchFilter || !branchFilter.value) ? 'disabled' : ''}>
                        <div class="item-search-dropdown"></div>
                        <input type="hidden" name="items[${idx}][item_id]" value="" data-selected-item-id>
                    </div>
                    <div class="selected-item-display mt-1" style="display:none;"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Quantity</label>
                    <input type="number" name="items[${idx}][quantity]" step="0.01" min="0.01"
                           class="form-control item-qty-input" placeholder="0" required>
                    <div class="stock-warning">${alertIcon} Exceeds available stock!</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Transaction Price <span class="text-muted">(automatic)</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" class="form-control item-price-input" placeholder="Select an item" disabled>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(row);
        setupRowBehavior(row);
        updateRemoveButtons();

        // Focus the search input
        const searchInput = row.querySelector('.item-search-input');
        if (searchInput && !searchInput.disabled) searchInput.focus();
    }

    // ────────────────────────────────────
    //  Setup event handlers on a row
    // ────────────────────────────────────
    function setupRowBehavior(row) {
        const searchInput  = row.querySelector('.item-search-input');
        const dropdown     = row.querySelector('.item-search-dropdown');
        const hiddenInput  = row.querySelector('input[data-selected-item-id]');
        const displayDiv   = row.querySelector('.selected-item-display');
        const qtyInput     = row.querySelector('.item-qty-input');
        const priceInput   = row.querySelector('.item-price-input');
        const stockWarning = row.querySelector('.stock-warning');
        const removeBtn    = row.querySelector('.remove-item-btn');
        let selectedItem   = null;

        // Remove button
        removeBtn.addEventListener('click', () => {
            row.remove();
            updateRemoveButtons();
        });

        // Show dropdown on focus
        searchInput.addEventListener('focus', () => showDropdown(''));
        searchInput.addEventListener('input', () => showDropdown(searchInput.value));

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!row.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        function showDropdown(query) {
            const items = getFilteredItems();
            const selectedIds = getSelectedItemIds();
            const q = query.trim().toLowerCase();

            if (!q) {
                dropdown.innerHTML = '<div class="item-search-no-results">Type to search items by name, location, category, or unit.</div>';
                dropdown.classList.add('show');
                return;
            }

            const currentItemId = parseInt(hiddenInput.value);
            const filtered = [];
            let hasMore = false;

            for (const item of items) {
                if ((selectedIds.has(item.id) && item.id !== currentItemId) || !item.searchText.includes(q)) {
                    continue;
                }

                if (filtered.length >= MAX_SEARCH_RESULTS) {
                    hasMore = true;
                    break;
                }

                filtered.push(item);
            }

            dropdown.innerHTML = '';
            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="item-search-no-results">No items found</div>';
            } else {
                const fragment = document.createDocumentFragment();
                filtered.forEach(item => {
                    const opt = renderOption(item);
                    opt.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        selectItem(item);
                    });
                    fragment.appendChild(opt);
                });
                dropdown.appendChild(fragment);

                if (hasMore) {
                    dropdown.insertAdjacentHTML(
                        'beforeend',
                        `<div class="item-search-no-results">Showing the first ${MAX_SEARCH_RESULTS} matches. Type more to narrow the results.</div>`
                    );
                }
            }
            dropdown.classList.add('show');
        }

        function selectItem(item) {
            selectedItem = item;
            hiddenInput.value = item.id;
            searchInput.style.display = 'none';
            dropdown.classList.remove('show');

            displayDiv.innerHTML = `
                <span class="selected-item-badge">
                    <strong>${escapeHtml(item.name)}</strong>
                    <span class="text-muted">(${escapeHtml(item.location)} › ${escapeHtml(item.category)})</span>
                    <span class="badge-stock sufficient" style="font-size:.72rem;">${item.quantity} ${escapeHtml(item.unit)}</span>
                    <span class="clear-selection" title="Change item">&times;</span>
                </span>
            `;
            displayDiv.style.display = 'block';

            // Auto-fill price based on quantity
            updatePrice();

            // Clear selection handler
            displayDiv.querySelector('.clear-selection').addEventListener('click', () => {
                clearSelection();
            });

            // Validate quantity immediately
            validateQuantity();
        }

        function clearSelection() {
            selectedItem = null;
            hiddenInput.value = '';
            searchInput.value = '';
            searchInput.style.display = '';
            displayDiv.style.display = 'none';
            displayDiv.innerHTML = '';
            priceInput.value = '';
            stockWarning.classList.remove('show');
            searchInput.focus();
        }

        // Quantity validation (client-side)
        qtyInput.addEventListener('input', validateQuantity);
        if (typeSelect) typeSelect.addEventListener('change', validateQuantity);

        function validateQuantity() {
            if (!selectedItem) return;
            const type = typeSelect ? typeSelect.value : '';
            const qty  = parseFloat(qtyInput.value) || 0;

            if (type === 'out' && qty > selectedItem.quantity) {
                stockWarning.innerHTML = `${alertIcon} Exceeds available stock! Only ${selectedItem.quantity} ${selectedItem.unit} available.`;
                stockWarning.classList.add('show');
            } else {
                stockWarning.classList.remove('show');
            }

            // Recalculate total price
            updatePrice();
        }

        function updatePrice() {
            if (!selectedItem || selectedItem.price === null) {
                priceInput.value = selectedItem ? 'No item price set' : '';
                return;
            }
            const qty = parseFloat(qtyInput.value) || 0;
            priceInput.value = qty > 0 ? Number(selectedItem.price * qty).toFixed(2) : Number(selectedItem.price).toFixed(2);
        }
    }

    // ────────────────────────────────────
    //  Show/hide remove buttons
    // ────────────────────────────────────
    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach(row => {
            const btn = row.querySelector('.remove-item-btn');
            btn.style.display = rows.length <= 1 ? 'none' : '';
        });
    }

    // ────────────────────────────────────
    //  Branch filter changes
    // ────────────────────────────────────
    if (branchFilter) {
        branchFilter.addEventListener('change', () => {
            // Clear all rows and add a fresh one
            container.innerHTML = '';
            rowIndex = 0;
            addItemRow();

            // Enable / disable inputs based on branch selection
            const hasValue = !!branchFilter.value;
            container.querySelectorAll('.item-search-input').forEach(input => {
                input.disabled = !hasValue;
                input.placeholder = hasValue ? 'Type to search items...' : '— Select a branch first —';
            });
        });
    }

    // ────────────────────────────────────
    //  Add item button
    // ────────────────────────────────────
    addBtn.addEventListener('click', addItemRow);

    // ────────────────────────────────────
    //  Form submit validation
    // ────────────────────────────────────
    form.addEventListener('submit', function (e) {
        const type = typeSelect ? typeSelect.value : '';
        const rows = container.querySelectorAll('.item-row');
        let hasError = false;

        rows.forEach(row => {
            const hiddenInput  = row.querySelector('input[data-selected-item-id]');
            const qtyInput     = row.querySelector('.item-qty-input');
            const stockWarning = row.querySelector('.stock-warning');
            const itemId       = parseInt(hiddenInput.value);
            const qty          = parseFloat(qtyInput.value) || 0;

            if (!itemId) {
                hasError = true;
                const searchInput = row.querySelector('.item-search-input');
                if (searchInput) searchInput.classList.add('is-invalid');
                return;
            }

            if (type === 'out') {
                const item = itemsById.get(itemId);
                if (item && qty > item.quantity) {
                    hasError = true;
                    stockWarning.innerHTML = `${alertIcon} Exceeds available stock! Only ${item.quantity} ${item.unit} available.`;
                    stockWarning.classList.add('show');
                    qtyInput.classList.add('is-invalid');
                }
            }
        });

        if (hasError) {
            e.preventDefault();
            alert('Please fix the highlighted errors before submitting.');
        }
    });

    // ────────────────────────────────────
    //  Utility
    // ────────────────────────────────────
    // Feather icon helper for JS template literals
    function ico(name) {
        return feather.icons[name] ? feather.icons[name].toSvg({ width: 14, height: 14, class: 'me-1 align-text-bottom' }) : '';
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ────────────────────────────────────
    //  Reason dropdown — "Others" toggle
    // ────────────────────────────────────
    const reasonSelect = document.getElementById('reasonSelect');
    const customWrapper = document.getElementById('customReasonWrapper');
    const customInput   = document.getElementById('customReasonInput');

    if (reasonSelect && customWrapper && customInput) {
        function toggleCustomReason() {
            const isOthers = reasonSelect.value === 'Others';
            customWrapper.style.display = isOthers ? '' : 'none';
            customInput.required = isOthers;
            if (!isOthers) customInput.value = '';
        }

        reasonSelect.addEventListener('change', toggleCustomReason);
        toggleCustomReason(); // apply on page load (e.g. old input)

        // On form submit, if "Others" is selected, replace the reason value
        form.addEventListener('submit', function () {
            if (reasonSelect.value === 'Others' && customInput.value.trim()) {
                reasonSelect.value = '';
                // Create a hidden input with the custom reason as the real reason
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'reason';
                hidden.value = customInput.value.trim();
                form.appendChild(hidden);
            }
        }, true); // capture phase, runs before the stock validation listener
    }

    // ────────────────────────────────────
    //  Initialize: add first row
    // ────────────────────────────────────
    addItemRow();
})();
</script>
@endpush
