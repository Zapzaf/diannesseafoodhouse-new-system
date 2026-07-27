@extends('layouts.app')

@section('page_title', 'Menu Items - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Menu Items" subtitle="Manage restaurant menu items and recipes" icon="coffee">
        <a class="btn btn-light text-primary" href="{{ route('menus.create') }}">
            <i data-lucide="plus-circle" class="me-1"></i> Add Menu Item
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><i data-lucide="coffee" class="me-1"></i> All Menu Items</div>
                <div class="d-flex gap-2">
                    <select id="categoryFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All Categories</option>
                        @foreach($menuCategories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <select id="perPage" class="form-select form-select-sm" style="width: auto;">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search menu items..." style="max-width: 280px;">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="menusTable" data-server-sort="1">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Selling Price</th>
                                <th>Ingredients</th>
                                <th>Branch</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"><tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="tableInfo" class="text-muted small"></div>
                <nav>
                    <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
                </nav>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/custom-table.js') }}"></script>
<script src="{{ asset('js/index-table-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    IndexTableBridge.init({
        tableId: 'menusTable',
        dataUrl: @json(route('menus.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        filters: [
            { inputId: 'categoryFilter', param: 'menu_category_id' }
        ],
        sortColumns: ['id', 'name', 'menu_description', 'category', 'selling_price', 'items_count', 'branch_id'],
        defaultSort: 'name',
        defaultDirection: 'asc',
        emptyMessage: 'No menu items found.',
        colspan: 8,
        renderRow: function(menu, ctx) {
            const description = menu.menu_description || '';
            const shortDescription = description.length > 32 ? description.slice(0, 32) + '...' : description;
            const ingredientText = menu.no_ingredients
                ? '<span class="text-muted small fst-italic">No Ingredients</span>'
                : (Number(menu.items_count || 0) > 0
                    ? `<span class="text-muted small">${menu.items_count} ingredient(s)</span>`
                    : '<span class="text-warning small">No ingredients</span>');

            return `<tr>
                    <td>${ctx.index}</td>
                    <td class="fw-semibold">${ctx.escapeHtml(menu.name)}</td>
                    <td class="text-muted small text-nowrap" title="${ctx.escapeHtml(description)}">${ctx.escapeHtml(shortDescription || '—')}</td>
                    <td><span class="badge bg-secondary">${ctx.escapeHtml(menu.category || menu.category_label || '\u2014')}</span></td>
                    <td>\u20B1${Number(menu.selling_price || 0).toFixed(2)}</td>
                    <td>${ingredientText}</td>
                    <td>${ctx.escapeHtml(menu.branch ? menu.branch.name : '\u2014')}</td>
                    <td class="table-actions-cell text-nowrap">
                            <a href="{{ url('/menus') }}/${menu.id}" class="btn btn-sm btn-outline-info" title="View"><i data-lucide="eye"></i></a>
                            <a href="{{ url('/menus') }}/${menu.id}/edit" class="btn btn-sm btn-outline-primary" title="Edit"><i data-lucide="edit"></i></a>
                            <form action="{{ url('/menus') }}/${menu.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this menu item?')">
                                <input type="hidden" name="_token" value="${csrf}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i data-lucide="trash-2"></i></button>
                            </form>
                        </td>
                    </tr>
                `;
        }
    });
});
</script>
@endpush
