@php
    $supplier = $supplier ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Supplier Type <span class="text-danger">*</span></label>
        <select name="type" id="supplierType" class="form-select @error('type') is-invalid @enderror" required>
            <option value="">Select Type</option>
            <option value="company" {{ old('type', $supplier?->type) === 'company' ? 'selected' : '' }}>Company</option>
            <option value="sole_proprietorship" {{ old('type', $supplier?->type) === 'sole_proprietorship' ? 'selected' : '' }}>Single Proprietorship</option>
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 supplier-type-field" data-type="company">
        <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
        <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name', $supplier?->company_name) }}">
        @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 supplier-type-field" data-type="sole_proprietorship">
        <label class="form-label fw-semibold">Business Name <span class="text-danger">*</span></label>
        <input type="text" name="business_name" class="form-control @error('business_name') is-invalid @enderror" value="{{ old('business_name', $supplier?->business_name) }}">
        @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 supplier-type-field" data-type="sole_proprietorship">
        <label class="form-label fw-semibold">Owner's Name <span class="text-danger">*</span></label>
        <input type="text" name="owner_name" class="form-control @error('owner_name') is-invalid @enderror" value="{{ old('owner_name', $supplier?->owner_name) }}">
        @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Contact Person</label>
        <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier?->contact_person) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier?->phone) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $supplier?->email) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Address</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $supplier?->address) }}">
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Notes</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $supplier?->notes) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('supplierType');
    if (!typeSelect) return;

    const fields = document.querySelectorAll('.supplier-type-field');

    function toggleFields() {
        const type = typeSelect.value;
        fields.forEach(function (field) {
            const matches = field.dataset.type === type;
            field.style.display = matches ? '' : 'none';
            const input = field.querySelector('input');
            if (input) {
                input.required = matches;
                input.disabled = !matches;
            }
        });
    }

    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
@endpush
