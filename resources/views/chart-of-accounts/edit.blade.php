@extends('layouts.app')
@section('page_title', 'Edit Account')
@section('content')
    <x-page-header title="Edit Account" :subtitle="$account->name" icon="edit-2">
        <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card p-4 shadow-sm" style="max-width: 640px;">
            <div class="card-body p-0">
                <form action="{{ route('chart-of-accounts.update', $account) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $account->name) }}" placeholder="e.g. Accounts Payable - Pacific Fresh Catch Trading" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Code</label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $account->code) }}" placeholder="Optional">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">Select Type</option>
                            <option value="debit_expense" {{ old('type', $account->type) === 'debit_expense' ? 'selected' : '' }}>Debit / Expense (cost account)</option>
                            <option value="debit_asset" {{ old('type', $account->type) === 'debit_asset' ? 'selected' : '' }}>Debit / Asset (e.g. Fixed Asset, Advances)</option>
                            <option value="credit_liability" {{ old('type', $account->type) === 'credit_liability' ? 'selected' : '' }}>Credit / Liability (e.g. Accounts Payable)</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
