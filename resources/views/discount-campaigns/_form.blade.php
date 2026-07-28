@php
    $campaign = $campaign ?? null;
    $old = fn($key, $default = null) => old($key, $campaign?->{$key} ?? $default);
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

    <div class="col-md-6">
        <label class="form-label fw-bold">Coupon Code</label>
        <input type="text" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror" value="{{ $old('code') }}" placeholder="Leave blank for an automatic discount">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Leave blank to make this apply <strong>automatically</strong> when conditions are met — no code needed at checkout.</div>
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

    <div class="col-md-6">
        <label class="form-label fw-bold">Usage Limit</label>
        <input type="number" name="usage_limit" class="form-control @error('usage_limit') is-invalid @enderror" value="{{ $old('usage_limit') }}" min="1">
        @error('usage_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Total number of times this can be redeemed across all orders. Leave blank for unlimited.</div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="isActiveSwitch" {{ $old('is_active', true) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="isActiveSwitch">Active</label>
        </div>
    </div>
</div>
