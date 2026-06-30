// ============================================================================
// CustomDataTable - Reusable Table Framework
// ============================================================================
// A flexible, component-based data table framework that can be used across
// multiple pages with different data sources and UI requirements.
//
// Features:
// - Server-side pagination, sorting, and filtering
// - Flexible column rendering with custom templates
// - Action buttons with configurable handlers
// - Optional selection checkboxes (select-all + individual row selection)
// - State persistence via localStorage
// - Multiple response format support (simple REST, DataTables, custom envelopes)
// - Scoped DOM element lookup for multi-table page support
//
// Example Usage:
//   new CustomDataTable({
//       apiUrl: '/api/items/low-stock',
//       tableId: 'lowStockTable',
//       enableCheckboxes: true,  // Enable selection checkboxes
//       columns: [
//           { key: 'ItemID', label: 'Item ID' },
//           { key: 'ItemName', label: 'Item Name' },
//           { key: 'ItemQty', label: 'Stock', render: (val) => `<strong>${val}</strong>` }
//       ],
//       actions: [
//           { icon: 'eye', class: 'btn-primary', onclick: 'viewItem' },
//           { icon: 'trash-2', class: 'btn-danger', onclick: 'deleteItem' }
//       ]
//   });
// ============================================================================

console.log('[custom-table.js] loaded - CustomDataTable Framework');

