@extends('layouts.app')
@section('page_title', 'Edit Petty Cash Voucher')
@section('content')
    <x-page-header title="Edit Petty Cash Voucher (PCV)" subtitle="{{ $pettyCashVoucher->pcv_no }}" icon="edit-2">
        <a href="{{ route('petty-cash-vouchers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Petty Cash Vouchers
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('petty-cash-vouchers.update', $pettyCashVoucher) }}" method="POST">
            @csrf
            @method('PUT')
            @include('petty-cash-vouchers._form', ['voucher' => $pettyCashVoucher])
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/voucher-item-repeater.js') }}"></script>
@include('petty-cash-vouchers._form-script')
@endpush
