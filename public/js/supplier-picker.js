window.SupplierPicker = (function () {
    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function visibleOptions(select) {
        return Array.from(select.options).filter((option, index) => index > 0 && !option.hidden);
    }

    function syncCombo(combo) {
        const select = combo.querySelector('.supplier-select');
        const search = combo.querySelector('.supplier-search');
        if (!select || !search) return;

        const selectedOption = select.selectedOptions[0];
        search.value = selectedOption && selectedOption.value ? selectedOption.textContent.trim() : '';
    }

    function closeMenu(combo) {
        const menu = combo.querySelector('.supplier-dropdown-menu');
        if (menu) menu.classList.remove('show');
    }

    function renderMenu(combo, query) {
        const select = combo.querySelector('.supplier-select');
        const menu = combo.querySelector('.supplier-dropdown-menu');
        if (!select || !menu) return;

        const q = (query || '').trim().toLowerCase();
        const matches = visibleOptions(select).filter((option) => !q || option.textContent.toLowerCase().includes(q));

        menu.innerHTML = matches.length
            ? matches.map((option) => `<button type="button" class="dropdown-item supplier-option" data-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent.trim())}</button>`).join('')
            : '<span class="dropdown-item-text text-muted small">No matching suppliers</span>';

        menu.classList.add('show');
    }

    function enhance(combo) {
        if (!combo || combo.dataset.enhanced === '1') return;
        combo.dataset.enhanced = '1';

        const select = combo.querySelector('.supplier-select');
        const search = combo.querySelector('.supplier-search');
        const menu = combo.querySelector('.supplier-dropdown-menu');
        if (!select || !search || !menu) return;

        search.addEventListener('focus', () => renderMenu(combo, search.value));
        search.addEventListener('input', () => renderMenu(combo, search.value));

        search.addEventListener('blur', () => {
            window.setTimeout(() => {
                closeMenu(combo);
                syncCombo(combo);
            }, 150);
        });

        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu(combo);
                syncCombo(combo);
                search.blur();
            }
        });

        menu.addEventListener('mousedown', (event) => {
            const option = event.target.closest('.supplier-option');
            if (!option) return;
            event.preventDefault();

            select.value = option.dataset.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncCombo(combo);
            closeMenu(combo);
        });

        syncCombo(combo);
    }

    function enhanceAll(container) {
        (container || document).querySelectorAll('.supplier-combo').forEach((combo) => enhance(combo));
    }

    document.addEventListener('mousedown', (event) => {
        document.querySelectorAll('.supplier-combo').forEach((combo) => {
            if (!combo.contains(event.target)) closeMenu(combo);
        });
    });

    document.addEventListener('DOMContentLoaded', () => enhanceAll(document));

    return { enhance, enhanceAll, syncCombo };
})();
