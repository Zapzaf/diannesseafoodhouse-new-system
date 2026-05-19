/*!
    * Start Bootstrap - SB Admin Pro v2.0.5 (https://shop.startbootstrap.com/product/sb-admin-pro)
    * Copyright 2013-2023 Start Bootstrap
    * Licensed under SEE_LICENSE (https://github.com/StartBootstrap/sb-admin-pro/blob/master/LICENSE)
    */
    window.addEventListener('DOMContentLoaded', event => {
    // Activate feather
    feather.replace();

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

        html += createPageItem(currentPage + 1, 'Next', false, currentPage >= lastPage);
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

        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    function applyActionColumnClasses(table) {
        const headerRow = getHeaderRow(table);
        if (!headerRow) {
            return;
        }

        let headers = Array.from(headerRow.cells || []);
        let actionIndex = -1;

        headers.forEach((th, index) => {
            if ((th.textContent || '').trim().toLowerCase().includes('action')) {
                actionIndex = index;
            }
            th.classList.remove('table-actions-head');
        });

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

        headers[actionIndex].classList.add('table-actions-head');
        Array.from(table.tBodies || []).forEach(tbody => {
            Array.from(tbody.rows || []).forEach(row => {
                if (row.cells[actionIndex]) {
                    row.cells[actionIndex].classList.add('table-actions-cell');
                }
            });
        });
    }

    function stabilizeActionColumnWidth(table) {
        const headerRow = getHeaderRow(table);
        if (!headerRow) {
            return;
        }

        const headers = Array.from(headerRow.cells || []);
        const actionIndex = headers.findIndex(th => th.classList.contains('table-actions-head'));
        if (actionIndex < 0) {
            return;
        }

        const widthCandidates = [];
        const headerCell = headers[actionIndex];

        if (headerCell) {
            widthCandidates.push(Math.ceil(headerCell.scrollWidth));
        }

        Array.from(table.tBodies || []).forEach(tbody => {
            Array.from(tbody.rows || []).forEach(row => {
                const actionCell = row.cells[actionIndex];
                if (actionCell) {
                    widthCandidates.push(Math.ceil(actionCell.scrollWidth));
                }
            });
        });

        if (widthCandidates.length === 0) {
            return;
        }

        const stabilizedWidth = Math.max(108, Math.max.apply(null, widthCandidates));
        const stabilizedWidthPx = `${stabilizedWidth}px`;

        if (headerCell) {
            headerCell.style.width = '';
            headerCell.style.minWidth = '';
            headerCell.style.maxWidth = '';
        }

        Array.from(table.tBodies || []).forEach(tbody => {
            Array.from(tbody.rows || []).forEach(row => {
                const actionCell = row.cells[actionIndex];
                if (!actionCell) {
                    return;
                }
                actionCell.style.width = '';
                actionCell.style.minWidth = '';
                actionCell.style.maxWidth = '';
            });
        });
    }

    function attachTableSorting(table) {
        const headerRow = getHeaderRow(table);
        if (!headerRow) {
            return;
        }

        const isServerSortTable = table.dataset.serverSort === '1';
        const headers = Array.from(headerRow.cells || []);
        headers.forEach((th, index) => {
            if (th.dataset.sortEnhanced === '1') {
                return;
            }

            const isActionColumn = (th.textContent || '').trim().toLowerCase().includes('action');
            if (isActionColumn) {
                return;
            }

            const originalContent = document.createElement('span');
            originalContent.className = 'table-sort-label';
            while (th.firstChild) {
                originalContent.appendChild(th.firstChild);
            }

            const sortBtn = document.createElement('button');
            sortBtn.type = 'button';
            sortBtn.className = 'table-sort-btn';
            sortBtn.setAttribute('aria-label', 'Sort column');
            sortBtn.setAttribute('data-direction', 'none');
            sortBtn.innerHTML = '<i data-feather="chevrons-up"></i>';

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
                    btn.innerHTML = '<i data-feather="chevrons-up"></i>';
                });

                sortBtn.setAttribute('data-direction', nextDirection);
                sortBtn.innerHTML = nextDirection === 'asc'
                    ? '<i data-feather="chevron-up"></i>'
                    : '<i data-feather="chevron-down"></i>';

                if (isServerSortTable) {
                    const sortKey = (th.dataset.sortKey || '').trim();
                    table.dispatchEvent(new CustomEvent('welheim:table-sort', {
                        detail: {
                            tableId: table.id || '',
                            sortKey: sortKey,
                            direction: nextDirection,
                            columnIndex: index,
                        }
                    }));
                } else {
                    sortTableByColumn(table, index, nextDirection);
                    applyActionColumnClasses(table);
                    stabilizeActionColumnWidth(table);
                }

                if (typeof feather !== 'undefined') {
                    feather.replace();
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
        document.querySelectorAll('table.table').forEach(table => {
            attachTableSorting(table);
            applyActionColumnClasses(table);
            stabilizeActionColumnWidth(table);
            autoFormatCells(table);
        });

        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    window.WelheimUI.enhanceTables = enhanceAllTables;
    enhanceAllTables();

    let enhanceTimer = null;
    const tableObserver = new MutationObserver(function() {
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
