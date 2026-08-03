@extends('layouts.app')
@section('page_title', 'Add Bank Account')
@section('content')
    <x-page-header title="Add Bank Account" subtitle="Register a bank account used to fund disbursements" icon="plus-circle">
        <a href="{{ route('bank-accounts.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-4 shadow-sm" style="max-width: 640px;">
            <div class="card-body p-0">
                <form action="{{ route('bank-accounts.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror" value="{{ old('bank_name') }}" placeholder="e.g. BDO, BPI, Metrobank" required>
                        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control @error('account_name') is-invalid @enderror" value="{{ old('account_name') }}" required>
                        @error('account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_number" class="form-control @error('account_number') is-invalid @enderror" value="{{ old('account_number') }}" required>
                        @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if(auth()->user()?->isAdmin())
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Branch</label>
                        <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                            <option value="">All Branches</option>
                            @foreach(\App\Models\Branch::query()->where('is_active', true)->orderBy('name')->get() as $branch)
                            <option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @endif
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('bank-accounts.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Bank Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
