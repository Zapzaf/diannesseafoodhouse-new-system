@extends('layouts.app')

@section('page_title', 'Add Table - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Add Table" subtitle="Create a new restaurant table for seating and order assignments." icon="plus-square">
        <a class="btn btn-primary" href="{{ route('tables.index') }}">
            <i class="me-1" data-lucide="arrow-left"></i> Back to Tables
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('tables.store') }}" method="POST">
                    @csrf
                    @include('tables.form')
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('tables.index') }}" class="btn btn-secondary text-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Table</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
