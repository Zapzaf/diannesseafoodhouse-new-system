@extends('layouts.app')

@section('page_title', 'Edit Menu Category - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Edit Menu Category" :subtitle="$menuCategory->name" icon="tag">
        <a class="btn btn-primary" href="{{ route('menu-categories.index') }}">
            <i class="me-1" data-lucide="arrow-left"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')
        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-lucide="edit-3"></i> Category Details</div>
            <div class="card-body">
                <form action="{{ route('menu-categories.update', $menuCategory) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Branch</label>
                            <input type="text" class="form-control" value="{{ $menuCategory->branch->name ?? '—' }}" readonly disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $menuCategory->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $menuCategory->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('menu-categories.index') }}" class="btn btn-secondary text-light">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i data-lucide="save" class="me-1"></i> Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection