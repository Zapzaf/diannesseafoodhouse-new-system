{{-- Branch picker for finance forms: only rendered for admins browsing
     "All Branches" — otherwise records are stamped with the active branch. --}}
@php
    $showBranchPicker = auth()->user()?->isAdmin() && ! session('selected_branch_id');
    $pickerBranches = $showBranchPicker
        ? \App\Models\Branch::query()->where('is_active', true)->orderBy('name')->get()
        : collect();
    $currentBranchId = old('branch_id', $voucher->branch_id ?? null);
@endphp
@if($showBranchPicker)
<div class="col-md-{{ $colWidth ?? 4 }}">
    <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
    <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" {{ ($pickerRequired ?? true) ? 'required' : '' }}>
        <option value="">Select Branch</option>
        @foreach($pickerBranches as $pickerBranch)
        <option value="{{ $pickerBranch->id }}" @selected((string) $currentBranchId === (string) $pickerBranch->id)>{{ $pickerBranch->name }}</option>
        @endforeach
    </select>
    @if(!empty($pickerHint))<div class="form-text">{{ $pickerHint }}</div>@endif
    @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
@endif
