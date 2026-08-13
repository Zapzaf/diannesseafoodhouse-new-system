@php
    $update = $update ?? null;
    // $update's attribute is used as the fallback default when there's no
    // old() session input to redisplay. released_at is cast to a Carbon
    // instance on the model, and echoing that raw object stringifies it as
    // "Y-m-d H:i:s" — invalid for <input type="date">, which silently blanks
    // itself when its value doesn't match "Y-m-d". Normalize any Carbon
    // instance to a plain date string here so every date-cast field stays
    // safe, not just released_at.
    $old = function (string $key, $default = null) use ($update) {
        $modelValue = $update?->{$key};

        if ($modelValue instanceof \Carbon\Carbon) {
            $modelValue = $modelValue->toDateString();
        }

        return old($key, $modelValue ?? $default);
    };
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="mb-3">
            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ $old('title') }}" placeholder="e.g. Multiple Coupon Codes per Campaign" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
            <textarea name="description" rows="6" class="form-control @error('description') is-invalid @enderror" placeholder="What changed, and why it matters to users..." required>{{ $old('description') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Update Type <span class="text-danger">*</span></label>
                <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                    <option value="">Select Type</option>
                    @foreach(\App\Models\ChangelogUpdate::TYPES as $value => $meta)
                    <option value="{{ $value }}" {{ (string) $old('type') === $value ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                <input type="date" name="released_at" class="form-control @error('released_at') is-invalid @enderror" value="{{ $old('released_at', now()->toDateString()) }}" required>
                @error('released_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" role="switch" name="is_published" value="1" id="isPublishedSwitch" {{ $old('is_published', $update?->is_published ?? true) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="isPublishedSwitch">Published</label>
            <div class="form-text">Unpublished updates are saved as a draft — only admins can see them until this is turned on.</div>
        </div>
    </div>

    <div class="col-lg-4">
        <label class="form-label fw-bold">Thumbnail Image</label>
        <div class="border rounded-3 p-3 text-center h-auto">
            <div class="d-flex align-items-center justify-content-center mb-3 rounded-3 overflow-hidden mx-auto"
                 style="aspect-ratio: 16 / 10; max-width: 320px; background: rgba(var(--primary-color-rgb), 0.06);">
                <img id="imagePreviewImg"
                     src="{{ $update?->image_url ?? '' }}"
                     alt="Thumbnail preview"
                     class="{{ $update?->image_url ? '' : 'd-none' }}"
                     style="width: 100%; height: 100%; object-fit: cover;">
                <div id="imagePlaceholder" class="text-muted {{ $update?->image_url ? 'd-none' : '' }}">
                    <i data-lucide="image" style="width: 36px; height: 36px; opacity: .45;"></i>
                    <div class="small mt-2">No image yet</div>
                </div>
            </div>
            <div id="imageStatus" class="small text-muted mb-2">
                {{ $update?->image_url ? 'Current image' : 'Upload a thumbnail to feature with this update' }}
            </div>
            <input type="file" class="form-control @error('image') is-invalid @enderror"
                   name="image" id="imageInput" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
            @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">JPEG, PNG, GIF or WebP, max 2MB. Leave blank to keep the current image.</div>

            @if($update?->image_url)
            <div class="form-check mt-2 text-start">
                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImageCheck">
                <label class="form-check-label small text-danger" for="removeImageCheck">Remove current image</label>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('imageInput')?.addEventListener('change', function (e) {
    const file = e.target.files[0];
    const img = document.getElementById('imagePreviewImg');
    const placeholder = document.getElementById('imagePlaceholder');
    const status = document.getElementById('imageStatus');
    const removeCheck = document.getElementById('removeImageCheck');

    if (file) {
        const reader = new FileReader();
        reader.onload = function (ev) {
            img.src = ev.target.result;
            img.classList.remove('d-none');
            placeholder.classList.add('d-none');
            status.textContent = 'New image preview — saved when you submit';
        };
        reader.readAsDataURL(file);
        if (removeCheck) removeCheck.checked = false;
    }
});
</script>
@endpush
