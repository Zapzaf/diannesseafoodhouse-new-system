@extends('layouts.app')
@section('page_title', 'Bank Accounts')
@section('content')
    <x-page-header title="Bank Accounts" subtitle="Bank accounts used to fund disbursements" icon="landmark">
        <a href="{{ route('bank-accounts.create') }}" class="btn btn-primary">
            <i data-lucide="plus-circle" class="me-1"></i> Add Bank Account
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="list"></i> Bank Accounts</div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search bank or account name" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Bank</th>
                                <th>Account Name</th>
                                <th>Account Number</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bankAccounts as $bankAccount)
                            <tr>
                                <td class="fw-semibold">{{ $bankAccount->bank_name }}</td>
                                <td>{{ $bankAccount->account_name }}</td>
                                <td>{{ $bankAccount->account_number }}</td>
                                <td class="text-muted small">{{ $bankAccount->branch?->name ?? 'All Branches' }}</td>
                                <td>
                                    <span class="badge {{ $bankAccount->is_active ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' }}">
                                        {{ $bankAccount->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="table-actions-cell text-nowrap">
                                    <form action="{{ route('bank-accounts.toggle-active', $bankAccount) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            {{ $bankAccount->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No bank accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($bankAccounts->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $bankAccounts->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
