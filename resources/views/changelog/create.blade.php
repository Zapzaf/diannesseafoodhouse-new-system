@extends('layouts.app')
@section('page_title', 'New Update - Dianne\'s Seafood House')
@section('content')
    <x-page-header title="New Update" subtitle="Publish a system update for everyone to see" icon="plus-circle">
        <a href="{{ route('changelog.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-4 shadow-sm">
            <div class="card-body p-0">
                <form action="{{ route('changelog.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('changelog._form', ['update' => null])

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('changelog.index') }}" class="btn btn-secondary text-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="me-1"></i> Publish Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
