@extends('layouts.app')
@section('page_title', 'Settings - Dianne Seafood House')
@section('content')
<main>
<x-page-header title="Settings" subtitle="System overview and configuration" icon="settings">
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    {{-- System Info --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i data-feather="map-pin" class="text-primary" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Branches</div>
                        <div class="fs-4 fw-bold">{{ $totalBranches }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i data-feather="users" class="text-success" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Users</div>
                        <div class="fs-4 fw-bold">{{ $totalUsers }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i data-feather="package" class="text-info" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Inventory Items</div>
                        <div class="fs-4 fw-bold">{{ $totalItems }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i data-feather="alert-triangle" class="text-warning" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Low Stock Items</div>
                        <div class="fs-4 fw-bold text-warning">{{ $lowStockItems }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Links --}}
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold"><i data-feather="link" class="me-1"></i> Quick Actions</div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('branches.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-feather="map-pin" class="text-primary" style="width:18px;height:18px;"></i>
                            Manage Branches
                        </a>
                        <a href="{{ route('users.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-feather="users" class="text-primary" style="width:18px;height:18px;"></i>
                            Manage Users
                        </a>
                        @endif
                        <a href="{{ route('categories.index', 'inventory') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-feather="folder" class="text-primary" style="width:18px;height:18px;"></i>
                            Manage Categories
                        </a>
                        <a href="{{ route('items.low-stock') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-feather="alert-triangle" class="text-warning" style="width:18px;height:18px;"></i>
                            View Low Stock Alerts
                        </a>
                        <a href="{{ route('account.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-feather="settings" class="text-primary" style="width:18px;height:18px;"></i>
                            Account Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold"><i data-feather="info" class="me-1"></i> System Information</div>
                <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted fw-semibold" style="width:180px;">Application</td>
                                <td>Dianne's Seafood House System</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Framework</td>
                                <td>Laravel {{ app()->version() }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">PHP Version</td>
                                <td>{{ PHP_VERSION }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Environment</td>
                                <td><span class="badge-status {{ app()->isProduction() ? 'badge-active' : 'badge-pending' }}">{{ strtoupper(app()->environment()) }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Logged in as</td>
                                <td>{{ auth()->user()->name }} <span class="text-muted small">({{ ucfirst(auth()->user()->role) }})</span></td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>
@endsection
