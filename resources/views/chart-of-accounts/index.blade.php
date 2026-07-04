@extends('layouts.app')
@section('page_title', 'Chart of Accounts')
@section('content')
    <x-page-header title="Chart of Accounts" subtitle="Debit expense and credit liability accounts used across the Purchase & Disbursement Book" icon="book-text">
        <a href="{{ route('chart-of-accounts.create') }}" class="btn btn-primary">
            <i data-lucide="plus-circle" class="me-1"></i> Add Account
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div><i class="me-1" data-lucide="list"></i> Accounts</div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search account name" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="debit_expense" {{ request('type') === 'debit_expense' ? 'selected' : '' }}>Debit / Expense</option>
                            <option value="credit_liability" {{ request('type') === 'credit_liability' ? 'selected' : '' }}>Credit / Liability</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $account)
                            <tr>
                                <td>{{ $account->code ?? '—' }}</td>
                                <td class="fw-semibold">{{ $account->name }}</td>
                                <td>
                                    <span class="badge {{ $account->type === 'debit_expense' ? 'bg-primary-soft text-primary' : 'bg-warning-soft text-warning' }}">
                                        {{ $account->type === 'debit_expense' ? 'Debit / Expense' : 'Credit / Liability' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $account->is_active ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' }}">
                                        {{ $account->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="table-actions-cell text-nowrap">
                                    <form action="{{ route('chart-of-accounts.toggle-active', $account) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($accounts->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $accounts->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
