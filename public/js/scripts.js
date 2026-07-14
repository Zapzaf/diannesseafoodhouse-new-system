/*!
    * Start Bootstrap - SB Admin Pro v2.0.5 (https://shop.startbootstrap.com/product/sb-admin-pro)
    * Copyright 2013-2023 Start Bootstrap
    * Licensed under SEE_LICENSE (https://github.com/StartBootstrap/sb-admin-pro/blob/master/LICENSE)
    */
    window.addEventListener('DOMContentLoaded', event => {
    // Activate Lucide icons
    if (typeof window.refreshLucideIcons === 'function') {
        window.refreshLucideIcons();
    }

    // Enable tooltips globally
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Enable popovers globally
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Toggle the side navigation
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Uncomment Below to persist sidebar toggle between refreshes
        // if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        //     document.body.classList.toggle('sidenav-toggled');
        // }
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sidenav-toggled'));
        });
    }

    // Close side navigation when width < LG
    const sidenavContent = document.body.querySelector('#layoutSidenav_content');
    if (sidenavContent) {
        sidenavContent.addEventListener('click', event => {
            const BOOTSTRAP_LG_WIDTH = 992;
            if (window.innerWidth >= 992) {
                return;
            }
            if (document.body.classList.contains("sidenav-toggled")) {
                document.body.classList.toggle("sidenav-toggled");
            }
        });
    }

    // Add active state to sidbar nav links
    let activatedPath = window.location.pathname.match(/([\w-]+\.html)/, '$1');

    if (activatedPath) {
        activatedPath = activatedPath[0];
    } else {
        activatedPath = 'index.html';
    }

    const targetAnchors = document.body.querySelectorAll('[href="' + activatedPath + '"].nav-link');

    targetAnchors.forEach(targetAnchor => {
        let parentNode = targetAnchor.parentNode;
        while (parentNode !== null && parentNode !== document.documentElement) {
            if (parentNode.classList.contains('collapse')) {
                parentNode.classList.add('show');
                const parentNavLink = document.body.querySelector(
                    '[data-bs-target="#' + parentNode.id + '"]'
                );
                parentNavLink.classList.remove('collapsed');
                parentNavLink.classList.add('active');
            }
            parentNode = parentNode.parentNode;
        }
        targetAnchor.classList.add('active');
    });

    function getCompactPaginationItems(currentPage, lastPage) {
        const pages = new Set([1, 2, lastPage - 1, lastPage, currentPage - 1, currentPage, currentPage + 1]);
        const sortedPages = Array.from(pages)
            .filter(page => page >= 1 && page <= lastPage)
            .sort((a, b) => a - b);

        const items = [];
        for (let i = 0; i < sortedPages.length; i++) {
            const page = sortedPages[i];
            const prev = sortedPages[i - 1];
            if (i > 0 && page - prev > 1) {
                items.push('ellipsis');
            }
            items.push(page);
        }

        return items;
    }

    function createPageItem(page, label, isActive, isDisabled) {
        const activeClass = isActive ? ' active' : '';
        const disabledClass = isDisabled ? ' disabled' : '';
        const safePage = isDisabled ? '' : String(page);
        return `<li class="page-item${activeClass}${disabledClass}"><a class="page-link" href="#" data-page="${safePage}">${label}</a></li>`;
    }

    window.WelheimUI = window.WelheimUI || {};
    window.WelheimUI.renderPagination = function(paginationElement, data) {
        if (!paginationElement || !data) {
            return;
        }

        const currentPage = Math.max(1, Number(data.current_page) || 1);
        const lastPage = Math.max(1, Number(data.last_page) || 1);
        const compactItems = getCompactPaginationItems(currentPage, lastPage);

        let html = '';
        html += createPageItem(currentPage - 1, 'Previous', false, currentPage <= 1);

        compactItems.forEach(item => {
            if (item === 'ellipsis') {
                html += '<li class="page-item disabled"><span class="page-link pagination-ellipsis">...</span></li>';
                return;
            }
            html += createPageItem(item, String(item), item === currentPage, false);
        });

        html += createPageItem(currentPage + 1, '<span class="page-link-label">Next</span><span aria-hidden="true">&rsaquo;</span>', false, currentPage >= lastPage);
        html += `
            <li class="page-item page-jump-item">
                <div class="page-jump-wrap">
                    <input type="number" min="1" max="${lastPage}" aria-label="Go to page" class="form-control js-page-jump-input" placeholder="#">
                    <button type="button" class="btn btn-secondary js-page-jump-btn" disabled>Go</button>
                </div>
            </li>
        `;

        paginationElement.innerHTML = html;

        const pageJumpInput = paginationElement.querySelector('.js-page-jump-input');
        const pageJumpBtn = paginationElement.querySelector('.js-page-jump-btn');

        if (!pageJumpInput || !pageJumpBtn) {
            return;
        }

        function updatePageJumpState() {
            const requestedPage = Number(pageJumpInput.value);
            const isValid = Number.isInteger(requestedPage) && requestedPage >= 1 && requestedPage <= lastPage;

            if (isValid) {
                pageJumpBtn.setAttribute('data-page', String(requestedPage));
            } else {
                pageJumpBtn.setAttribute('data-page', '');
            }

            pageJumpBtn.disabled = !isValid;
        }

        pageJumpInput.addEventListener('input', updatePageJumpState);
        pageJumpInput.addEventListener('change', updatePageJumpState);
        pageJumpInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                updatePageJumpState();
                if (!pageJumpBtn.disabled) {
                    pageJumpBtn.click();
                }
            }
        });
    };

    function getHeaderRow(table) {
        if (!table) {
            return null;
        }

        if (table.tHead && table.tHead.rows && table.tHead.rows.length > 0) {
            return table.tHead.rows[table.tHead.rows.length - 1];
        }

        return table.querySelector('thead tr');
    }

    function getComparableValue(text) {
        const value = (text || '').trim();
        const numericValue = Number(value.replace(/[^0-9.-]/g, ''));
        const dateValue = Date.parse(value);

        if (value !== '' && !Number.isNaN(numericValue) && /\d/.test(value)) {
            return { type: 'number', value: numericValue };
        }

        if (!Number.isNaN(dateValue) && /\d/.test(value)) {
            return { type: 'date', value: dateValue };
        }

        return { type: 'string', value: value.toLowerCase() };
    }

    function sortTableByColumn(table, columnIndex, direction) {
        if (!table || !table.tBodies || table.tBodies.length === 0) {
            return;
        }

        const tbody = table.tBodies[0];
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        const rows = Array.from(tbody.rows).map((row, index) => ({ row: row, index: index }));

        rows.sort((a, b) => {
            const cellA = a.row.cells[columnIndex];
            const cellB = b.row.cells[columnIndex];
            const valueA = getComparableValue(cellA ? cellA.innerText : '');
            const valueB = getComparableValue(cellB ? cellB.innerText : '');

            if (valueA.type === valueB.type && valueA.value !== valueB.value) {
                if (valueA.type === 'number' || valueA.type === 'date') {
                    return direction === 'asc' ? valueA.value - valueB.value : valueB.value - valueA.value;
                }
                return direction === 'asc'
                    ? collator.compare(valueA.value, valueB.value)
                    : collator.compare(valueB.value, valueA.value);
            }

            if (valueA.type !== valueB.type) {
                return direction === 'asc'
                    ? collator.compare(String(valueA.value), String(valueB.value))
                    : collator.compare(String(valueB.value), String(valueA.value));
            }

            return a.index - b.index;
        });

        rows.forEach(item => {
            tbody.appendChild(item.row);
        });

        if (typeof window.refreshLucideIcons === 'function') {
            window.refreshLucideIcons();
        }
    }

    function normalizeSortKey(label) {
        return String(label || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function getSortKey(th, originalContent) {
        const explicitKey = (th.dataset.sortKey || '').trim();
        if (explicitKey) {
            return explicitKey;
        }

        return normalizeSortKey(originalContent ? originalContent.textContent : th.textContent);
    }

    function sortServerRenderedTable(table, sortKey, direction) {
        if (!sortKey) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('sort', sortKey);
        url.searchParams.set('direction', direction);
        url.searchParams.delete('page');

        if (window.WelheimUI && typeof window.WelheimUI.loadTableUrl === 'function' && window.WelheimUI.loadTableUrl(url.toString(), table)) {
            return;
        }

        window.location.assign(url.toString());
    }

    function applyActionColumnClasses(table) {
        const headerRow = getHeaderRow(table);
        if (!headerRow) {
            return;
        }

        let headers = Array.from(headerRow.cells || []);
        let actionIndex = -1;

        headers.forEach((th, index) => {
            const headerText = (th.textContent || '').trim().toLowerCase();
            if (th.classList.contains('table-actions-head') || headerText.includes('action')) {
                actionIndex = index;
            }
            th.classList.remove('table-actions-head');
        });

        if (actionIndex < 0 && headers.length > 0) {
            const lastIndex = headers.length - 1;
            const lastHeaderText = (headers[lastIndex].textContent || '').trim();
            const hasActionControls = Array.from(table.tBodies || []).some(tbody =>
                Array.from(tbody.rows || []).some(row => {
                    const cell = row.cells[lastIndex];
                    return cell && Boolean(cell.querySelector('a.btn, button.btn, form, .btn, .action-btns, .app-action-group'));
                })
            );

            if (lastHeaderText === '' && hasActionControls) {
                actionIndex = lastIndex;
            }
        }

        Array.from(table.tBodies || []).forEach(tbody => {
            Array.from(tbody.rows || []).forEach(row => {
                Array.from(row.cells || []).forEach(cell => {
                    cell.classList.remove('table-actions-cell');
                });
            });
        });

        if (actionIndex < 0) {
            return;
        }

        // Keep Action as the final column so sticky-right does not leave trailing cells visible.
        if (actionIndex !== headers.length - 1) {
            const actionHeaderCell = headers[actionIndex];
            headerRow.appendChild(actionHeaderCell);

            Array.from(table.tBodies || []).forEach(tbody => {
                Array.from(tbody.rows || []).forEach(row => {
                    if (row.cells[actionIndex]) {
                        row.appendChild(row.cells[actionIndex]);
                    }
                });
            });

            headers = Array.from(headerRow.cells || []);
            actionIndex = headers.length - 1;
        }

        const actionHeader = headers[actionIndex];
        actionHeader.classList.add('table-actions-head');
        const actionHeaderText = (actionHeader.textContent || '').trim();
        if (actionHeaderText === '' || actionHeaderText.toLowerCase() === 'actions') {
            actionHeader.textContent = 'Action';
        }

        Array.from(table.tBodies || []).forEach(tbody => {
            Array.from(tbody.rows || []).forEach(row => {
                if (row.cells[actionIndex]) {
                    row.cells[actionIndex].classList.add('table-actions-cell');
                }
            });
        });
    }

    function ensureCustomTableShell(table) {
        if (!table || !table.classList || table.closest('.modal')) {
            return;
        }

        table.classList.add('app-table');

        const currentWrapper = table.closest('.table-responsive, .table-wrapper, .datatable-container, .app-table-scroll');
        if (currentWrapper) {
            currentWrapper.classList.add('app-table-scroll');
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive app-table-scroll';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    }

    function ensureActionGroup(actionCell) {
        if (!actionCell || actionCell.querySelector(':scope > .app-action-group')) {
            return;
        }

        const actionableNodes = Array.from(actionCell.childNodes).filter(node => {
            if (node.nodeType === Node.TEXT_NODE) {
                return node.textContent.trim() !== '';
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return false;
            }

            return node.matches('a.btn, button.btn, form, .btn, .d-inline, .d-inline-block');
        });

        if (actionableNodes.length <= 1) {
            return;
        }

        const group = document.createElement('div');
        group.className = 'app-action-group';

        actionableNodes.forEach(node => {
            group.appendChild(node);
        });

        actionCell.appendChild(group);
    }

    function stabilizeActionColumnWidth(table) {
        const headerRow = getHeaderRow(table);
        if (!headerRow) {
            return;
        }

        const headers = Array.from(headerRow.cells || []);
        const actionIndex = headers.findIndex(th => th.classList.contains('table-actions-head'));
        const actionWidth = 224;
        const columnWidth = 144;
        const columnCount = headers.length;

        if (columnCount === 0) {
            return;
        }

        const nonActionColumns = actionIndex >= 0 ? Math.max(0, columnCount - 1) : columnCount;
        const minTableWidth = Math.max(720, (nonActionColumns * columnWidth) + (actionIndex >= 0 ? actionWidth : 0));

        table.style.setProperty('--app-table-min-width', `${minTableWidth}px`);
        table.style.setProperty('--app-table-action-width', `${actionWidth}px`);

        let colgroup = table.querySelector(':scope > colgroup[data-app-table-layout="1"]');
        if (!colgroup) {
            colgroup = document.createElement('colgroup');
            colgroup.dataset.appTableLayout = '1';
            table.insertBefore(colgroup, table.firstChild);
        }

        while (colgroup.children.length < columnCount) {
            colgroup.appendChild(document.createElement('col'));
        }

        while (colgroup.children.length > columnCount) {
            colgroup.removeChild(colgroup.lastElementChild);
        }

        Array.from(colgroup.children).forEach((col, index) => {
            col.className = index === actionIndex ? 'app-table-action-col' : '';
            col.style.width = index === actionIndex ? `${actionWidth}px` : '';
        });

        if (actionIndex < 0) {
            return;
        }

        const stabilizedWidthPx = `${actionWidth}px`;
        const headerCell = headers[actionIndex];

        if (headerCell) {
            headerCell.style.width = stabilizedWidthPx;
            headerCell.style.minWidth = stabilizedWidthPx;
            headerCell.style.maxWidth = stabilizedWidthPx;
        }

        Array.from(table.tBodies || []).forEach(tbody => {
            Array.from(tbody.rows || []).forEach(row => {
                const actionCell = row.cells[actionIndex];
                if (!actionCell) {
                    return;
                }
                actionCell.style.width = stabilizedWidthPx;
                actionCell.style.minWidth = stabilizedWidthPx;
                actionCell.style.maxWidth = stabilizedWidthPx;
                ensureActionGroup(actionCell);
            });
        });
    }

    function attachTableSorting(table) {
        const headerRow = getHeaderRow(table);
        if (!headerRow) {
            return;
        }

        const isServerSortTable = table.dataset.serverSort === '1';
        const isServerPageSortTable = table.dataset.serverPageSort === '1';
        const activeSort = new URLSearchParams(window.location.search).get('sort') || '';
        const activeDirection = (new URLSearchParams(window.location.search).get('direction') || '').toLowerCase() === 'asc' ? 'asc' : 'desc';
        const headers = Array.from(headerRow.cells || []);
        headers.forEach((th, index) => {
            if (th.dataset.sortEnhanced === '1') {
                return;
            }

            const isActionColumn = (th.textContent || '').trim().toLowerCase().includes('action');
            if (isActionColumn) {
                return;
            }

            // Never enhance selection/checkbox columns or headers that opt out.
            if (th.dataset.noSort === '1' || th.querySelector('input[type="checkbox"]')) {
                return;
            }

            if (isServerPageSortTable && !(th.dataset.sortKey || '').trim()) {
                return;
            }

            const originalContent = document.createElement('span');
            originalContent.className = 'table-sort-label';
            while (th.firstChild) {
                originalContent.appendChild(th.firstChild);
            }

            const explicitSortKey = (th.dataset.sortKey || '').trim();
            const sortKey = explicitSortKey || (isServerSortTable ? '' : getSortKey(th, originalContent));
            if (sortKey) {
                th.dataset.sortKey = sortKey;
            }

            const sortBtn = document.createElement('button');
            sortBtn.type = 'button';
            sortBtn.className = 'table-sort-btn';
            sortBtn.setAttribute('aria-label', 'Sort by ' + (originalContent.textContent || 'column').trim());

            const isActiveServerSort = isServerPageSortTable && activeSort === sortKey;
            sortBtn.setAttribute('data-direction', isActiveServerSort ? activeDirection : 'none');
            sortBtn.innerHTML = isActiveServerSort
                ? (activeDirection === 'asc' ? '<i data-lucide="chevron-up"></i>' : '<i data-lucide="chevron-down"></i>')
                : '<i data-lucide="chevrons-up"></i>';

            if (isActiveServerSort) {
                th.classList.add('table-sort-active');
            }

            sortBtn.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                const currentDirection = sortBtn.getAttribute('data-direction');
                const nextDirection = currentDirection === 'asc' ? 'desc' : 'asc';

                headers.forEach(headerCell => {
                    const btn = headerCell.querySelector('.table-sort-btn');
                    if (!btn) {
                        return;
                    }
                    btn.setAttribute('data-direction', 'none');
                    btn.innerHTML = '<i data-lucide="chevrons-up"></i>';
                });

                sortBtn.setAttribute('data-direction', nextDirection);
                sortBtn.innerHTML = nextDirection === 'asc'
                    ? '<i data-lucide="chevron-up"></i>'
                    : '<i data-lucide="chevron-down"></i>';

                if (isServerSortTable) {
                    table.dispatchEvent(new CustomEvent('welheim:table-sort', {
                        detail: {
                            tableId: table.id || '',
                            sortKey: sortKey,
                            direction: nextDirection,
                            columnIndex: index,
                        }
                    }));
                } else if (isServerPageSortTable) {
                    sortServerRenderedTable(table, sortKey, nextDirection);
                } else {
                    sortTableByColumn(table, index, nextDirection);
                    applyActionColumnClasses(table);
                    stabilizeActionColumnWidth(table);
                }

                if (typeof window.refreshLucideIcons === 'function') {
                    window.refreshLucideIcons();
                }
            });

            originalContent.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                sortBtn.click();
            });

            th.appendChild(originalContent);
            th.appendChild(sortBtn);
            th.classList.add('table-sortable');
            th.dataset.sortEnhanced = '1';
        });
    }

    function autoFormatCells(table) {
        if (!table || !table.tBodies) {
            return;
        }

        const STATUS_MAP = {
            'active': 'active',
            'inactive': 'inactive',
            'expired': 'expired',
            'pending': 'pending',
            'cancelled': 'cancelled',
            'canceled': 'cancelled',
            'paid': 'paid',
            'unpaid': 'unpaid',
            'approved': 'approved',
            'rejected': 'rejected',
            'claimed': 'claimed',
            'closed': 'closed',
            'submitted': 'submitted',
            'processing': 'processing',
            'void': 'void',
        };

        Array.from(table.tBodies).forEach(function(tbody) {
            Array.from(tbody.rows).forEach(function(row) {
                Array.from(row.cells).forEach(function(cell) {
                    if (cell.classList.contains('table-actions-cell')) {
                        return;
                    }
                    if (cell.dataset.autoFormatted === '1') {
                        return;
                    }
                    if (cell.querySelector('.badge-status, .btn, a[href], form')) {
                        return;
                    }

                    const rawText = (cell.textContent || '').trim();
                    const lowerText = rawText.toLowerCase();

                    if (Object.prototype.hasOwnProperty.call(STATUS_MAP, lowerText)) {
                        const statusClass = STATUS_MAP[lowerText];
                        cell.innerHTML = `<span class="badge badge-status badge-${statusClass}">${rawText}</span>`;
                        cell.dataset.autoFormatted = '1';
                    }
                });
            });
        });
    }

    function enhanceAllTables() {
        document.querySelectorAll('.main-content table.table').forEach(table => {
            if (table.closest('.modal') || table.dataset.noTableEnhance === '1') {
                return;
            }

            ensureCustomTableShell(table);
            applyActionColumnClasses(table);
            attachTableSorting(table);
            stabilizeActionColumnWidth(table);
            autoFormatCells(table);
        });

        if (typeof window.refreshLucideIcons === 'function') {
            window.refreshLucideIcons();
        }
    }

    window.WelheimUI.enhanceTables = enhanceAllTables;
    enhanceAllTables();

    const ajaxTableSelector = '.main-content .card, .main-content [data-ajax-table-container]';
    let ajaxTableRequest = null;
    let searchDebounceTimer = null;

    function getAjaxTableContainers(root) {
        return Array.from((root || document).querySelectorAll(ajaxTableSelector))
            .filter(container => container.querySelector('table'))
            .filter((container, index, containers) => containers.indexOf(container) === index);
    }

    function markAjaxTableContainers(root) {
        getAjaxTableContainers(root).forEach((container, index) => {
            container.dataset.ajaxTableContainer = '1';
            container.dataset.ajaxTableIndex = String(index);

            container.querySelectorAll('form[method="GET"], form:not([method])').forEach(form => {
                if (!form.closest('[data-ajax-table-container="1"]')) {
                    return;
                }
                form.querySelectorAll('select[onchange]').forEach(select => {
                    select.removeAttribute('onchange');
                });
            });
        });
    }

    function updateBrowserUrl(url, replaceState) {
        if (!window.history || !window.history.pushState) {
            return;
        }

        const nextUrl = new URL(url, window.location.href);
        const currentUrl = new URL(window.location.href);
        if (nextUrl.pathname !== currentUrl.pathname) {
            return;
        }

        const state = { ajaxTable: true, url: nextUrl.toString() };
        if (replaceState) {
            window.history.replaceState(state, '', nextUrl.toString());
            return;
        }

        window.history.pushState(state, '', nextUrl.toString());
    }

    function setAjaxTableLoading(container, isLoading) {
        if (!container) {
            return;
        }

        container.classList.toggle('ajax-table-loading', isLoading);
        container.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function loadTableUrl(url, sourceElement, options) {
        const targetContainer = sourceElement
            ? sourceElement.closest('[data-ajax-table-container="1"]')
            : document.querySelector('[data-ajax-table-container="1"]');

        if (!targetContainer) {
            return false;
        }

        const requestUrl = new URL(url, window.location.href);
        if (requestUrl.origin !== window.location.origin) {
            return false;
        }

        const targetIndex = Number(targetContainer.dataset.ajaxTableIndex || 0);

        if (ajaxTableRequest) {
            ajaxTableRequest.abort();
        }

        ajaxTableRequest = new AbortController();
        setAjaxTableLoading(targetContainer, true);

        fetch(requestUrl.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: ajaxTableRequest.signal
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Unable to load table.');
                }
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                markAjaxTableContainers(doc);

                const replacement = getAjaxTableContainers(doc)[targetIndex] || getAjaxTableContainers(doc)[0];
                if (!replacement) {
                    window.location.assign(requestUrl.toString());
                    return;
                }

                targetContainer.replaceWith(document.importNode(replacement, true));
                markAjaxTableContainers(document);
                enhanceAllTables();
                updateBrowserUrl(requestUrl.toString(), options && options.replaceState);
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    return;
                }
                window.location.assign(requestUrl.toString());
            })
            .finally(() => {
                const currentContainer = document.querySelector(`[data-ajax-table-index="${targetIndex}"]`);
                setAjaxTableLoading(currentContainer, false);
                ajaxTableRequest = null;
            });

        return true;
    }

    function formUrl(form) {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        const formData = new FormData(form);
        const params = new URLSearchParams();

        formData.forEach((value, key) => {
            if (value !== null && String(value) !== '') {
                params.append(key, String(value));
            }
        });

        url.search = params.toString();
        return url;
    }

    function attachAjaxTableEvents() {
        markAjaxTableContainers(document);

        document.addEventListener('click', function(event) {
            const link = event.target.closest('[data-ajax-table-container="1"] .pagination a[href], [data-ajax-table-container="1"] .datatable-pagination a[href], [data-ajax-table-container="1"] a[data-ajax-table-link]');
            if (!link) {
                return;
            }

            const href = link.getAttribute('href');
            if (!href || href === '#') {
                return;
            }

            event.preventDefault();
            loadTableUrl(href, link);
        });

        document.addEventListener('submit', function(event) {
            const form = event.target.closest('[data-ajax-table-container="1"] form');
            if (!form || String(form.method || 'GET').toUpperCase() !== 'GET') {
                return;
            }

            event.preventDefault();
            loadTableUrl(formUrl(form).toString(), form);
        });

        document.addEventListener('change', function(event) {
            const control = event.target.closest('[data-ajax-table-container="1"] form select');
            if (!control || !control.form || String(control.form.method || 'GET').toUpperCase() !== 'GET') {
                return;
            }

            loadTableUrl(formUrl(control.form).toString(), control.form);
        });

        document.addEventListener('input', function(event) {
            const input = event.target.closest('[data-ajax-table-container="1"] form input[name="search"]');
            if (!input || !input.form) {
                return;
            }

            window.clearTimeout(searchDebounceTimer);
            searchDebounceTimer = window.setTimeout(function() {
                loadTableUrl(formUrl(input.form).toString(), input.form, { replaceState: true });
            }, 300);
        });

        window.addEventListener('popstate', function() {
            loadTableUrl(window.location.href, document.querySelector('[data-ajax-table-container="1"]'), { replaceState: true });
        });
    }

    window.WelheimUI.loadTableUrl = loadTableUrl;
    attachAjaxTableEvents();

    function mutationNeedsTableEnhance(mutations) {
        if (window.__isRefreshingLucideIcons) {
            return false;
        }

        return Array.from(mutations).some(function(mutation) {
            return Array.from(mutation.addedNodes || []).concat(Array.from(mutation.removedNodes || [])).some(function(node) {
                if (!node || node.nodeType !== Node.ELEMENT_NODE) {
                    return false;
                }

                if (node.matches('svg.lucide, svg.lucide *') || node.closest('svg.lucide')) {
                    return false;
                }

                return node.matches('table, thead, tbody, tfoot, tr, td, th, .table-responsive, .table-wrapper, .datatable-container, .app-table-scroll, [data-ajax-table-container], .card')
                    || Boolean(node.querySelector('table, thead, tbody, tfoot, tr, td, th'));
            });
        });
    }

    let enhanceTimer = null;
    const tableObserver = new MutationObserver(function(mutations) {
        if (!mutationNeedsTableEnhance(mutations)) {
            return;
        }

        if (enhanceTimer) {
            window.clearTimeout(enhanceTimer);
        }
        enhanceTimer = window.setTimeout(function() {
            enhanceAllTables();
        }, 120);
    });

    tableObserver.observe(document.body, {
        childList: true,
        subtree: true
    });
});
