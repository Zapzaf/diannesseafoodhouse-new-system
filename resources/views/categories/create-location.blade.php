@extends('layouts.app')

@section('page_title', 'Create Location')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="map-pin"></i></div>
                            Create Location
                        </h1>
                        <div class="page-header-subtitle">Add a new location for the selected branch</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a href="{{ route('categories.all') }}" class="btn btn-light text-primary">
                            <i data-feather="arrow-left" class="me-1"></i> All Location Categories
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
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
                                <i data-feather="save" class="me-1"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection
