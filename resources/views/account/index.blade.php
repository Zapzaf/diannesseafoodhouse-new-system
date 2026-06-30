@extends('layouts.app')
@section('page_title', 'My Account')
@section('content')
    <x-page-header title="My Account" subtitle="Manage your profile, credentials, and assigned branch" icon="settings">
    </x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Profile Information</div>
                <div class="card-body">
                    @php
                        $existingProfilePhoto = auth()->user()->profile_photo_path
                            ? route('profile-photos.show', auth()->user())
                            : asset('assets/img/illustrations/profiles/profile-1.png');
                    @endphp
                    <form action="{{ route('account.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Profile Picture</label>
                            <input type="file" name="profile_photo" id="profilePhotoInput" class="form-control @error('profile_photo') is-invalid @enderror" accept="image/*">
                            <input type="hidden" name="profile_photo_cropped" id="profilePhotoCropped" value="{{ old('profile_photo_cropped') }}">
                            @error('profile_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('profile_photo_cropped')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">Choose an image, crop it to square, then save profile changes.</div>
                            <img id="profilePhotoPreview" src="{{ old('profile_photo_cropped') ?: $existingProfilePhoto }}" alt="Profile Preview" class="rounded-circle border mt-2" style="width:84px; height:84px; object-fit:cover;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', auth()->user()->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', auth()->user()->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password <span class="text-muted small">(leave blank to keep current)</span></label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" minlength="8">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <input type="password" name="password_confirmation" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Account Info</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Role</dt>
                        <dd class="col-sm-7">
                            <span class="badge bg-{{ ['admin'=>'danger','branch_manager'=>'warning','staff'=>'info'][auth()->user()->role] ?? 'secondary' }}">
                                {{ ucfirst(str_replace('_',' ',auth()->user()->role)) }}
                            </span>
                        </dd>
                        <dt class="col-sm-5">Branch</dt>
                        <dd class="col-sm-7">{{ auth()->user()->branch->name ?? 'All Branches' }}</dd>
                        <dt class="col-sm-5">Joined</dt>
                        <dd class="col-sm-7">{{ auth()->user()->created_at->format('M d, Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="profileCropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crop Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center cropper-modal-body">
                <img id="cropperImage" src="" alt="Crop Source" style="width:100%; max-width:100%; max-height:70vh; display:block; margin:0 auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="applyCropBtn">Apply Crop</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<style>
    #profileCropModal .cropper-modal-body {
        overflow: hidden;
    }
    #profileCropModal .cropper-container {
        max-width: 100% !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const photoInput = document.getElementById('profilePhotoInput');
    const photoCroppedInput = document.getElementById('profilePhotoCropped');
    const photoPreview = document.getElementById('profilePhotoPreview');
    const cropImage = document.getElementById('cropperImage');
    const cropModalEl = document.getElementById('profileCropModal');
    const applyCropBtn = document.getElementById('applyCropBtn');
    const cropModal = new bootstrap.Modal(cropModalEl);
    let cropper = null;

    photoInput.addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            cropImage.src = event.target.result;
            cropModal.show();
        };
        reader.readAsDataURL(file);
    });

    cropModalEl.addEventListener('shown.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
        }
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            responsive: true,
            restore: false,
            background: false,
        });
    });

    cropModalEl.addEventListener('hidden.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    });

    applyCropBtn.addEventListener('click', function () {
        if (!cropper) {
            return;
        }

        const canvas = cropper.getCroppedCanvas({
            width: 600,
            height: 600,
            imageSmoothingQuality: 'high',
        });

        const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
        photoCroppedInput.value = dataUrl;
        photoPreview.src = dataUrl;
        cropModal.hide();
    });
});
</script>
@endpush
@endsection
