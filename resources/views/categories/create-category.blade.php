@extends('layouts.app')

@section('page_title', 'Create Category')

@section('content')
    <x-page-header title="Create Category" subtitle="Category must belong to a location (Location &gt; Category)" icon="folder">
        <a href="{{ route('categories.all') }}" class="btn btn-secondary text-white">
            <i data-lucide="arrow-left" class="me-1"></i> All Location Categories
        </a>
    </x-page-header>

    <div class="container-xl px-4">
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
                                <i data-lucide="save" class="me-1"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
