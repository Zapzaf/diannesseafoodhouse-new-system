@extends('layouts.app')
@section('page_title', 'New Petty Cash Voucher')
@section('content')
    <x-page-header title="New Petty Cash Voucher (PCV)" subtitle="Log a purchase paid out of petty cash on hand" icon="plus-circle">
        <a href="{{ route('petty-cash-vouchers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Petty Cash Vouchers
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <form action="{{ route('petty-cash-vouchers.store') }}" method="POST">
            @csrf
            @include('petty-cash-vouchers._form', ['voucher' => null])
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/voucher-item-repeater.js') }}?v={{ filemtime(public_path('js/voucher-item-repeater.js')) }}"></script>
@include('petty-cash-vouchers._form-script')
@endpush
