@extends('layouts.app')
@section('page_title', 'Edit Discount Campaign - Dianne\'s Seafood House')
@section('content')
<x-page-header title="Edit Discount Campaign" :subtitle="$campaign->name" icon="tag">
    <a class="btn btn-primary" href="{{ route('discount-campaigns.index') }}">
        <i data-lucide="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('discount-campaigns.update', $campaign) }}">
                @csrf @method('PUT')
                @include('discount-campaigns._form')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('discount-campaigns.index') }}" class="btn btn-secondary text-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" class="me-1"></i> Update Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
