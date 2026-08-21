@php
    $campaign = $campaign ?? null;
    $old = fn($key, $default = null) => old($key, $campaign?->{$key} ?? $default);
    $oldCodes = old('codes');
    if ($oldCodes === null) {
        $oldCodes = $campaign
            ? $campaign->codes->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'usage_limit' => $c->usage_limit])->all()
            : [];
    }
    if (empty($oldCodes)) {
        $oldCodes = [['id' => null, 'code' => '', 'usage_limit' => 1]];
    }
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-bold">Branch</label>
        <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
            <option value="">All Branches (company-wide)</option>
            @foreach($branches as $branch)
            <option value="{{ $branch->id }}" {{ (string) $old('branch_id') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Campaign Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ $old('name') }}" placeholder="e.g. Grand Opening 10% Off" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold">Description</label>
        <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ $old('description') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="border rounded p-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
                <div>
                    <label class="form-label fw-bold mb-0">Usage Limit</label>
                    <div class="form-text mt-0">Controls how many times this discount can be redeemed in total.</div>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="unified_usage_limit" value="1" id="unifiedUsageLimitSwitch" {{ $old('unified_usage_limit', $campaign?->unified_usage_limit ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="unifiedUsageLimitSwitch">Unified Usage Limit</label>
                </div>
            </div>

            <div id="unifiedUsageLimitField">
                <input type="number" name="usage_limit" class="form-control @error('usage_limit') is-invalid @enderror" value="{{ $old('usage_limit', $campaign?->usage_limit) }}" min="1" placeholder="Unlimited">
                @error('usage_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">One shared limit for the whole campaign — every coupon code below (and, for automatic discounts, every order) draws from this same pool. Leave blank for unlimited.</div>
            </div>

            <div id="perCodeUsageLimitNote" class="form-text mb-0 d-none">
                Unified is off, so each coupon code below has its own Usage Limit instead (set per code, default 1 use). Turn Unified back on to share one limit across all of them.
            </div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold">Coupon Code(s)</label>
        <div class="row g-2 mb-1 d-none d-md-flex text-muted small fw-semibold text-uppercase" style="letter-spacing: .04em;">
            <div class="col-md-7">Code</div>
            <div class="col-md-4">Usage Limit</div>
        </div>
        <div id="couponCodesContainer">
            @foreach($oldCodes as $i => $row)
            <div class="row g-2 mb-2 align-items-center coupon-code-row">
                <input type="hidden" name="codes[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                <div class="col-md-7">
                    <input type="text" name="codes[{{ $i }}][code]" class="form-control text-uppercase" value="{{ $row['code'] ?? '' }}" placeholder="e.g. WELCOME10">
                </div>
                <div class="col-md-4 coupon-code-usage-limit-cell">
                    <div class="input-group coupon-code-usage-limit-input">
                        <span class="input-group-text">Uses</span>
                        <input type="number" name="codes[{{ $i }}][usage_limit]" class="form-control" value="{{ $row['usage_limit'] ?? 1 }}" min="1" placeholder="1">
                    </div>
                    <div class="form-control-plaintext text-muted small coupon-code-pooled-note d-none">Shared campaign limit</div>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-danger coupon-code-remove text-white"><i data-lucide="x"></i></button>
                </div>
            </div>
            @endforeach
        </div>
        <button type="button" id="addCouponCodeBtn" class="btn btn-sm btn-primary text-white">
            <i data-lucide="plus-circle" class="me-1"></i> Add Another Code
        </button>
        @error('codes')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        <div class="form-text">Leave all blank to make this apply <strong>automatically</strong> when conditions are met — no code needed at checkout. Add more than one code to give this same discount several codes (e.g. one per channel or partner) — the discount type/value stay the same for all of them.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">Discount Type <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            <option value="percentage" {{ $old('type') === 'percentage' ? 'selected' : '' }}>Percentage</option>
            <option value="fixed" {{ $old('type') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">Value <span class="text-danger">*</span></label>
        <input type="number" name="value" class="form-control @error('value') is-invalid @enderror" value="{{ $old('value') }}" step="0.01" min="0.01" required>
        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Percent (0-100) or ₱ amount, depending on type above.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Max Discount Amount (₱)</label>
        <input type="number" name="max_discount_amount" class="form-control @error('max_discount_amount') is-invalid @enderror" value="{{ $old('max_discount_amount') }}" step="0.01" min="0">
        @error('max_discount_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Optional cap, useful for percentage discounts (e.g. "50% off, up to ₱200").</div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Minimum Purchase Amount (₱)</label>
        <input type="number" name="min_purchase_amount" class="form-control @error('min_purchase_amount') is-invalid @enderror" value="{{ $old('min_purchase_amount', 0) }}" step="0.01" min="0">
        @error('min_purchase_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">Start Date</label>
        <input type="date" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ $old('starts_at') }}">
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">End Date</label>
        <input type="date" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ $old('ends_at') }}">
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="isActiveSwitch" {{ $old('is_active', true) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="isActiveSwitch">Active</label>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('couponCodesContainer');
    const addBtn = document.getElementById('addCouponCodeBtn');
    const unifiedSwitch = document.getElementById('unifiedUsageLimitSwitch');
    const unifiedField = document.getElementById('unifiedUsageLimitField');
    const perCodeNote = document.getElementById('perCodeUsageLimitNote');

    // Unified ON: one shared Usage Limit governs the whole campaign, so each
    // code's own limit is hidden (server ignores it either way). Unified
    // OFF: the shared field hides and every code's own limit becomes editable.
    function applyUsageLimitMode() {
        const unified = !!unifiedSwitch?.checked;

        unifiedField?.classList.toggle('d-none', !unified);
        perCodeNote?.classList.toggle('d-none', unified);

        container?.querySelectorAll('.coupon-code-usage-limit-cell').forEach(function (cell) {
            cell.querySelector('.coupon-code-usage-limit-input')?.classList.toggle('d-none', unified);
            cell.querySelector('.coupon-code-pooled-note')?.classList.toggle('d-none', !unified);
        });
    }

    unifiedSwitch?.addEventListener('change', applyUsageLimitMode);
    applyUsageLimitMode();

    if (!container || !addBtn) return;

    let index = container.querySelectorAll('.coupon-code-row').length;

    addBtn.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center coupon-code-row';
        row.innerHTML =
            '<input type="hidden" name="codes[' + index + '][id]" value="">' +
            '<div class="col-md-7">' +
                '<input type="text" name="codes[' + index + '][code]" class="form-control text-uppercase" placeholder="e.g. WELCOME10">' +
            '</div>' +
            '<div class="col-md-4 coupon-code-usage-limit-cell">' +
                '<div class="input-group coupon-code-usage-limit-input">' +
                    '<span class="input-group-text">Uses</span>' +
                    '<input type="number" name="codes[' + index + '][usage_limit]" class="form-control" value="1" min="1" placeholder="1">' +
                '</div>' +
                '<div class="form-control-plaintext text-muted small coupon-code-pooled-note d-none">Shared campaign limit</div>' +
            '</div>' +
            '<div class="col-md-1 d-grid">' +
                '<button type="button" class="btn btn-danger coupon-code-remove text-white"><i data-lucide="x"></i></button>' +
            '</div>';
        container.appendChild(row);
        index++;
        applyUsageLimitMode();
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    });

    container.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.coupon-code-remove');
        if (!removeBtn) return;

        const rows = container.querySelectorAll('.coupon-code-row');
        if (rows.length > 1) {
            removeBtn.closest('.coupon-code-row').remove();
        } else {
            const row = removeBtn.closest('.coupon-code-row');
            row.querySelectorAll('input[type="text"], input[type="hidden"]').forEach(function (input) {
                input.value = '';
            });
            const usageInput = row.querySelector('input[type="number"]');
            if (usageInput) usageInput.value = 1;
        }
    });
});
</script>
