@extends('layouts.app')

@section('page_title', 'Add Table - Dianne\'s Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="plus-square"></i></div>
                            Add Table
                        </h1>
                        <div class="page-header-subtitle">Create a new restaurant table for seating and order assignments.</div>
                    </div>
                    <div class="col-auto mt-4">
                        <a class="btn btn-light text-primary" href="{{ route('tables.index') }}">
                            <i class="me-1" data-feather="arrow-left"></i> Back to Tables
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
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
</main>
@endsection
