@extends('layouts.app')
@section('page_title', 'Branches')
@section('content')
    <x-page-header title="Branches" subtitle="Manage branch details and manager assignments" icon="map-pin">
        <a href="{{ route('branches.create') }}" class="btn btn-light text-primary">
            <i data-lucide="plus-circle" class="me-1"></i> Add Branch
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><i data-lucide="git-branch" class="me-1"></i> Branch List</div>
                <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                    <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="5" {{ request('per_page') == 5 ? 'selected' : '' }}>5</option>
                        <option value="10" {{ request('per_page', 10) == 10 || request('per_page', 15) == 10 || request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i data-lucide="search" style="width: 14px; height: 14px;"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Manager</th>
                                <th>Status</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($branches as $branch)
                            <tr>
                                <td class="fw-semibold">{{ $branch->name }}</td>
                                <td>{{ $branch->address }}</td>
                                <td>{{ $branch->manager?->name ?? 'Unassigned' }}</td>
                                <td>
                                    <span class="badge {{ $branch->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="table-actions-cell text-nowrap">
                                    <a href="{{ route('branches.show', $branch) }}" class="btn btn-sm btn-outline-secondary">
                                        <i data-lucide="eye"></i>
                                    </a>
                                    <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-outline-primary">
                                        <i data-lucide="edit"></i>
                                    </a>
                                    <a href="{{ route('branches.mail-settings.edit', $branch) }}" class="btn btn-sm btn-outline-info" title="Mail Settings">
                                        <i data-lucide="mail"></i>
                                    </a>
                                    <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="d-inline"
                                          onsubmit="return confirm('Delete branch {{ addslashes($branch->name) }}? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No branches found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($branches->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $branches->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection

