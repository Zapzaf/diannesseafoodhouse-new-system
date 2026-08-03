@extends('layouts.app')
@section('page_title', 'Edit Service')
@section('content')
    <x-page-header title="Edit Service" subtitle="{{ $service->ref_no }}" icon="edit-2">
        <a href="{{ route('services.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Services
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('services.update', $service) }}" method="POST">
            @csrf
            @method('PUT')
            @include('services._form', ['service' => $service])
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/supplier-picker.js') }}"></script>
@endpush
