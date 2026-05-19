@extends('layouts.app')

@section('page_title', 'Create Category')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="folder"></i></div>
                            Create Category
                        </h1>
                        <div class="page-header-subtitle">Category must belong to a location (Location &gt; Category)</div>
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
            <div class="card-header fw-semibold">Category Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('categories.items.store') }}">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Category Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Location</label>
                            <select name="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                                <option value="">Select location</option>
                                @foreach($locationOptions as $locationOption)
                                <option value="{{ $locationOption->id }}" {{ (string) old('location_id', $selectedLocationId) === (string) $locationOption->id ? 'selected' : '' }}>
                                    {{ $locationOption->name }} ({{ $locationOption->branch?->name ?? 'N/A' }})
                                </option>
                                @endforeach
                            </select>
                            @error('location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

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
