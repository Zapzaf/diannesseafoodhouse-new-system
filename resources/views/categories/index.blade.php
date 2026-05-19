@extends('layouts.app')

@section('page_title', 'All Location Categories')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="folder"></i></div>
                            All Location Categories
                        </h1>
                        <div class="page-header-subtitle">Tree structure: Location &gt; Category</div>
                    </div>
                    <div class="col-auto mt-4 d-flex gap-2">
                        <a href="{{ route('categories.locations.create') }}" class="btn btn-light text-primary">
                            <i data-feather="map-pin" class="me-1"></i> Create Location
                        </a>
                        <a href="{{ route('categories.items.create') }}" class="btn btn-light text-primary">
                            <i data-feather="folder-plus" class="me-1"></i> Create Category
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>Location &gt; Category Tree</div>
                <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                    <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="5" {{ request('per_page') == 5 ? 'selected' : '' }}>5</option>
                        <option value="10" {{ request('per_page', 10) == 10 || request('per_page', 12) == 10 || request('per_page', 15) == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i data-feather="search" style="width: 14px; height: 14px;"></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="min-width: 260px;">Hierarchy</th>
                                <th>Branch</th>
                                <th>Type</th>
                                <th class="text-end" style="min-width: 230px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($locations as $location)
                            <tr class="table-light">
                                <td class="fw-semibold">
                                    <i data-feather="map-pin" style="width:14px;height:14px;" class="me-1"></i>
                                    {{ $location->name }}
                                </td>
                                <td>{{ $location->branch?->name ?? 'N/A' }}</td>
                                <td><span class="badge bg-secondary">Location</span></td>
                                <td class="text-end">
                                    <a href="{{ route('categories.items.create', ['location_id' => $location->id]) }}" class="btn btn-sm btn-primary" title="Add Category" aria-label="Add Category">
                                        <i data-feather="plus-circle" style="width:14px;height:14px;"></i>
                                    </a>
                                    @if($user->isAdmin())
                                    <button type="button" class="btn btn-sm btn-warning edit-location-btn" data-id="{{ $location->id }}" data-name="{{ $location->name }}" title="Edit Location" aria-label="Edit Location">
                                        <i data-feather="edit" style="width:14px;height:14px;"></i>
                                    </button>
                                    <form method="POST" action="{{ route('categories.locations.destroy', $location) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this location?')" title="Delete Location" aria-label="Delete Location">
                                            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>

                            @forelse($location->categories as $category)
                            <tr>
                                <td class="ps-4">
                                    <span class="text-muted me-2">└─</span>
                                    <i data-feather="folder" style="width:14px;height:14px;" class="me-1"></i>
                                    {{ $category->name }}
                                </td>
                                <td>{{ $category->branch?->name ?? $location->branch?->name ?? 'N/A' }}</td>
                                <td><span class="badge bg-info text-white">Category</span></td>
                                <td class="text-end">
                                    <a href="{{ route('categories.view', [$location, 'category_id' => $category->id]) }}" class="btn btn-sm btn-info text-white" title="View Category Items" aria-label="View Category Items">
                                        <i data-feather="eye" style="width:14px;height:14px;"></i>
                                    </a>
                                    @if($user->isAdmin())
                                    <button type="button" class="btn btn-sm btn-warning edit-category-btn" data-id="{{ $category->id }}" data-name="{{ $category->name }}" data-location-id="{{ $location->id }}" title="Edit Category" aria-label="Edit Category">
                                        <i data-feather="edit" style="width:14px;height:14px;"></i>
                                    </button>
                                    <form method="POST" action="{{ route('categories.items.destroy', $category) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?')" title="Delete Category" aria-label="Delete Category">
                                            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td class="ps-4 text-muted" colspan="4">No categories yet under this location.</td>
                            </tr>
                            @endforelse
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No locations found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($user->isAdmin())
    <div class="modal fade" id="editLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="editLocationForm" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Location Name</label>
                    <input type="text" name="name" id="editLocationName" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="editCategoryForm" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name</label>
                        <input type="text" name="name" id="editCategoryName" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Location</label>
                        <select name="location_id" id="editCategoryLocationId" class="form-select" required>
                            @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->branch?->name ?? 'N/A' }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if($user->isAdmin())
    const editLocationModal = new bootstrap.Modal(document.getElementById('editLocationModal'));
    document.querySelectorAll('.edit-location-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('editLocationName').value = this.dataset.name;
            document.getElementById('editLocationForm').action = '{{ url('/categories/locations') }}/' + this.dataset.id;
            editLocationModal.show();
        });
    });

    const editCategoryModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    document.querySelectorAll('.edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('editCategoryName').value = this.dataset.name;
            document.getElementById('editCategoryLocationId').value = this.dataset.locationId;
            document.getElementById('editCategoryForm').action = '{{ url('/categories/items') }}/' + this.dataset.id;
            editCategoryModal.show();
        });
    });
    @endif

    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>
@endpush

