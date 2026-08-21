@extends('layouts.app')
@section('page_title', 'Add User')
@section('content')
    <x-page-header title="Add User" subtitle="Create a staff account with the correct role and branch scope" icon="user-plus">
        <a href="{{ route('users.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Users
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')
        <div class="card p-4 shadow-sm">
            <div class="card-body p-0">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-primary">
                    <i data-lucide="user" style="width: 20px; height: 20px;"></i>
                    <span>User Account Details</span>
                </h5>
                <form action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profile Picture</label>
                        <input type="file" name="profile_photo" id="profilePhotoInput" class="form-control @error('profile_photo') is-invalid @enderror" accept="image/*">
                        <input type="hidden" name="profile_photo_cropped" id="profilePhotoCropped" value="{{ old('profile_photo_cropped') }}">
                        @error('profile_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('profile_photo_cropped')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text text-muted">Choose a photo, crop it to a square, then save the user.</div>
                        <img id="profilePhotoPreview" src="{{ old('profile_photo_cropped') ?: asset('assets/img/illustrations/profiles/profile-1.png') }}" alt="Profile Preview" class="rounded-circle border mt-2" style="width:72px; height:72px; object-fit:cover;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        @if(auth()->user()->isBranchManager())
                            <input type="hidden" name="role" value="staff">
                            <input type="text" class="form-control" value="Staff" readonly>
                            <div class="form-text text-muted">Branch managers can only create staff users.</div>
                        @else
                        <select name="role" class="form-select @error('role') is-invalid @enderror" id="roleSelect" required>
                            <option value="staff" {{ old('role','staff') == 'staff' ? 'selected' : '' }}>Staff</option>
                            <option value="branch_manager" {{ old('role') == 'branch_manager' ? 'selected' : '' }}>Branch Manager</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @endif
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3" id="branchField">
                        <label class="form-label fw-semibold">Branch</label>
                        @if(auth()->user()->isBranchManager())
                            @php $assignedBranch = $branches->first(); @endphp
                            <input type="hidden" name="branch_id" value="{{ $assignedBranch?->id }}">
                            <input type="text" class="form-control" value="{{ $assignedBranch?->name ?? 'Unassigned' }}" readonly>
                        @else
                            <select name="branch_id" class="form-select">
                                <option value="">Unassigned</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary text-white px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                            <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                            <span>Create User</span>
                        </button>
                    </div>
                </form>
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
@endsection

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
    const roleSelect = document.getElementById('roleSelect');
    const branchField = document.getElementById('branchField');
    const photoInput = document.getElementById('profilePhotoInput');
    const photoCroppedInput = document.getElementById('profilePhotoCropped');
    const photoPreview = document.getElementById('profilePhotoPreview');
    const cropImage = document.getElementById('cropperImage');
    const cropModalEl = document.getElementById('profileCropModal');
    const applyCropBtn = document.getElementById('applyCropBtn');
    const cropModal = new bootstrap.Modal(cropModalEl);
    let cropper = null;

    function updateBranchVisibility() {
        if (!roleSelect || !branchField) {
            return;
        }
        branchField.style.display = roleSelect.value === 'admin' ? 'none' : '';
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', updateBranchVisibility);
        updateBranchVisibility();
    }

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
