@extends('layouts.app')

@section('page_title', 'Production - Dianne Seafood House')

@section('content')
    <x-page-header title="Production Orders" subtitle="Track production batches from raw inputs to finished outputs" icon="wrench">
        <a href="{{ route('productions.create') }}" class="btn btn-light">
            <i data-lucide="plus" class="me-1"></i> Start Production
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>Production Orders</div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="perPage" class="form-select form-select-sm" style="width: auto;">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <span class="input-group-text"><i data-lucide="search" style="width: 14px; height: 14px;"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="productionsTable" data-server-sort="1">
                        <thead>
                            <tr>
                                <th data-sort-key="id">Production Info</th>
                                @if(auth()->user()?->isAdmin())
                                <th data-sort-key="branch_name">Branch</th>
                                @endif
                                <th data-sort-key="status">Status</th>
                                <th data-sort-key="created_at">Created Date</th>
                                <th data-sort-key="finished_at">Finished Date</th>
                                <th data-sort-key="inputs_count">Input Details</th>
                                <th data-sort-key="outputs_count">Output Details</th>
                                <th class="table-actions-head text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"><tr><td colspan="{{ auth()->user()?->isAdmin() ? 8 : 7 }}" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="tableInfo" class="text-muted small"></div>
                <nav aria-label="Production orders pagination">
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
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const isAdmin = @json(auth()->user()?->isAdmin() ?? false);

    function renderItemSummary(summary, variant, emptyLabel) {
        if (!summary || !summary.items || summary.items.length === 0) {
            return '<div class="production-item-summary"><span class="text-muted small">' + emptyLabel + '</span></div>';
        }

        const rows = summary.items.map(function (entry) {
            const unit = entry.unit ? ' ' + IndexTableBridge_escapeHtml(entry.unit) : '';
            return '<div class="production-item-summary-row production-item-summary-row-' + variant + '">'
                + '<span class="production-item-summary-name" title="' + IndexTableBridge_escapeHtml(entry.name) + '">' + IndexTableBridge_escapeHtml(entry.name) + '</span>'
                + '<span class="production-item-summary-meta">' + Number(entry.quantity || 0).toFixed(2) + unit + '</span>'
                + '</div>';
        }).join('');

        const more = summary.more > 0 ? '<span class="production-item-summary-more">' + summary.more + ' more...</span>' : '';

        return '<div class="production-item-summary">' + rows + more + '</div>';
    }

    function IndexTableBridge_escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    IndexTableBridge.init({
        tableId: 'productionsTable',
        dataUrl: @json(route('productions.data')),
        searchInputId: 'searchInput',
        perPageId: 'perPage',
        sortColumns: isAdmin
            ? ['id', 'branch_name', 'status', 'created_at', 'finished_at', 'inputs_count', 'outputs_count']
            : ['id', 'status', 'created_at', 'finished_at', 'inputs_count', 'outputs_count'],
        defaultSort: 'created_at',
        defaultDirection: 'desc',
        emptyMessage: 'No production orders yet.',
        colspan: isAdmin ? 8 : 7,
        renderRow: function (row, ctx) {
            const branchCell = isAdmin ? `<td>${ctx.escapeHtml(row.branch_name || 'N/A')}</td>` : '';
            const cancelBtn = row.cancel_url
                ? `<form action="${row.cancel_url}" method="POST" class="d-inline" onsubmit="return confirm('Cancel Production #${row.id}? Pulled inputs will be restocked to inventory.');">
                        <input type="hidden" name="_token" value="${csrf}">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Production"><i data-lucide="x-circle"></i></button>
                   </form>`
                : '';

            return `
                <tr>
                    <td>
                        <div class="fw-semibold">Production #${row.id}</div>
                        <div class="small text-muted">Created by ${ctx.escapeHtml(row.creator_name)}</div>
                    </td>
                    ${branchCell}
                    <td><span class="badge-status badge-${ctx.escapeHtml(row.status)}">${ctx.escapeHtml(row.status_label)}</span></td>
                    <td class="text-muted small">${ctx.escapeHtml(row.created_at || 'N/A')}</td>
                    <td class="text-muted small">${ctx.escapeHtml(row.finished_at || '—')}</td>
                    <td>${renderItemSummary(row.inputs, 'input', 'No inputs')}</td>
                    <td>${renderItemSummary(row.outputs, 'output', 'No outputs yet')}</td>
                    <td class="table-actions-cell text-end">
                        <a href="${row.show_url}" class="btn btn-sm btn-outline-secondary" title="Open"><i data-lucide="eye"></i></a>
                        ${cancelBtn}
                    </td>
                </tr>
            `;
        }
    });
});
</script>
@endpush
