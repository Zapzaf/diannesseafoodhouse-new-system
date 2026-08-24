/**
 * Builds the "Add Item" modal's menu grid client-side from a JSON endpoint,
 * instead of the server rendering every menu item's card (and image) into
 * the page on every load. That eager render — dozens of items and images,
 * whether or not the modal was ever opened — was the reported lag when
 * clicking "Add Item". Now the grid starts empty with a spinner, and this
 * fetches + builds the cards the first time the modal is actually opened.
 */
window.MenuOrderItemPicker = (function () {
    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function cardHtml(menu) {
        const price = Number(menu.price || 0).toFixed(2);
        const name = escapeHtml(menu.name);
        const imageUrl = menu.image_url || '';

        const imageBlock = imageUrl
            ? `<img src="${escapeHtml(imageUrl)}" alt="${name}" width="200" height="200" decoding="async" loading="lazy">`
            : `<div class="text-center text-muted">
                   <i data-lucide="image" style="width:32px; height:32px; opacity: 0.5; margin-bottom: 0.5rem;"></i>
                   <div class="small fw-medium">No Image</div>
               </div>`;

        const badge = menu.has_recipe
            ? `<span class="badge bg-success-subtle text-success menu-availability-badge border border-success-subtle" data-menu-badge="${menu.id}">Recipe ready</span>`
            : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">No recipe</span>`;

        return `
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card menu-modal-card h-100 border-0 shadow-sm" data-menu-id="${menu.id}"
                     data-menu-name="${name}"
                     data-menu-price="${price}"
                     data-menu-branch="${menu.branch_id}"
                     data-menu-image="${escapeHtml(imageUrl)}">
                    <div class="position-relative menu-card-img-wrapper" title="Click to add to order">
                        ${imageBlock}
                        <div class="position-absolute top-0 start-0 w-100 h-100 opacity-0 hover-overlay"></div>
                    </div>
                    <div class="card-body p-3 d-flex flex-column">
                        <div class="fw-bold text-dark text-truncate mb-1 menu-modal-name" title="${name}">${name}</div>
                        <div class="text-primary fw-bold mb-2">&#x20B1;${price}</div>
                        <div class="mb-3">${badge}</div>
                        <div class="mt-auto">
                            <div class="input-group input-group-sm">
                                <button class="btn btn-secondary px-2 btn-qty-minus text-white" type="button" aria-label="Decrease quantity"><i data-lucide="minus"></i></button>
                                <input type="number" class="form-control modal-item-qty text-center fw-bold" value="0" min="0" max="999">
                                <button class="btn btn-primary px-2 btn-qty-plus text-white" type="button" aria-label="Increase quantity"><i data-lucide="plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    /**
     * options: { grid: HTMLElement, spinner: HTMLElement|null, url: string, onDone: function(), onError: function()|undefined }
     * Fetches once per grid element and caches the built cards — reopening
     * the modal reuses what's already there instead of refetching.
     */
    function load(options) {
        const grid = options.grid;
        const spinner = options.spinner || null;
        const onDone = options.onDone || function () {};

        if (!grid) return;

        if (grid.dataset.loaded === '1') {
            onDone();
            return;
        }
        if (grid.dataset.loading === '1') {
            return;
        }

        grid.dataset.loading = '1';
        grid.classList.add('d-none');
        if (spinner) spinner.classList.remove('d-none');

        fetch(options.url, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (menus) {
                grid.innerHTML = '<div class="row g-3">' + menus.map(cardHtml).join('') + '</div>';
                grid.dataset.loaded = '1';
                grid.dataset.loading = '0';
                if (spinner) spinner.classList.add('d-none');
                grid.classList.remove('d-none');
                if (window.lucide) window.lucide.createIcons();
                onDone();
            })
            .catch(function (error) {
                grid.dataset.loading = '0';
                if (spinner) spinner.classList.add('d-none');
                grid.classList.remove('d-none');
                grid.innerHTML = '<div class="text-center text-danger py-4">Failed to load menu items. Close and reopen this window to try again.</div>';
                console.error('[menu-order-item-picker] failed to load menu items', error);
                if (typeof options.onError === 'function') options.onError(error);
            });
    }

    return { load: load };
})();
