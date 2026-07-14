@extends('layouts.app')
@section('page_title', 'Settings - Dianne Seafood House')
@section('content')
<x-page-header title="Settings" subtitle="System overview and configuration" icon="settings">
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    {{-- System Info --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3" style="background-color: rgba(240, 124, 89, 0.1) !important;">
                        <i data-lucide="map-pin" class="text-primary" style="width:24px;height:24px;"></i>
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
                        <i data-lucide="users" class="text-success" style="width:24px;height:24px;"></i>
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
                        <i data-lucide="package" class="text-info" style="width:24px;height:24px;"></i>
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
                        <i data-lucide="alert-triangle" class="text-warning" style="width:24px;height:24px;"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Low Stock Items</div>
                        <div class="fs-4 fw-bold text-warning">{{ $lowStockItems }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i data-lucide="sliders" class="me-1"></i> Branch Settings</span>
                    @if($selectedBranch)
                    <span class="text-muted small">Billing, receipt, and guest discount behavior for {{ $selectedBranch->name }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($selectedBranch)
                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="border rounded p-4 h-100 bg-light">
                                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                    <div>
                                        <div class="text-muted small mb-1">Selected Branch</div>
                                        <div class="fs-5 fw-bold">{{ $selectedBranch->name }}</div>
                                        <div class="text-muted small">{{ $selectedBranch->address }}</div>
                                    </div>
                                    <span class="badge-status {{ $selectedBranch->is_active ? 'badge-active' : 'badge-expired' }}">
                                        {{ $selectedBranch->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                    </span>
                                </div>

                                @if(auth()->user()->isAdmin())
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Switch Branch</label>
                                    <select class="form-select" onchange="window.location = '{{ route('settings.show') }}?branch_id=' + this.value">
                                        @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (int) $selectedBranchId === (int) $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif

                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <div class="border rounded p-3 h-100 bg-white">
                                            <div class="text-muted small">Manager</div>
                                            <div class="fw-semibold">{{ $selectedBranch->manager?->name ?? 'Unassigned' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-3 h-100 bg-white">
                                            <div class="text-muted small">Users</div>
                                            <div class="fw-semibold">{{ number_format($selectedBranch->users_count ?? 0) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-3 h-100 bg-white">
                                            <div class="text-muted small">Locations</div>
                                            <div class="fw-semibold">{{ number_format($selectedBranch->locations_count ?? 0) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-3 h-100 bg-white">
                                            <div class="text-muted small">Inventory Items</div>
                                            <div class="fw-semibold">{{ number_format($selectedBranch->items_count ?? 0) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 fw-semibold">Current Billing Rules</div>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge {{ $selectedBranch->vat_enabled ? 'bg-success' : 'bg-secondary' }}">VAT {{ $selectedBranch->vat_enabled ? 'On' : 'Off' }}</span>
                                    <span class="badge {{ $selectedBranch->pwd_discount_enabled ? 'bg-primary' : 'bg-secondary' }}">PWD {{ $selectedBranch->pwd_discount_enabled ? 'On' : 'Off' }}</span>
                                    <span class="badge {{ $selectedBranch->senior_discount_enabled ? 'bg-info text-white' : 'bg-secondary' }}">Senior {{ $selectedBranch->senior_discount_enabled ? 'On' : 'Off' }}</span>
                                </div>

                                <div class="small text-muted">
                                    Changes here immediately affect menu order billing preview, saved totals, and how order summaries present VAT and guest discounts for this branch.
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-8">
                            <form method="POST" action="{{ route('settings.branch.update') }}" class="row g-4">
                                @csrf
                                @method('PUT')

                                <input type="hidden" name="branch_id" value="{{ $selectedBranch->id }}">

                                <div class="col-12">
                                    <div class="border rounded p-4">
                                        <div class="fw-semibold mb-1">Compliance and Receipt Details</div>
                                        <div class="text-muted small mb-4">These details appear in receipts and support tax-compliant branch billing.</div>

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Contact Number</label>
                                                <input type="text" name="contact_number" class="form-control @error('contact_number') is-invalid @enderror" value="{{ old('contact_number', $selectedBranch->contact_number) }}" placeholder="Branch contact number">
                                                @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">TIN Number</label>
                                                <input type="text" name="tin_number" class="form-control @error('tin_number') is-invalid @enderror" value="{{ old('tin_number', $selectedBranch->tin_number) }}" placeholder="Tax identification number">
                                                @error('tin_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded p-4">
                                        <div class="fw-semibold mb-1">Tax Settings</div>
                                        <div class="text-muted small mb-4">Controls whether this branch shows VAT in menu-order billing summaries and applies VAT to saved totals.</div>

                                        <div class="row g-3">
                                            <div class="col-lg-7">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="vatEnabled" name="vat_enabled" value="1" {{ old('vat_enabled', $selectedBranch->vat_enabled) ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-semibold" for="vatEnabled">Enable VAT</label>
                                                </div>
                                                <div class="small text-muted">When disabled, VAT context is removed from billing previews and summaries for this branch.</div>
                                            </div>
                                            <div class="col-lg-5">
                                                <label class="form-label fw-semibold">VAT Percentage</label>
                                                <div class="input-group">
                                                    <input type="number" step="0.01" min="0" max="100" id="vatPercentage" name="vat_percentage" class="form-control @error('vat_percentage') is-invalid @enderror" value="{{ old('vat_percentage', number_format((float) ($selectedBranch->vat_percentage ?? 12), 2, '.', '')) }}">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                @error('vat_percentage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded p-4">
                                        <div class="fw-semibold mb-1">Guest Discount Controls</div>
                                        <div class="text-muted small mb-4">Controls whether PWD and Senior Citizen discount inputs appear in menu orders and whether the related billing summary rows are shown.</div>

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" role="switch" id="pwdEnabled" name="pwd_discount_enabled" value="1" {{ old('pwd_discount_enabled', $selectedBranch->pwd_discount_enabled) ? 'checked' : '' }}>
                                                        <label class="form-check-label fw-semibold" for="pwdEnabled">Enable PWD Discount</label>
                                                    </div>
                                                    <div class="small text-muted">Allows PWD pax counting, ID capture, and discount context in menu order billing summary.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" role="switch" id="seniorEnabled" name="senior_discount_enabled" value="1" {{ old('senior_discount_enabled', $selectedBranch->senior_discount_enabled) ? 'checked' : '' }}>
                                                        <label class="form-check-label fw-semibold" for="seniorEnabled">Enable Senior Discount</label>
                                                    </div>
                                                    <div class="small text-muted">Allows senior pax counting, ID capture, and discount context in menu order billing summary.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-light border mb-0">
                                        <div class="fw-semibold mb-1">What will change in menu orders?</div>
                                        <ul class="mb-0 ps-3 small text-muted">
                                            <li>VAT disabled: no VAT row in billing preview and saved summary for this branch.</li>
                                            <li>PWD or Senior disabled: their pax inputs and discount details are hidden, and their discounts are not applied.</li>
                                            <li>Totals are still computed on the server to match the branch settings exactly at save time.</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i data-lucide="save" class="me-1"></i> Save Branch Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @else
                    <div class="text-muted">No active branch available for settings.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->isAdmin())
    {{-- Appearance --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i data-lucide="palette" class="me-1"></i> Appearance</div>
        <div class="card-body">
            <form action="{{ route('settings.appearance.update') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sidebar Color — Light Mode</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" name="sidebar_bg_light" class="form-control form-control-color @error('sidebar_bg_light') is-invalid @enderror"
                               value="{{ old('sidebar_bg_light', $sidebarBgLight) }}" title="Sidebar background in light mode">
                        <code class="text-muted small">{{ $sidebarBgLight }}</code>
                    </div>
                    @error('sidebar_bg_light')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sidebar Color — Dark Mode</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" name="sidebar_bg_dark" class="form-control form-control-color @error('sidebar_bg_dark') is-invalid @enderror"
                               value="{{ old('sidebar_bg_dark', $sidebarBgDark) }}" title="Sidebar background in dark mode">
                        <code class="text-muted small">{{ $sidebarBgDark }}</code>
                    </div>
                    @error('sidebar_bg_dark')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Appearance</button>
                    <button type="submit" name="reset" value="1" class="btn btn-outline-secondary"
                            onclick="return confirm('Reset sidebar colors to the system defaults?')">Reset to Defaults</button>
                </div>
                <div class="col-12">
                    <div class="form-text">Applies to everyone. Each theme keeps its own sidebar color.</div>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Quick Links --}}
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold"><i data-lucide="link" class="me-1"></i> Quick Actions</div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('branches.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-lucide="map-pin" class="text-primary" style="width:18px;height:18px;"></i>
                            Manage Branches
                        </a>
                        <a href="{{ route('users.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-lucide="users" class="text-primary" style="width:18px;height:18px;"></i>
                            Manage Users
                        </a>
                        @endif
                        <a href="{{ route('categories.index', 'inventory') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-lucide="folder" class="text-primary" style="width:18px;height:18px;"></i>
                            Manage Categories
                        </a>
                        <a href="{{ route('items.low-stock') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-lucide="alert-triangle" class="text-warning" style="width:18px;height:18px;"></i>
                            View Low Stock Alerts
                        </a>
                        <a href="{{ route('account.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i data-lucide="settings" class="text-primary" style="width:18px;height:18px;"></i>
                            Account Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold"><i data-lucide="info" class="me-1"></i> System Information</div>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const vatEnabled = document.getElementById('vatEnabled');
    const vatPercentage = document.getElementById('vatPercentage');

    if (!vatEnabled || !vatPercentage) {
        return;
    }

    function syncVatInput() {
        vatPercentage.disabled = !vatEnabled.checked;
    }

    vatEnabled.addEventListener('change', syncVatInput);
    syncVatInput();
});
</script>
@endpush
