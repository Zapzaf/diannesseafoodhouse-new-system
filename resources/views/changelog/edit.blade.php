@extends('layouts.app')
@section('page_title', 'Edit Update - Dianne\'s Seafood House')
@section('content')
    <x-page-header title="Edit Update" :subtitle="$update->title" icon="edit-2">
        <a href="{{ route('changelog.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-4 shadow-sm">
            <div class="card-body p-0">
                <form action="{{ route('changelog.update', $update) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    @include('changelog._form', ['update' => $update])

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('changelog.index') }}" class="btn btn-secondary text-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
