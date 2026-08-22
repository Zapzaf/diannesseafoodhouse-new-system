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

    // The dropdown lives in document.body (see enhance()) rather than nested
    // inside the combo, specifically so it isn't clipped when the combo sits
    // inside a scrolling container — e.g. a receipts table's .table-responsive,
    // which sets overflow-y: hidden and was cutting this dropdown off entirely
    // ("can't select a supplier, it doesn't even appear"). Its position is
    // recomputed from the search input's live position every time it opens.
    function positionMenu(combo) {
        const search = combo.querySelector('.supplier-search');
        const menu = combo._menuEl;
        if (!search || !menu) return;

        const rect = search.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = rect.bottom + 'px';
        menu.style.left = rect.left + 'px';
        menu.style.width = rect.width + 'px';
    }

    function closeMenu(combo) {
        const menu = combo._menuEl;
        if (menu) menu.classList.remove('show');
    }

    function renderMenu(combo, query) {
        const select = combo.querySelector('.supplier-select');
        const menu = combo._menuEl;
        if (!select || !menu) return;

        const q = (query || '').trim().toLowerCase();
        const matches = visibleOptions(select).filter((option) => !q || option.textContent.toLowerCase().includes(q));

        menu.innerHTML = matches.length
            ? matches.map((option) => `<button type="button" class="dropdown-item supplier-option" data-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent.trim())}</button>`).join('')
            : '<span class="dropdown-item-text text-muted small">No matching suppliers</span>';

        positionMenu(combo);
        menu.classList.add('show');
    }

    function enhance(combo) {
        if (!combo || combo.dataset.enhanced === '1') return;
        combo.dataset.enhanced = '1';

        const select = combo.querySelector('.supplier-select');
        const search = combo.querySelector('.supplier-search');
        const menu = combo.querySelector('.supplier-dropdown-menu');
        if (!select || !search || !menu) return;

        // Move the menu out to <body> so no ancestor's overflow/clipping can
        // ever hide it; keep a direct reference since it's no longer a
        // descendant of combo (combo.querySelector('.supplier-dropdown-menu')
        // would stop finding it after this).
        document.body.appendChild(menu);
        combo._menuEl = menu;

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

        // The menu now lives outside combo, so keep it anchored to the
        // search box while open — a scroll (e.g. inside the receipts table)
        // would otherwise leave it floating over the wrong spot.
        window.addEventListener('scroll', () => {
            if (menu.classList.contains('show')) positionMenu(combo);
        }, true);
        window.addEventListener('resize', () => {
            if (menu.classList.contains('show')) positionMenu(combo);
        });

        syncCombo(combo);
    }

    function enhanceAll(container) {
        (container || document).querySelectorAll('.supplier-combo').forEach((combo) => enhance(combo));
    }

    // Call before removing a combo's row/container from the DOM (e.g. a
    // "remove receipt row" button) — its dropdown lives in <body>, not
    // inside the combo, so deleting the row alone would leave it orphaned.
    function destroy(combo) {
        if (combo && combo._menuEl) combo._menuEl.remove();
    }

    function destroyAll(container) {
        (container || document).querySelectorAll('.supplier-combo').forEach((combo) => destroy(combo));
    }

    document.addEventListener('mousedown', (event) => {
        document.querySelectorAll('.supplier-combo').forEach((combo) => {
            const menu = combo._menuEl;
            const insideCombo = combo.contains(event.target);
            const insideMenu = menu && menu.contains(event.target);
            if (!insideCombo && !insideMenu) closeMenu(combo);
        });
    });

    document.addEventListener('DOMContentLoaded', () => enhanceAll(document));

    return { enhance, enhanceAll, syncCombo, destroy, destroyAll };
})();
