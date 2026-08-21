@extends('layouts.app')

@section('page_title', 'Create Location')

@section('content')
    <x-page-header title="Create Location" subtitle="Add a new location for the selected branch" icon="map-pin">
        <a href="{{ route('categories.all') }}" class="btn btn-secondary text-white">
            <i data-lucide="arrow-left" class="me-1"></i> All Location Categories
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header fw-semibold">Location Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('categories.locations.store') }}">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        @if($user->isAdmin())
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Branch</label>
                            <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                                <option value="">Select branch</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) old('branch_id', $selectedBranchId) === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @endif

                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="save" class="me-1"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
