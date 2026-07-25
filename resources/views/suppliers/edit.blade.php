@extends('layouts.app')
@section('page_title', 'Edit Supplier')
@section('content')
    <x-page-header title="Edit Supplier" :subtitle="$supplier->name" icon="edit">
        <a href="{{ route('suppliers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Suppliers
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('suppliers.update', $supplier) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('suppliers._form')
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
