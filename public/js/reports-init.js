window.ReportUtils = {
    initDataTable: function (selector) {
        if (!window.simpleDatatables) {
            return;
        }
        var table = document.querySelector(selector);
        if (!table) {
            return;
        }
        if (table.dataset.reportInitialized === '1') {
            return;
        }

        // simple-datatables throws when body rows use colspan placeholders.
        var hasColspanPlaceholder = Array.prototype.some.call(
            table.querySelectorAll('tbody tr td[colspan]'),
            function (cell) {
                return Number(cell.getAttribute('colspan') || '0') > 1;
            }
        );

        if (hasColspanPlaceholder) {
            return;
        }

        table.dataset.reportInitialized = '1';
        try {
            new simpleDatatables.DataTable(table, {
                perPage: 10,
                perPageSelect: [10, 25, 50, 100],
                searchable: true,
                sortable: true,
                fixedHeight: false,
            });
        } catch (error) {
            table.dataset.reportInitialized = '0';
            console.warn('Report table initialization skipped:', error);
        }
    },

    initPeriodFilter: function (formSelector) {
        var form = document.querySelector(formSelector);

        if (!form) {
            return;
        }

        var periodSelect = form.querySelector('[data-role="period-type"]');
        var weeklyInput = form.querySelector('[data-role="weekly-date"]');
        var dailyInput = form.querySelector('[data-role="daily-date"]');
        var monthlyInput = form.querySelector('[data-role="monthly-date"]');
        var yearlyInput = form.querySelector('[data-role="yearly-date"]');
        var startDateInput = form.querySelector('[data-role="start-date"]');
        var endDateInput = form.querySelector('[data-role="end-date"]');
        var resolvedDateFrom = form.querySelector('[data-role="resolved-date-from"]');
        var resolvedDateTo = form.querySelector('[data-role="resolved-date-to"]');
        var resolvedTargetDate = form.querySelector('[data-role="resolved-target-date"]');
        var weeklyGroup = form.querySelector('[data-role="weekly-group"]');
        var dailyGroup = form.querySelector('[data-role="daily-group"]');
        var monthlyGroup = form.querySelector('[data-role="monthly-group"]');
        var yearlyGroup = form.querySelector('[data-role="yearly-group"]');
        var startDateGroup = form.querySelector('[data-role="start-date-group"]');
        var endDateGroup = form.querySelector('[data-role="end-date-group"]');

        if (!form || !periodSelect || !startDateInput || !endDateInput || !resolvedDateFrom || !resolvedDateTo) {
            return;
        }

        var formatDate = function (date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');

            return year + '-' + month + '-' + day;
        };

        var parseWeekStart = function (weekValue) {
            if (!weekValue || weekValue.indexOf('-W') === -1) {
                return null;
            }

            var parts = weekValue.split('-W');
            var year = Number(parts[0]);
            var week = Number(parts[1]);
            var januaryFourth = new Date(year, 0, 4);
            var dayOfWeek = januaryFourth.getDay() || 7;
            var monday = new Date(januaryFourth);
            monday.setDate(januaryFourth.getDate() - dayOfWeek + 1 + ((week - 1) * 7));

            return monday;
        };

        var showGroup = function (group, shouldShow) {
            if (group) {
                group.style.display = shouldShow ? '' : 'none';
            }
        };

        var syncResolvedDates = function () {
            var period = periodSelect.value;
            var startDate;
            var endDate;

            if (period === 'daily') {
                startDate = dailyInput && dailyInput.value ? new Date(dailyInput.value) : new Date();
                endDate = new Date(startDate);
            } else if (period === 'weekly') {
                startDate = weeklyInput ? parseWeekStart(weeklyInput.value) : null;
                startDate = startDate || new Date();
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 6);
            } else if (period === 'monthly') {
                if (monthlyInput && monthlyInput.value) {
                    startDate = new Date(monthlyInput.value + '-01');
                } else {
                    startDate = new Date();
                    startDate = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
                }
                endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0);
            } else if (period === 'yearly') {
                var selectedYear = yearlyInput && yearlyInput.value ? Number(yearlyInput.value) : new Date().getFullYear();
                startDate = new Date(selectedYear, 0, 1);
                endDate = new Date(selectedYear, 11, 31);
            } else {
                startDate = startDateInput.value ? new Date(startDateInput.value) : new Date();
                endDate = endDateInput.value ? new Date(endDateInput.value) : new Date(startDate);
            }

            resolvedDateFrom.value = formatDate(startDate);
            resolvedDateTo.value = formatDate(endDate);

            if (resolvedTargetDate) {
                resolvedTargetDate.value = formatDate(endDate);
            }
        };

        var syncState = function () {
            var period = periodSelect.value;
            var isCustom = period === 'custom';

            showGroup(weeklyGroup, period === 'weekly');
            showGroup(dailyGroup, period === 'daily');
            showGroup(monthlyGroup, period === 'monthly');
            showGroup(yearlyGroup, period === 'yearly');
            showGroup(startDateGroup, isCustom);
            showGroup(endDateGroup, isCustom);

            syncResolvedDates();
        };

        periodSelect.addEventListener('change', syncState);

        [weeklyInput, dailyInput, monthlyInput, yearlyInput, startDateInput, endDateInput]
            .filter(Boolean)
            .forEach(function (input) {
                input.addEventListener('change', syncResolvedDates);
            });

        form.addEventListener('submit', syncResolvedDates);

        syncState();
    },
};