class CustomDataTable {
    constructor(options) {
        // ===== REQUIRED OPTIONS =====
        this.tableId = options.tableId; // ID of the <table>
        this.apiUrl = options.apiUrl; // server endpoint
        
        // ===== COLUMN & DISPLAY OPTIONS =====
        this.columns = (options.columns || []).map(c => Object.assign({}, c)); // column definitions
        this.actions = options.actions || [];
        this.stateKey = options.stateKey || (`tableState_${this.tableId}`);
        this.primaryKey = options.primaryKey || null; // name of primary key field in data
        
        // ===== REQUEST/RESPONSE FORMAT OPTIONS =====
        this.method = (options.method || 'GET').toUpperCase();
        this.requestFormat = options.requestFormat || 'simple'; // 'simple' or 'datatables'
        this.csrfToken = options.csrfToken || (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null);
        this.additionalParams = options.additionalParams || {}; // Extra parameters to send with each request
        this.headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }, options.headers || {}); // Custom headers
        
        // ===== PAGINATION & SORTING OPTIONS =====
        this.perPage = options.perPage || 10;
        this.defaultSort = options.defaultSort || null;
        this.defaultOrder = options.defaultOrder || 'asc';
        this.maxPaginationLinks = options.maxPaginationLinks || 5; // Number of page links to show
        
        // ===== SEARCH & FILTER OPTIONS =====
        this.searchDebounceDelay = options.searchDebounceDelay || 300; // ms
        this.enableColumnFilters = options.enableColumnFilters || false; // Enable per-column filters
        this.columnFilters = options.columnFilters || {}; // Define column-specific filters
        
        // ===== ROW INTERACTION OPTIONS =====
        this.enableCheckboxes = options.enableCheckboxes || false; // Enable selection checkboxes
        this.onRowClick = options.onRowClick || null; // Callback when row is clicked
        this.rowHighlight = options.rowHighlight || false; // Highlight rows on hover/click
        this.enableRowSelection = options.enableRowSelection || this.enableCheckboxes;
        
        // ===== MESSAGING & UI OPTIONS =====
        this.emptyStateMessage = options.emptyStateMessage || 'No data found';
        this.emptyStateIcon = options.emptyStateIcon || '🔍';
        this.loadingMessage = options.loadingMessage || 'Loading...';
        this.errorMessage = options.errorMessage || 'Failed to load data. Please try again.';
        this.noResultsMessage = options.noResultsMessage || 'No results match your search';
        this.showTableInfo = options.showTableInfo !== false; // Show "Showing X to Y of Z"
        
        // ===== CALLBACK FUNCTIONS =====
        this.onDataLoaded = options.onDataLoaded || null; // Called after data loads successfully
        this.onError = options.onError || null; // Called if request fails
        this.onRowsSelected = options.onRowsSelected || null; // Called when rows are selected/deselected
        this.beforeRequest = options.beforeRequest || null; // Called before each request
        this.afterRequest = options.afterRequest || null; // Called after each request
        this.onSort = options.onSort || null; // Called when sorting changes
        this.onPageChange = options.onPageChange || null; // Called when page changes
        
        // ===== AUTO-REFRESH OPTIONS =====
        this.autoRefresh = options.autoRefresh || false; // Enable auto-refresh (ms interval, false = disabled)
        this.autoRefreshInterval = null; // Stores interval ID
        
        // ===== CHECKBOX CONFIGURATION =====
        this.checkboxColumn = { key: '_select', label: '', sortable: false, sticky: true }; // checkbox column definition (sticky by default)

        // ===== DOM ELEMENT RESOLUTION =====
        // Find DOM elements scoped to the table. Prefer scoped data- attributes inside a container.
        this.table = document.getElementById(this.tableId);
        if (!this.table) throw new Error(`Table element with id "${this.tableId}" not found`);

        // Container is either provided or inferred (closest .table-wrapper or parentElement)
        this.container = document.getElementById(options.containerId) || this.table.closest('.table-wrapper') || this.table.parentElement;

        // Scoped elements (use data attributes if present, otherwise fallback to common IDs)
        this.tbody = this.table.querySelector('tbody') || (this.container && this.container.querySelector('tbody')) || document.getElementById('tableBody');
        this.searchInput = (this.container && this.container.querySelector('[data-table-search]')) || document.getElementById('searchInput');
        this.searchSuggestions = (this.container && this.container.querySelector('[data-table-suggestions]')) || document.getElementById('searchSuggestions');
        this.perPageSelect = (this.container && this.container.querySelector('[data-table-perpage]')) || document.getElementById('perPageSelect');
        this.pagination = (this.container && this.container.querySelector('[data-table-pagination]')) || document.getElementById('pagination');
        this.tableInfo = (this.container && this.container.querySelector('[data-table-info]')) || document.getElementById('tableInfo');

        // ===== INITIALIZE COLUMNS =====
        // If checkboxes enabled, prepend checkbox column to columns array
        if (this.enableCheckboxes) {
            this.columns.unshift(this.checkboxColumn);
        }

        // Normalize column keys (support `key` or `data`)
        this.columns.forEach(col => {
            col._data = col.data || col.key || col.name || null;
        });

        // Attempt to auto-detect primary key if not provided
        if (!this.primaryKey) {
            const candidates = ['ItemID', 'item_id', 'id', 'ID', 'ItemId'];
            const found = this.columns.find(c => c._data && candidates.includes(c._data));
            this.primaryKey = found ? found._data : 'id';
        }

        // ===== INITIALIZE STATE =====
        this.data = [];
        this.totalRecords = 0;
        this.filteredRecords = 0;
        this.currentPage = 1;
        this.perPage = options.perPage || (this.perPageSelect ? parseInt(this.perPageSelect.value || 10) : 10);
        this.sortColumn = this.defaultSort;
        this.sortDirection = this.defaultOrder;
        this.searchTerm = '';
        this.searchDebounce = null;
        this.draw = 1;
        this.filterParams = {}; // Store additional filter parameters
        this.isLoading = false;

        this.init();
    }
    
    init() {
        this.restoreState();
        this.setupEventListeners();
        this.applyDefaultSort();
        this.loadData();
    }
    
    applyDefaultSort() {
        // Apply default sort styling on initialization
        if (this.sortColumn) {
            const header = this.table.querySelector(`thead th[data-column="${this.sortColumn}"]`);
            if (header) {
                header.classList.add(`sort-${this.sortDirection}`);
            }
        }
    }
    
    setupEventListeners() {
        // Search input (scoped)
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchDebounce);
                this.searchDebounce = setTimeout(() => {
                    this.searchTerm = e.target.value.toLowerCase();
                    this.filterData();
                    this.showSuggestions();
                }, 300);
            });
        }
        
        // Click outside to close suggestions
        document.addEventListener('click', (e) => {
            if (this.searchInput && this.searchSuggestions) {
                if (!this.searchInput.contains(e.target) && !this.searchSuggestions.contains(e.target)) {
                    this.searchSuggestions.classList.remove('active');
                }
            }
        });
        
        // Per page select (scoped)
        if (this.perPageSelect) {
            this.perPageSelect.addEventListener('change', (e) => {
                this.perPage = parseInt(e.target.value);
                this.currentPage = 1;
                this.saveState();
                this.loadData(); // Reload from server with new per page
            });
        }
        
        // Column sorting
        const headers = this.table.querySelectorAll('thead th[data-column]');
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.column;
                this.sortTable(column, header);
            });
        });
        
        // Apply sticky-col class to headers that have sticky: true in column definition
        this.applyStickyClassToHeaders();
    }
    
    applyStickyClassToHeaders() {
        const headers = this.table.querySelectorAll('thead th[data-column]');
        headers.forEach(header => {
            const columnName = header.dataset.column;
            const columnDef = this.columns.find(col => col._data === columnName);
            if (columnDef && columnDef.sticky) {
                header.classList.add('sticky-col');
            }
        });
    }
    
    async loadData() {
        try {
            // Call beforeRequest callback if defined
            if (typeof this.beforeRequest === 'function') {
                await this.beforeRequest();
            }

            this.isLoading = true;
            this.showLoading();
            
            let requestUrl = this.apiUrl;
            let fetchOptions = { 
                method: this.method, 
                headers: Object.assign({}, this.headers)
            };

            // Build either simple REST-style params or DataTables payload
            if (this.requestFormat === 'datatables') {
                const requestData = {
                    draw: this.draw || 1,
                    start: (this.currentPage - 1) * this.perPage,
                    length: this.perPage,
                    search: {
                        value: this.searchTerm,
                        regex: false
                    },
                    order: this.sortColumn ? [{
                        column: this.getColumnIndex(this.sortColumn),
                        dir: this.sortDirection
                    }] : [],
                    columns: this.columns.map(col => ({
                        data: col._data,
                        name: col._data,
                        searchable: col.searchable !== false,
                        orderable: col.orderable !== false
                    }))
                };

                console.log('[CustomDataTable] Requesting data (datatables) from', this.apiUrl);
                console.log('[CustomDataTable] Request payload', requestData);
                fetchOptions.headers['Content-Type'] = 'application/json';
                // Read CSRF token safely
                const _csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const _csrfToken = _csrfMeta ? _csrfMeta.getAttribute('content') : null;
                if (_csrfToken) fetchOptions.headers['X-CSRF-TOKEN'] = _csrfToken;

                if (this.method === 'GET') {
                    // send as querystring (flattened)
                    const qs = new URLSearchParams();
                    qs.set('draw', requestData.draw);
                    qs.set('start', requestData.start);
                    qs.set('length', requestData.length);
                    qs.set('search', requestData.search.value);
                    requestUrl += (requestUrl.includes('?') ? '&' : '?') + qs.toString();
                } else {
                    fetchOptions.body = JSON.stringify(requestData);
                }

            } else {
                // simple format expected by many endpoints (per_page, page, search, sort_by, sort_order)
                const simple = {
                    per_page: this.perPage,
                    page: this.currentPage,
                    search_term: this.searchTerm,
                    sort_direction: this.sortDirection,
                    sort_column: this.sortColumn || 'id'
                };
                
                // Add filter parameters if they exist
                if (this.filterParams && typeof this.filterParams === 'object') {
                    Object.assign(simple, this.filterParams);
                }

                // Add additional parameters if they exist
                if (this.additionalParams && typeof this.additionalParams === 'object') {
                    Object.assign(simple, this.additionalParams);
                }
                
                console.log('[CustomDataTable] Requesting data (simple) from', this.apiUrl);
                console.log('[CustomDataTable] Request payload', simple);
                fetchOptions.headers['Content-Type'] = 'application/json';
                if (this.csrfToken) {
                    fetchOptions.headers['X-CSRF-TOKEN'] = this.csrfToken;
                }

                if (this.method === 'GET') {
                    const params = new URLSearchParams();
                    Object.keys(simple).forEach(k => {
                        if (simple[k] !== null && simple[k] !== undefined) params.set(k, simple[k]);
                    });
                    requestUrl += (requestUrl.includes('?') ? '&' : '?') + params.toString();
                } else {
                    fetchOptions.body = JSON.stringify(simple);
                }
            }

            const startedAt = Date.now();
            const response = await fetch(requestUrl, fetchOptions);
            
            if (!response.ok) throw new Error('Network response was not ok');

            const result = await response.json();
            const elapsed = Date.now() - startedAt;
            console.log(`[CustomDataTable] Response received in ${elapsed}ms`);
            console.log('[CustomDataTable] Server response', result);

            // Support different server response shapes. Prefer DataTables-style but fall back to a JSON list + pagination
            if (result && Array.isArray(result.data)) {
                this.data = result.data;
                this.totalRecords = result.recordsTotal || (result.records_total || (result.pagination && result.pagination.total) || this.data.length);
                this.filteredRecords = result.recordsFiltered || this.totalRecords;
                this.draw = result.draw || this.draw;
            } else if (Array.isArray(result)) {
                this.data = result;
                this.totalRecords = this.data.length;
                this.filteredRecords = this.data.length;
            } else if (result && Array.isArray(result.items)) {
                this.data = result.items;
                this.totalRecords = result.total || (result.pagination && result.pagination.total) || this.data.length;
                this.filteredRecords = this.totalRecords;
            } else if (result && result.success && Array.isArray(result.data)) {
                // Handle success envelope with data array
                this.data = result.data;
                this.totalRecords = result.pagination ? result.pagination.total : this.data.length;
                this.filteredRecords = this.totalRecords;
            } else {
                this.data = [];
                this.totalRecords = 0;
                this.filteredRecords = 0;
            }
            
            this.renderTable();
            
            // Call afterRequest callback if defined
            if (typeof this.afterRequest === 'function') {
                await this.afterRequest(this.data);
            }

            // Call onDataLoaded callback if defined
            if (typeof this.onDataLoaded === 'function') {
                await this.onDataLoaded(this.data, this.totalRecords);
            }
            
            // Clear the return flag after rendering
            this.clearReturnFlag();
            this.isLoading = false;
        } catch (error) {
            console.error('Error loading data:', error);
            this.isLoading = false;
            
            // Call onError callback if defined
            if (typeof this.onError === 'function') {
                await this.onError(error);
            }
            
            this.showError(this.errorMessage);
        }
    }
    
    getColumnIndex(columnName) {
        return this.columns.findIndex(col => col._data === columnName || col.data === columnName || col.key === columnName);
    }
    
    showLoading() {
        const colspan = this.getTotalColumns();
        this.tbody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </td>
            </tr>
        `;
    }

    getTotalColumns() {
        // number of visible columns + actions column
        const visible = this.columns.length;
        const hasActions = (this.actions && this.actions.length) ? 1 : 0;
        return visible + hasActions;
    }
    
    filterData() {
        this.currentPage = 1;
        this.saveState();
        this.loadData(); // Reload from server with new search term
    }
    
    showSuggestions() {
        if (!this.searchSuggestions || !this.searchTerm || this.searchTerm.length < 2) {
            if (this.searchSuggestions) this.searchSuggestions.classList.remove('active');
            return;
        }
        
        const suggestions = [];
        const maxSuggestions = 5;
        
        // Find matching items from current data
        for (const row of this.data) {
            if (suggestions.length >= maxSuggestions) break;
            
            for (const col of this.columns) {
                const value = col._data ? row[col._data] : undefined;
                if (value && value.toString().toLowerCase().includes(this.searchTerm)) {
                    suggestions.push({
                        label: col.label,
                        value: value.toString(),
                        fullRow: row
                    });
                    break;
                }
            }
        }
        
        if (suggestions.length === 0) {
            if (this.searchSuggestions) this.searchSuggestions.classList.remove('active');
            return;
        }
        
        if (!this.searchSuggestions) return; // exit if no suggestions container
        
        // Render suggestions
        this.searchSuggestions.innerHTML = suggestions.map(s => {
            const highlighted = this.highlightMatch(s.value, this.searchTerm);
            return `
                <div class="suggestion-item" data-value="${this.escapeHtml(s.value)}">
                    <div class="suggestion-label">${s.label}</div>
                    <div class="suggestion-text">${highlighted}</div>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        this.searchSuggestions.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                if (this.searchInput) this.searchInput.value = item.dataset.value;
                this.searchTerm = item.dataset.value.toLowerCase();
                this.filterData();
                if (this.searchSuggestions) this.searchSuggestions.classList.remove('active');
            });
        });
        
        this.searchSuggestions.classList.add('active');
    }
    
    highlightMatch(text, search) {
        const regex = new RegExp(`(${search})`, 'gi');
        return text.replace(regex, '<span class="suggestion-match">$1</span>');
    }
    
    sortTable(column, headerElement) {
        // Toggle sort direction
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        
        // Update header classes
        const headers = this.table.querySelectorAll('thead th[data-column]');
        headers.forEach(h => {
            h.classList.remove('sort-asc', 'sort-desc');
        });
        headerElement.classList.add(`sort-${this.sortDirection}`);
        
        // Call onSort callback if defined
        if (typeof this.onSort === 'function') {
            this.onSort(this.sortColumn, this.sortDirection);
        }
        
        this.saveState();
        this.loadData(); // Reload from server with new sort
    }
    
    renderTable() {
        if (this.data.length === 0) {
            const colspan = this.getTotalColumns();
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="text-center py-5">
                        <div class="empty-state">
                            <i>${this.emptyStateIcon}</i>
                            <div>${this.emptyStateMessage}</div>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            this.tbody.innerHTML = this.data.map((row, idx) => this.renderRow(row, idx)).join('');
        }
        
        // NEW: Only clear/update checkboxes if enabled
        if (this.enableCheckboxes) {
            const selectAllCheckbox = document.getElementById('selectAllRows');
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
        }
        
        this.renderPagination();
        if (this.showTableInfo) {
            this.updateTableInfo();
        }
        
        // Initialize Lucide icons for the newly rendered content
        if (typeof window.refreshLucideIcons === 'function') {
            window.refreshLucideIcons();
        }
        
        // NEW: Add event listeners to row checkboxes only if enabled
        if (this.enableCheckboxes) {
            this.tbody.querySelectorAll('.select-row').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.updateSelectAllCheckbox();
                    if (typeof this.onRowsSelected === 'function') {
                        this.onRowsSelected(this.getSelectedRows(), this.getSelectedRowData());
                    }
                });
            });
        }

        // Add row click handlers if onRowClick callback is defined
        if (typeof this.onRowClick === 'function') {
            this.tbody.querySelectorAll('.table-row').forEach(row => {
                row.addEventListener('click', (e) => {
                    // Don't trigger if clicking on checkbox or action buttons
                    if (e.target.closest('.select-row, .action-btns, .app-action-group, .inventory-action-group, .table-actions-cell, a.btn, button.btn, form')) {
                        return;
                    }
                    const rowId = row.dataset.rowId;
                    const rowData = this.data.find(r => String(r[this.primaryKey]) === rowId);
                    this.onRowClick(rowId, rowData, row);
                });
            });
        }
    }

    updateSelectAllCheckbox() {
        if (!this.enableCheckboxes) return; // Skip if checkboxes disabled
        
        const selectAllCheckbox = document.getElementById('selectAllRows');
        if (!selectAllCheckbox) return;

        const allCheckboxes = this.tbody.querySelectorAll('.select-row');
        const checkedCheckboxes = this.tbody.querySelectorAll('.select-row:checked');

        // Auto-check select-all if all rows are checked
        selectAllCheckbox.checked = (allCheckboxes.length > 0 && checkedCheckboxes.length === allCheckboxes.length);
        
        // Update indeterminate state if some (but not all) are checked
        selectAllCheckbox.indeterminate = (checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length);
    }
    
    renderRow(row) {
        // Build row cells dynamically from column definitions
        const cells = this.columns.map(col => {
            const key = col._data;
            const stickyClass = col.sticky ? ' sticky-col' : '';
            
            // Handle checkbox column (only if enabled)
            if ((col.key === '_select' || key === '_select') && this.enableCheckboxes) {
                const id = this.escapeHtml((row[this.primaryKey] !== undefined && row[this.primaryKey] !== null) ? row[this.primaryKey] : (key ? row[key] : '') || '');
                return `<td class="${stickyClass}"><input type="checkbox" class="select-row" data-id="${id}" /></td>`;
            }
            
            // Skip checkbox column if not enabled
            if (col.key === '_select' || key === '_select') {
                return '';
            }

            const raw = key ? (row[key] !== undefined ? row[key] : '') : '';
            let content = '';
            if (typeof col.render === 'function') {
                try {
                    content = col.render(raw, row);
                } catch (e) {
                    console.warn('Column render error', e);
                    content = this.escapeHtml(String(raw));
                }
            } else {
                content = this.escapeHtml(String(raw));
            }

            return `<td class="${stickyClass}">${content}</td>`;
        }).filter(cell => cell !== '').join(''); // Filter out empty checkbox cells when disabled

        const actionsHtml = this.actions && this.actions.length ? `<td class="actions-col">${this.renderActions(row)}</td>` : '';

        // Build row class list
        let rowClass = '';
        if (this.rowHighlight) rowClass += ' row-interactive';
        const rowId = row[this.primaryKey];

        return `
            <tr class="table-row ${rowClass}" data-row-id="${rowId}">
                ${cells}
                ${actionsHtml}
            </tr>
        `;
    }
    
    renderActions(row) {
        // actions can be { label, icon, class, onclick, title, condition } - renders icon-only buttons with tooltip
        const btns = (this.actions || []).map((action, idx) => {
            // Check if action has a condition and if it evaluates to false, skip rendering
            if (action.condition && typeof action.condition === 'function') {
                if (!action.condition(row)) {
                    return ''; // Skip rendering this action
                }
            }

            const cls = action.class || 'btn-secondary';
            const icon = action.icon ? `<i data-lucide="${action.icon}"></i>` : '';
            const title = action.title || action.label || '';
            let id = (row[this.primaryKey] !== undefined && row[this.primaryKey] !== null) ? row[this.primaryKey] : 
                       ((row[action.key || this.primaryKey] !== undefined && row[action.key || this.primaryKey] !== null) ? row[action.key || this.primaryKey] : 
                       ((row.id !== undefined && row.id !== null) ? row.id : ''));
            // Convert to string to ensure proper handling of 0
            id = String(id);

            // onclick wiring: call provided function reference or global function name
            let onclick = '';
            if (typeof action.onclick === 'function') {
                // store function on window so inline onclick can call it
                const fnName = `__table_action_${this.tableId}_${idx}_${Math.random().toString(36).substr(2,9)}`;
                window[fnName] = (id) => {
                    try {
                        action.onclick(id, row);
                    } catch (e) {
                        console.error('Action handler error:', e);
                    }
                };
                onclick = `return window.${fnName}('${id}')`;
            } else if (typeof action.onclick === 'string') {
                // For string onclick handlers, pass the status as second argument if available
                const status = row.delivery_status || row.status || '';
                onclick = `return ${action.onclick}('${id}', '${status}')`;
            } else if (action.href) {
                onclick = `window.location.href='${action.href.replace(/\{id\}/g, id)}'`;
            } else {
                onclick = '';
            }

            const handler = onclick ? `onclick="${onclick}"` : '';

            return `<button class="btn btn-sm ${cls}" title="${title}" type="button" ${handler}>${icon}</button>`;
        }).join('');

        return `<div class="action-btns">${btns}</div>`;
    }
    
    renderPagination() {
        const totalPages = Math.ceil(this.filteredRecords / this.perPage);
        
        if (totalPages <= 1) {
            this.pagination.innerHTML = '';
            return;
        }
        
        if (window.WelheimUI && typeof window.WelheimUI.renderPagination === 'function') {
            window.WelheimUI.renderPagination(this.pagination, {
                current_page: this.currentPage,
                last_page: totalPages
            });
        }

        // Add click handlers
        this.pagination.querySelectorAll('[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page, 10);
                if (!Number.isInteger(page)) {
                    return;
                }

                if (page > 0 && page <= totalPages && page !== this.currentPage) {
                    this.currentPage = page;
                    this.saveState();
                    this.loadData(); // Reload from server with new page
                    this.scrollToTop();
                }
            });
        });
        
        // NEW: Only add checkbox listeners if enabled
        if (this.enableCheckboxes) {
            this.tbody.querySelectorAll('.select-row').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.updateSelectAllCheckbox();
                });
            });
        }
    }
    
    updateTableInfo() {
        const start = this.data.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
        const end = Math.min(start + this.data.length - 1, (this.currentPage - 1) * this.perPage + this.data.length);
        const total = this.filteredRecords;
        const totalRecords = this.totalRecords;
        
        let info = `Showing ${start} to ${end} of ${total} entries`;
        if (total !== totalRecords) {
            info += ` (filtered from ${totalRecords} total entries)`;
        }
        
        this.tableInfo.textContent = info;
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        const hasTime = typeof dateString === 'string' && (dateString.includes('T') || dateString.includes(' '));
        const dateText = date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        if (!hasTime) {
            return dateText;
        }

        const timeText = date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
        });

        return `${dateText} ${timeText}`;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    showError(message) {
        const colspan = this.getTotalColumns ? this.getTotalColumns() : 9;
        this.tbody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center py-5">
                    <div class="text-danger">
                        <i>⚠️</i>
                        <div>${message}</div>
                    </div>
                </td>
            </tr>
        `;
    }
    
    // State Management Methods
    saveState() {
        const state = {
            currentPage: this.currentPage,
            perPage: this.perPage,
            sortColumn: this.sortColumn,
            sortDirection: this.sortDirection,
            searchTerm: this.searchTerm,
            timestamp: Date.now()
        };
        
        try {
            localStorage.setItem(this.stateKey, JSON.stringify(state));
        } catch (e) {
            console.warn('Failed to save table state:', e);
        }
    }
    
    restoreState() {
        try {
            const saved = localStorage.getItem(this.stateKey);
            if (!saved) return;
            
            const state = JSON.parse(saved);
            
            // Only restore if state is less than 1 hour old
            const maxAge = 60 * 60 * 1000; // 1 hour
            if (Date.now() - state.timestamp > maxAge) {
                localStorage.removeItem(this.stateKey);
                return;
            }
            
            // Restore state
            this.currentPage = state.currentPage || 1;
            this.perPage = state.perPage || 10;
            this.sortColumn = state.sortColumn;
            this.sortDirection = state.sortDirection || 'asc';
            this.searchTerm = state.searchTerm || '';
            
            // Update UI elements
            if (this.perPageSelect) this.perPageSelect.value = this.perPage;
            if (this.searchInput) this.searchInput.value = this.searchTerm;
            
            // Restore sort indicator
            if (this.sortColumn) {
                const header = this.table.querySelector(`thead th[data-column="${this.sortColumn}"]`);
                if (header) {
                    header.classList.add(`sort-${this.sortDirection}`);
                }
            }
            
        } catch (e) {
            console.warn('Failed to restore table state:', e);
            localStorage.removeItem(this.stateKey);
        }
    }
    
    clearReturnFlag() {
        // Remove the flag after successfully returning
        sessionStorage.removeItem('tableReturn');
    }
    
    saveStateAndNavigate(action, id) {
        // Save current state
        this.saveState();
        
        // Set return flag
        sessionStorage.setItem('tableReturn', 'true');
        
        // Navigate based on action
        if (action === 'view') {
            viewExpression(id);
        } else if (action === 'edit') {
            editExpression(id);
        }
    }

    // ===== NEW UTILITY METHODS =====

    /**
     * Get selected row IDs (requires enableCheckboxes: true)
     * @returns {Array} Array of selected primary key values
     */
    getSelectedRows() {
        if (!this.enableCheckboxes) {
            console.warn('[CustomDataTable] Checkboxes not enabled. Set enableCheckboxes: true');
            return [];
        }
        const checked = Array.from(this.tbody.querySelectorAll('.select-row:checked'));
        return checked.map(cb => cb.getAttribute('data-id'));
    }

    /**
     * Get selected row data objects (requires enableCheckboxes: true)
     * @returns {Array} Array of selected row data objects
     */
    getSelectedRowData() {
        if (!this.enableCheckboxes) {
            console.warn('[CustomDataTable] Checkboxes not enabled. Set enableCheckboxes: true');
            return [];
        }
        const selectedIds = this.getSelectedRows();
        return this.data.filter(row => selectedIds.includes(String(row[this.primaryKey])));
    }

    /**
     * Clear all selections
     */
    clearSelection() {
        if (!this.enableCheckboxes) return;
        this.tbody.querySelectorAll('.select-row').forEach(cb => cb.checked = false);
        this.updateSelectAllCheckbox();
    }

    /**
     * Select all rows on current page
     */
    selectAllRows() {
        if (!this.enableCheckboxes) return;
        this.tbody.querySelectorAll('.select-row').forEach(cb => cb.checked = true);
        this.updateSelectAllCheckbox();
    }

    /**
     * Start auto-refresh with given interval (ms)
     * @param {number} interval - Interval in milliseconds
     */
    startAutoRefresh(interval) {
        if (this.autoRefreshInterval) {
            console.warn('[CustomDataTable] Auto-refresh already running');
            return;
        }
        this.autoRefresh = interval;
        this.autoRefreshInterval = setInterval(() => this.loadData(), interval);
        console.log(`[CustomDataTable] Auto-refresh started (${interval}ms)`);
    }

    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (!this.autoRefreshInterval) {
            console.warn('[CustomDataTable] Auto-refresh not running');
            return;
        }
        clearInterval(this.autoRefreshInterval);
        this.autoRefreshInterval = null;
        console.log('[CustomDataTable] Auto-refresh stopped');
    }

    /**
     * Get all current filter parameters
     * @returns {Object} Current filter state
     */
    getFilters() {
        return {
            search: this.searchTerm,
            filters: this.filterParams,
            sort: { column: this.sortColumn, direction: this.sortDirection },
            pagination: { page: this.currentPage, perPage: this.perPage }
        };
    }

    /**
     * Apply multiple filters at once
     * @param {Object} filters - Filter parameters to apply
     */
    setFilters(filters) {
        if (filters.search !== undefined) this.searchTerm = filters.search;
        if (filters.filterParams) Object.assign(this.filterParams, filters.filterParams);
        if (filters.page) this.currentPage = filters.page;
        this.currentPage = 1;
        this.saveState();
        this.loadData();
    }

    /**
     * Clear all filters and search
     */
    clearAllFilters() {
        this.searchTerm = '';
        this.filterParams = {};
        this.currentPage = 1;
        this.sortColumn = this.defaultSort;
        this.sortDirection = this.defaultOrder;
        if (this.searchInput) this.searchInput.value = '';
        this.saveState();
        this.loadData();
    }

    /**
     * Reload current data
     */
    refresh() {
        this.loadData();
    }

    /**
     * Export table data as JSON
     * @returns {Object} Table data with metadata
     */
    exportAsJSON() {
        return {
            exported: new Date().toISOString(),
            tableId: this.tableId,
            totalRecords: this.totalRecords,
            filteredRecords: this.filteredRecords,
            currentPage: this.currentPage,
            perPage: this.perPage,
            filters: this.getFilters(),
            data: this.data
        };
    }

    /**
     * Export table data as CSV
     * @returns {string} CSV formatted data
     */
    exportAsCSV() {
        const headers = this.columns
            .filter(col => col.key !== '_select')
            .map(col => `"${col.label || col._data}"`);
        
        const rows = this.data.map(row => {
            return this.columns
                .filter(col => col.key !== '_select')
                .map(col => {
                    let value = row[col._data] || '';
                    // Remove HTML tags if rendered
                    value = value.toString().replace(/<[^>]*>/g, '');
                    return `"${value.replace(/"/g, '""')}"`;
                })
                .join(',');
        });

        return [headers.join(','), ...rows].join('\n');
    }

    /**
     * Print table
     */
    print() {
        const printWindow = window.open('', '', 'height=500,width=800');
        const html = document.getElementById(this.tableId).outerHTML;
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Table</title>
                <link rel="stylesheet" href="${document.querySelector('link[href*="bootstrap"]').href}">
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                </style>
            </head>
            <body>
                <h2>${this.tableId}</h2>
                <p>Printed: ${new Date().toLocaleString()}</p>
                ${html}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    /**
     * Destroy table instance and cleanup
     */
    destroy() {
        // Stop auto-refresh if running
        if (this.autoRefreshInterval) {
            this.stopAutoRefresh();
        }
        
        // Clear state
        try {
            localStorage.removeItem(this.stateKey);
        } catch (e) {
            console.warn('Failed to clear state:', e);
        }
        
        // Remove event listeners (basic cleanup)
        this.searchInput = null;
        this.perPageSelect = null;
        this.pagination = null;
        this.data = [];
        
        console.log(`[CustomDataTable] Instance ${this.tableId} destroyed`);
    }
}

// Action functions (to be implemented in your Laravel app)
function viewExpression(id) {
    console.log('View expression:', id);
    // window.location.href = `/expression/${id}`;
}

function editExpression(id) {
    console.log('Edit expression:', id);
    // window.location.href = `/expression/${id}/edit`;
}

function deleteExpression(id) {
    if (confirm('Are you sure you want to delete this expression?')) {
        console.log('Delete expression:', id);
        // Send delete request
    }
}

// Expose for backwards compatibility: allow `new DataTable(...)` as used in blades
try {
    window.CustomDataTable = CustomDataTable;
    if (typeof window.DataTable === 'undefined') window.DataTable = CustomDataTable;
    console.log('[custom-table.js] exported CustomDataTable -> window.DataTable');
} catch (e) {
    console.warn('[custom-table.js] cannot export globals', e);
}
