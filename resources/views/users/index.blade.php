@extends('layouts.app')
@section('page_title', 'Users')
@section('content')
    <x-page-header title="Users" subtitle="Manage staff access, roles, and branch assignments" icon="user-check">
        <a href="{{ route('users.create') }}" class="btn btn-light text-primary">
            <i data-lucide="plus-circle" class="me-1"></i> Add User
        </a>
    </x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')
    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-nowrap"><i class="me-1" data-lucide="users" style="width: 16px; height: 16px;"></i> All Users</div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <select id="roleFilter" class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="branch_manager">Branch Manager</option>
                    <option value="staff">Staff</option>
                </select>
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search users...">
                    <span class="input-group-text"><i data-lucide="search" style="width: 14px; height: 14px;"></i></span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle" id="usersTable" data-server-sort="1">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 56px;">#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Joined</th>
                            <th class="table-actions-head">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div id="tableInfo" class="text-muted small"></div>
            <nav aria-label="Users pagination" class="custom-pagination-wrapper">
                <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
            </nav>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let perPage = 10;
    let searchQuery = '';
    let roleFilter = '';
    let sortColumn = 'created_at';
    let sortDirection = 'desc';
    const sortColumns = ['id', 'name', 'email', 'role', 'branch_id', 'created_at'];
    const currentUserId = {{ auth()->id() }};
    const currentUserRole = @json(auth()->user()->role);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function loadData() {
        const params = new URLSearchParams({
            page: currentPage,
            per_page: perPage,
            search: searchQuery,
            role: roleFilter,
            sort: sortColumn,
            direction: sortDirection
        });

        fetch(`{{ route('users.data') }}?${params}`)
            .then(response => response.json())
            .then(data => {
                renderTable(data.data || []);
                renderPagination(data);
                updateInfo(data);
            });
    }

    function renderTable(users) {
        const tbody = document.getElementById('tableBody');
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No users found</td></tr>';
            return;
        }

        const roleClasses = {
            'admin': 'bg-danger',
            'branch_manager': 'bg-warning',
            'staff': 'bg-info'
        };

        tbody.innerHTML = users.map((u, index) => {
            const displayIndex = ((currentPage - 1) * perPage) + index + 1;
            const roleLabel = String(u.role || '').replace(/_/g, ' ');
            const branchName = u.role === 'admin'
                ? 'All Branches'
                : (u.branch && u.branch.name ? u.branch.name : 'Unassigned');
            const joined = u.created_at
                ? new Date(u.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                : '-';

            const canManageTarget = currentUserRole === 'admin' || (currentUserRole === 'branch_manager' && String(u.role || '') === 'staff');

            const deleteAction = canManageTarget && Number(u.id) !== Number(currentUserId)
                ? `
                    <form action="{{ url('/users') }}/${u.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button class="btn btn-sm btn-danger text-white" title="Delete"><i data-lucide="trash-2"></i></button>
                    </form>
                `
                : '';

            return `
                <tr>
                    <td>${displayIndex}</td>
                    <td class="fw-semibold">${escapeHtml(u.name)}</td>
                    <td>${escapeHtml(u.email)}</td>
                    <td><span class="badge ${roleClasses[u.role] || 'bg-secondary'}">${escapeHtml(roleLabel)}</span></td>
                    <td>${escapeHtml(branchName)}</td>
                    <td>${escapeHtml(joined)}</td>
                    <td class="table-actions-cell text-nowrap">
                        ${canManageTarget ? `<a href="{{ url('/users') }}/${u.id}/edit" class="btn btn-sm btn-primary text-white" title="Edit"><i data-lucide="edit-2"></i></a>` : ''}
                        ${deleteAction}
                    </td>
                </tr>
            `;
        }).join('');

        if (typeof window.refreshLucideIcons === 'function') {
            window.refreshLucideIcons();
        }
    }

    function renderPagination(data) {
        const pagination = document.getElementById('pagination');
        if (!pagination) {
            return;
        }

        let html = '';
        if (data.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${data.current_page - 1}">Previous</a></li>`;
        }

        for (let i = 1; i <= (data.last_page || 1); i++) {
            html += `<li class="page-item ${i === data.current_page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }

        if (data.current_page < data.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${data.current_page + 1}">Next</a></li>`;
        }

        pagination.innerHTML = html;
    }

    function updateInfo(data) {
        const total = Number(data.total || 0);
        const info = document.getElementById('tableInfo');
        if (!info) {
            return;
        }

        if (total === 0) {
            info.textContent = 'No entries found';
            return;
        }

        const start = (data.current_page - 1) * data.per_page + 1;
        const end = Math.min(data.current_page * data.per_page, total);
        info.textContent = `Showing ${start} to ${end} of ${total} entries`;
    }

    document.getElementById('searchInput').addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        searchQuery = e.target.value;
        currentPage = 1;
        loadData();
    });

    document.getElementById('roleFilter').addEventListener('change', function(e) {
        roleFilter = e.target.value;
        currentPage = 1;
        loadData();
    });

    document.getElementById('pagination').addEventListener('click', function(e) {
        if (e.target.dataset.page) {
            e.preventDefault();
            currentPage = parseInt(e.target.dataset.page, 10);
            loadData();
        }
    });

    const usersTable = document.getElementById('usersTable');
    usersTable.addEventListener('welheim:table-sort', function(e) {
        const columnIndex = e.detail && Number.isInteger(e.detail.columnIndex) ? e.detail.columnIndex : -1;
        const mappedSort = sortColumns[columnIndex] || '';
        if (!mappedSort) {
            return;
        }

        sortColumn = mappedSort;
        sortDirection = e.detail && e.detail.direction === 'desc' ? 'desc' : 'asc';
        currentPage = 1;
        loadData();
    });

    loadData();
});
</script>
@endpush



