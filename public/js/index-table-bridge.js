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

        if (currentPage > 1) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (currentPage - 1) + '">Previous</a></li>';
        }

        for (let page = 1; page <= lastPage; page += 1) {
            html += '<li class="page-item ' + (page === currentPage ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + page + '">' + page + '</a></li>';
        }

        if (currentPage < lastPage) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (currentPage + 1) + '">Next</a></li>';
        }

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

            fetch(config.dataUrl + '?' + params.toString())
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

                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function (event) {
                search = event.target.value;
                currentPage = 1;
                loadData();
            });
        }

        if (filterInput) {
            filterInput.addEventListener('change', function (event) {
                filter = event.target.value;
                currentPage = 1;
                loadData();
            });
        }
        
        if (perPageInput) {
            perPageInput.addEventListener('change', function (event) {
                perPage = Number(event.target.value);
                currentPage = 1;
                loadData();
            });
        }

        if (pagination) {
            pagination.addEventListener('click', function (event) {
                const page = event.target.dataset.page;
                if (!page) {
                    return;
                }

                event.preventDefault();
                currentPage = parseInt(page, 10);
                loadData();
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
