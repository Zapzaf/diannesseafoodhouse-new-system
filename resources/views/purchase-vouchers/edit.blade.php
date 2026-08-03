@extends('layouts.app')
@section('page_title', 'Edit Purchase Voucher')
@section('content')
    <x-page-header title="Edit Purchase Voucher (APV)" subtitle="{{ $purchaseVoucher->apv_no }}" icon="edit-2">
        <a href="{{ route('purchase-vouchers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Purchase Vouchers
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('purchase-vouchers.update', $purchaseVoucher) }}" method="POST">
            @csrf
            @method('PUT')
            @include('purchase-vouchers._form', ['voucher' => $purchaseVoucher])
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/voucher-item-repeater.js') }}"></script>
<script src="{{ asset('js/supplier-picker.js') }}"></script>
@include('purchase-vouchers._form-script')
@endpush
