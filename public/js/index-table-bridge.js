window.IndexTableBridge = (function () {
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(dateValue) {
        if (!dateValue) {
            return '—';
        }

        const date = new Date(dateValue);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        const hasTime = typeof dateValue === 'string' && (dateValue.includes('T') || dateValue.includes(' '));
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

    function renderPagination(paginationElement, data) {
        if (!paginationElement) {
            return;
        }

        let html = '';
        const currentPage = Number(data.current_page || 1);
        const lastPage = Number(data.last_page || 1);
        const pages = [];

        if (lastPage <= 1) {
            paginationElement.innerHTML = '';
            return;
        }

        const addControl = function (label, page, disabled, ariaLabel, extraClass) {
            const className = 'page-item pagination-control ' + (extraClass || '') + (disabled ? ' disabled' : '');
            const pageAttr = disabled ? '' : ' data-page="' + page + '"';
            const tabIndex = disabled ? ' tabindex="-1" aria-disabled="true"' : '';

            html += '<li class="' + className.trim() + '"><a class="page-link" href="#"' + pageAttr + tabIndex + ' aria-label="' + ariaLabel + '">' + label + '</a></li>';
        };

        addControl('<span aria-hidden="true">&laquo;</span><span class="page-link-label">First</span>', 1, currentPage === 1, 'First page', 'pagination-first');
        addControl('<span aria-hidden="true">&lsaquo;</span><span class="page-link-label">Prev</span>', currentPage - 1, currentPage === 1, 'Previous page', 'pagination-prev');

        for (let page = 1; page <= lastPage; page += 1) {
            if (
                lastPage <= 9
                || page <= 3
                || page >= lastPage - 1
                || Math.abs(page - currentPage) <= 1
            ) {
                pages.push(page);
            }
        }

        pages.forEach(function (page, index) {
            const previousPage = pages[index - 1];
            if (previousPage && page - previousPage > 1) {
                html += '<li class="page-item disabled pagination-gap"><span class="page-link" aria-hidden="true">&hellip;</span></li>';
            }

            html += '<li class="page-item pagination-page ' + (page === currentPage ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + page + '"' + (page === currentPage ? ' aria-current="page"' : '') + ' aria-label="Page ' + page + '">' + page + '</a></li>';
        });

        addControl('<span class="page-link-label">Next</span><span aria-hidden="true">&rsaquo;</span>', currentPage + 1, currentPage === lastPage, 'Next page', 'pagination-next');
        addControl('<span class="page-link-label">Last</span><span aria-hidden="true">&raquo;</span>', lastPage, currentPage === lastPage, 'Last page', 'pagination-last');

        paginationElement.innerHTML = html;
    }

    function updateInfo(infoElement, data) {
        if (!infoElement) {
            return;
        }

        const total = Number(data.total || 0);
        if (total === 0) {
            infoElement.textContent = 'No entries found';
            return;
        }

        const currentPage = Number(data.current_page || 1);
        const perPage = Number(data.per_page || 10);
        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, total);
        infoElement.textContent = 'Showing ' + start + ' to ' + end + ' of ' + total + ' entries';
    }

    function init(config) {
        const tbody = document.getElementById(config.tbodyId || 'tableBody');
        const pagination = document.getElementById(config.paginationId || 'pagination');
        const info = document.getElementById(config.infoId || 'tableInfo');
        const searchInput = config.searchInputId ? document.getElementById(config.searchInputId) : null;
        const filterInput = config.filterInputId ? document.getElementById(config.filterInputId) : null;
        const filters = (config.filters || []).map(function (filterConfig) {
            return {
                element: document.getElementById(filterConfig.inputId),
                param: filterConfig.param
            };
        }).filter(function (filterConfig) {
            return filterConfig.element && filterConfig.param;
        });
        const perPageInput = config.perPageId ? document.getElementById(config.perPageId) : null;
        const tableElement = document.getElementById(config.tableId);

        if (!tbody || !tableElement) {
            return;
        }

        let currentPage = 1;
        let perPage = perPageInput ? Number(perPageInput.value || config.perPage || 10) : Number(config.perPage || 10);
        let search = '';
        let filter = '';
        let sort = config.defaultSort || 'created_at';
        let direction = config.defaultDirection || 'desc';
        const sortColumns = config.sortColumns || [];

        // ===== List-state preservation (page, search, sort, filters, scroll) =====
        // State is mirrored into the current URL's query string so a normal
        // browser Back/Forward navigation (e.g. after following an edit link)
        // restores the exact same list state on reload, and the scroll offset
        // is remembered separately since it isn't part of the URL.
        const scrollStateKey = 'indexTableScroll:' + (config.stateKey || config.tableId);
        let hasRestoredScroll = false;

        (function restoreStateFromUrl() {
            const params = new URLSearchParams(window.location.search);

            if (params.has('page')) {
                const page = parseInt(params.get('page'), 10);
                if (Number.isInteger(page) && page > 0) currentPage = page;
            }

            if (params.has('per_page')) {
                const pp = parseInt(params.get('per_page'), 10);
                if (Number.isInteger(pp) && pp > 0) perPage = pp;
            }

            if (params.has('search')) {
                search = params.get('search') || '';
            }

            if (params.has('sort')) {
                sort = params.get('sort') || sort;
            }

            if (params.has('direction')) {
                direction = params.get('direction') === 'desc' ? 'desc' : 'asc';
            }

            if (config.filterParam && params.has(config.filterParam)) {
                filter = params.get(config.filterParam) || '';
            }

            // First pass: apply each restored value and dispatch a native
            // 'change' event so page-specific listeners can react (e.g. a
            // category filter repopulating a dependent subcategory <select>).
            // Second pass: re-apply the restored values once more, since a
            // cascade triggered above may have reset a dependent filter's
            // value/options after it was first set.
            filters.forEach(function (filterConfig) {
                if (params.has(filterConfig.param)) {
                    filterConfig.element.value = params.get(filterConfig.param) || '';
                    filterConfig.element.dispatchEvent(new Event('change'));
                }
            });

            filters.forEach(function (filterConfig) {
                if (params.has(filterConfig.param)) {
                    filterConfig.element.value = params.get(filterConfig.param) || '';
                }
            });

            if (searchInput) searchInput.value = search;

            // Only touch filterInput when the URL actually carries the param —
            // otherwise a fresh visit (no query string) would blank out
            // whatever default value/UI state the page itself set up.
            if (filterInput && config.filterParam && params.has(config.filterParam)) {
                filterInput.value = filter;
                filterInput.dispatchEvent(new Event('change'));
            }

            if (perPageInput) perPageInput.value = String(perPage);
        })();

        function syncUrl(params) {
            const url = window.location.pathname + '?' + params.toString();
            window.history.replaceState(window.history.state, '', url);
        }

        function saveScroll() {
            try {
                sessionStorage.setItem(scrollStateKey, String(window.scrollY));
            } catch (e) {
                // ignore - sessionStorage may be unavailable
            }
        }

        let scrollSaveDebounce = null;
        window.addEventListener('scroll', function () {
            clearTimeout(scrollSaveDebounce);
            scrollSaveDebounce = setTimeout(saveScroll, 150);
        }, { passive: true });

        function restoreScrollOnce() {
            if (hasRestoredScroll) return;
            hasRestoredScroll = true;

            let savedY = null;
            try {
                savedY = sessionStorage.getItem(scrollStateKey);
            } catch (e) {
                savedY = null;
            }

            if (savedY === null) return;

            const targetY = parseInt(savedY, 10);
            if (!Number.isInteger(targetY) || targetY <= 0) return;

            requestAnimationFrame(function () {
                window.scrollTo(0, targetY);
            });
        }

        let activeAbortController = null;

        function loadData() {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: perPage,
                search: search,
                sort: sort,
                direction: direction
            });

            if (config.filterParam && filter !== '') {
                params.set(config.filterParam, filter);
            }

            filters.forEach(function (filterConfig) {
                if (filterConfig.element.value !== '') {
                    params.set(filterConfig.param, filterConfig.element.value);
                }
            });

            syncUrl(params);

            // Cancel any still-in-flight request so fast typing/clicking
            // doesn't pile up overlapping fetches whose responses can land
            // out of order and thrash the table (this is what caused the
            // "search freezes the browser" issue).
            if (activeAbortController) {
                activeAbortController.abort();
            }
            activeAbortController = new AbortController();
            const thisRequestController = activeAbortController;

            fetch(config.dataUrl + '?' + params.toString(), { signal: thisRequestController.signal })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    const records = Array.isArray(data.data) ? data.data : [];

                    if (records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="' + config.colspan + '" class="text-center text-muted py-4">' + escapeHtml(config.emptyMessage || 'No records found') + '</td></tr>';
                        renderPagination(pagination, data);
                        updateInfo(info, data);
                        if (window.WelheimUI && typeof window.WelheimUI.enhanceTables === 'function') {
                            window.WelheimUI.enhanceTables();
                        }
                        restoreScrollOnce();
                        if (typeof config.onData === 'function') config.onData(data);
                        return;
                    }

                    tbody.innerHTML = records.map(function (record, index) {
                        return config.renderRow(record, {
                            index: ((Number(data.current_page || 1) - 1) * Number(data.per_page || perPage)) + index + 1,
                            escapeHtml: escapeHtml,
                            formatDate: formatDate
                        });
                    }).join('');

                    renderPagination(pagination, data);
                    updateInfo(info, data);

                    if (window.WelheimUI && typeof window.WelheimUI.enhanceTables === 'function') {
                        window.WelheimUI.enhanceTables();
                    }

                    if (typeof window.refreshLucideIcons === 'function') {
                        window.refreshLucideIcons();
                    }

                    restoreScrollOnce();

                    // Optional hook so a page can keep other UI (summary
                    // cards, totals) in sync with whatever this table's
                    // response carries beyond just its own rows/pagination.
                    if (typeof config.onData === 'function') config.onData(data);
                })
                .catch(function (error) {
                    // A superseded request was aborted on purpose — ignore it,
                    // the request that replaced it will render instead.
                    if (error && error.name === 'AbortError') return;
                    throw error;
                });
        }

        let searchDebounce = null;

        if (searchInput) {
            searchInput.addEventListener('input', function (event) {
                const value = event.target.value;
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(function () {
                    search = value;
                    currentPage = 1;
                    loadData();
                }, 300);
            });
        }

        if (filterInput) {
            filterInput.addEventListener('change', function (event) {
                filter = event.target.value;
                currentPage = 1;
                loadData();
            });
        }

        filters.forEach(function (filterConfig) {
            filterConfig.element.addEventListener('change', function () {
                currentPage = 1;
                loadData();
            });
        });

        if (perPageInput) {
            perPageInput.addEventListener('change', function (event) {
                perPage = Number(event.target.value);
                currentPage = 1;
                loadData();
            });
        }

        if (pagination) {
            pagination.addEventListener('click', function (event) {
                const clickedLink = event.target.closest('.page-link');
                const pageLink = event.target.closest('[data-page]');
                const page = pageLink ? pageLink.dataset.page : null;

                if (clickedLink) {
                    event.preventDefault();
                }

                if (!page) {
                    return;
                }

                const nextPage = parseInt(page, 10);
                if (!Number.isInteger(nextPage) || nextPage === currentPage) {
                    return;
                }

                currentPage = nextPage;
                saveScroll();
                loadData();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        if (typeof window !== 'undefined') {
            window.IndexTableBridgeReload = loadData;
        }

        tableElement.addEventListener('welheim:table-sort', function (event) {
            const columnIndex = event.detail && Number.isInteger(event.detail.columnIndex) ? event.detail.columnIndex : -1;
            const mappedSort = (event.detail && event.detail.sortKey ? String(event.detail.sortKey) : '') || sortColumns[columnIndex] || '';
            if (!mappedSort) {
                return;
            }

            sort = mappedSort;
            direction = event.detail && event.detail.direction === 'desc' ? 'desc' : 'asc';
            currentPage = 1;
            loadData();
        });

        loadData();
    }

    return {
        init: init
    };
})();
