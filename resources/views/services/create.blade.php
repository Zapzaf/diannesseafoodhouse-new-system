@extends('layouts.app')
@section('page_title', 'New Service')
@section('content')
    <x-page-header title="New Service" subtitle="Log a service expense (utilities, professional fees, repairs, etc.)" icon="plus-circle">
        <a href="{{ route('services.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Services
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            @include('services._form', ['service' => null])
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/supplier-picker.js') }}"></script>
@endpush
