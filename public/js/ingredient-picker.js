window.IngredientPicker = (function () {
    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function visibleOptions(select) {
        return Array.from(select.options).filter((option, index) => index > 0 && !option.hidden);
    }

    function syncRow(row) {
        const combo = row.querySelector('.ingredient-combo');
        if (!combo) return;

        const select = combo.querySelector('.ingredient-select');
        const search = combo.querySelector('.ingredient-search');
        if (!select || !search) return;

        const selectedOption = select.selectedOptions[0];
        search.value = selectedOption && selectedOption.value ? selectedOption.textContent.trim() : '';
    }

    function closeMenu(combo) {
        const menu = combo.querySelector('.ingredient-dropdown-menu');
        if (menu) menu.classList.remove('show');
    }

    function renderMenu(combo, query) {
        const select = combo.querySelector('.ingredient-select');
        const menu = combo.querySelector('.ingredient-dropdown-menu');
        if (!select || !menu) return;

        const q = (query || '').trim().toLowerCase();
        const matches = visibleOptions(select).filter((option) => !q || option.textContent.toLowerCase().includes(q));

        menu.innerHTML = matches.length
            ? matches.map((option) => `<button type="button" class="dropdown-item ingredient-option" data-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent.trim())}</button>`).join('')
            : '<span class="dropdown-item-text text-muted small">No matching inventory items</span>';

        menu.classList.add('show');
    }

    function enhanceRow(row) {
        const combo = row.querySelector('.ingredient-combo');
        if (!combo || combo.dataset.enhanced === '1') return;
        combo.dataset.enhanced = '1';

        const select = combo.querySelector('.ingredient-select');
        const search = combo.querySelector('.ingredient-search');
        const menu = combo.querySelector('.ingredient-dropdown-menu');
        if (!select || !search || !menu) return;

        search.addEventListener('focus', () => renderMenu(combo, search.value));
        search.addEventListener('input', () => renderMenu(combo, search.value));

        search.addEventListener('blur', () => {
            window.setTimeout(() => {
                closeMenu(combo);
                syncRow(row);
            }, 150);
        });

        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu(combo);
                syncRow(row);
                search.blur();
            }
        });

        menu.addEventListener('mousedown', (event) => {
            const option = event.target.closest('.ingredient-option');
            if (!option) return;
            event.preventDefault();

            select.value = option.dataset.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncRow(row);
            closeMenu(combo);
        });

        syncRow(row);
    }

    function enhanceAll(container) {
        if (!container) return;
        container.querySelectorAll('.ingredient-row').forEach((row) => enhanceRow(row));
    }

    document.addEventListener('mousedown', (event) => {
        document.querySelectorAll('.ingredient-combo').forEach((combo) => {
            if (!combo.contains(event.target)) closeMenu(combo);
        });
    });

    return { enhanceRow, enhanceAll, syncRow };
})();
