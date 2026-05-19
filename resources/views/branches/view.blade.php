@extends('layouts.app')
@section('page_title', 'Branch — ' . $branch->name)
@section('content')
<main>
    <x-page-header :title="$branch->name" subtitle="Branch details and assigned resources" icon="map-pin">
        <a href="{{ route('branches.edit', $branch) }}" class="btn btn-light text-primary">
            <i data-feather="edit" class="me-1"></i> Edit
        </a>
        <a href="{{ route('branches.index') }}" class="btn btn-light text-secondary">
            <i data-feather="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        {{-- Branch info card --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold"><i data-feather="info" class="me-1"></i> Branch Information</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9">{{ $branch->name }}</dd>

                    <dt class="col-sm-3">Address</dt>
                    <dd class="col-sm-9">{{ $branch->address }}</dd>

                    <dt class="col-sm-3">Manager</dt>
                    <dd class="col-sm-9">{{ $branch->manager?->name ?? '— Unassigned —' }}</dd>

                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9">
                        <span class="badge {{ $branch->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $branch->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </dd>

                    <dt class="col-sm-3">Created</dt>
                    <dd class="col-sm-9 text-muted">{{ $branch->created_at->format('M d, Y H:i') }}</dd>
                </dl>
            </div>
        </div>

        {{-- Assigned users --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold"><i data-feather="users" class="me-1"></i> Assigned Users ({{ $branch->users->count() }})</div>
            <div class="card-body">
                <div class="table-responsive mt-3">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($branch->users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td class="text-muted">{{ $user->email }}</td>
                                <td><span class="badge bg-secondary text-uppercase">{{ $user->role }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No users assigned.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Locations & Categories --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold"><i data-feather="layers" class="me-1"></i> Locations & Categories ({{ $branch->locations->count() }})</div>
            <div class="card-body">
                @forelse($branch->locations as $location)
                <div class="mb-3">
                    <div class="fw-semibold text-primary mb-1">
                        <i data-feather="map-pin" class="me-1" style="width:14px;height:14px"></i> {{ $location->name }}
                    </div>
                    @if($location->categories->isNotEmpty())
                    <ul class="list-unstyled ms-3 mb-0">
                        @foreach($location->categories as $category)
                        <li class="text-muted small">
                            <i data-feather="tag" class="me-1" style="width:12px;height:12px"></i> {{ $category->name }}
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-muted small ms-3 mb-0">No categories.</p>
                    @endif
                </div>
                @empty
                <p class="text-muted mb-0">No locations found for this branch.</p>
                @endforelse
            </div>
        </div>

        {{-- Danger zone --}}
        <div class="card shadow-sm border-danger mb-4">
            <div class="card-header fw-semibold text-danger"><i data-feather="alert-triangle" class="me-1"></i> Danger Zone</div>
            <div class="card-body">
                <p class="text-muted mb-3">Permanently delete this branch. This action cannot be undone.</p>
                <form method="POST" action="{{ route('branches.destroy', $branch) }}"
                      onsubmit="return confirm('Delete branch \'{{ addslashes($branch->name) }}\'? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i data-feather="trash-2" class="me-1"></i> Delete Branch
                    </button>
                </form>
            </div>
        </div>

    </div>
</main>
@endsection
