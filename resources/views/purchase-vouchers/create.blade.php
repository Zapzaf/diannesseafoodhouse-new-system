@extends('layouts.app')
@section('page_title', 'New Purchase Voucher')
@section('content')
    <x-page-header title="New Purchase Voucher (APV)" subtitle="Log a credit purchase that has not been paid yet" icon="plus-circle">
        <a href="{{ route('purchase-vouchers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Purchase Vouchers
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('purchase-vouchers.store') }}" method="POST">
            @csrf
            @include('purchase-vouchers._form', ['voucher' => null])
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/voucher-item-repeater.js') }}"></script>
<script src="{{ asset('js/supplier-picker.js') }}"></script>
@include('purchase-vouchers._form-script')
@endpush
