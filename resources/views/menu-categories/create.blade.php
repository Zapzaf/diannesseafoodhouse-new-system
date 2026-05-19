@extends('layouts.app')

@section('page_title', 'Add Menu Category - Dianne\'s Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="tag"></i></div>
                            Add Menu Category
                        </h1>
                        <div class="page-header-subtitle">Create a new category for organizing menu items</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a class="btn btn-light text-primary" href="{{ route('menu-categories.index') }}">
                            <i class="me-1" data-feather="arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')
        <div class="card mb-4">
            <div class="card-header"><i class="me-1" data-feather="edit-3"></i> Category Details</div>
            <div class="card-body">
                <form action="{{ route('menu-categories.store') }}" method="POST">
                    @csrf
                    @if(!auth()->user()->isAdmin())
                    <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                    @endif
                    <div class="row">
                        @if(auth()->user()->isAdmin())
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                                <option value="">-- Select Branch --</option>
                                @foreach(\App\Models\Branch::where('is_active', true)->orderBy('name')->get() as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @endif
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="e.g. Appetizers, Main Course, Desserts" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="Optional description of this category">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('menu-categories.index') }}" class="btn btn-secondary text-light">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i data-feather="save" class="me-1"></i> Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection