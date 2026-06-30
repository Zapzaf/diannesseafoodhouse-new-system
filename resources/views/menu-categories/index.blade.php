@extends('layouts.app')

@section('page_title', 'Menu Categories - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Menu Categories" subtitle="Manage categories for menu items per branch" icon="tag">
        <a class="btn btn-primary" href="{{ route('menu-categories.create') }}">
            <i class="me-1" data-lucide="plus"></i> New Category
        </a>
    </x-page-header>
    <div class="container-xl px-4">
        @include('layouts.alerts')
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span></span>
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search categories..." style="max-width: 280px;">
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="menuCategoriesTable" data-server-sort="1">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Menus</th>
                                <th class="table-actions-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"><tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="tableInfo" class="text-muted small"></div>
                <nav aria-label="Pagination">
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
        tableId: 'menuCategoriesTable',
        dataUrl: @json(route('menu-categories.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        filterInputId: null,
        sortColumns: ['id', 'name', 'branch_id', 'created_at'],
        defaultSort: 'name',
        defaultDirection: 'asc',
        emptyMessage: 'No menu categories found.',
        colspan: 5,
        renderRow: function(cat, ctx) {
            return `
                <tr>
                    <td>${ctx.index}</td>
                    <td class="fw-semibold">${ctx.escapeHtml(cat.name)}</td>
                    <td>${ctx.escapeHtml(cat.branch ? cat.branch.name : '\u2014')}</td>
                    <td>${cat.menus_count || 0}</td>
                    <td class="table-actions-cell text-nowrap">
                        <a href="{{ url('/menu-categories') }}/${cat.id}/edit" class="btn btn-sm btn-outline-warning" title="Edit"><i data-lucide="edit"></i></a>
                        <form action="{{ url('/menu-categories') }}/${cat.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="_token" value="${csrf}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i data-lucide="trash-2"></i></button>
                        </form>
                    </td>
                </tr>`;
        }
    });
});
</script>
@endpush
