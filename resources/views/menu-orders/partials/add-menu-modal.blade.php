<div class="modal fade menu-order-modal" id="addMenuModal" tabindex="-1" aria-labelledby="addMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="{{ $formAction ?? '#' }}" method="{{ $formMethod ?? 'POST' }}" id="{{ $formId ?? 'addMenuItemsForm' }}" class="modal-content">
            @csrf
            <div id="{{ $fieldsContainerId ?? 'selectedMenuItemsFields' }}"></div>
            <div class="modal-header">
                <h5 class="modal-title" id="addMenuModalLabel"><i data-lucide="plus-circle" class="me-1"></i> Add Menu Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="menu-order-modal-toolbar">
                    <div class="row">
                        <div class="col-12 col-md-8 col-lg-6 mx-auto">
                            <div class="input-group input-group-lg menu-order-modal-search">
                                <span class="input-group-text"><i data-lucide="search"></i></span>
                                <input type="text" id="modalSearch" class="form-control" placeholder="Search menu items by name...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 p-md-4 menu-order-modal-grid" id="modalMenuGrid">
                    <div class="row g-3">
                        @foreach($menus as $menu)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card menu-modal-card h-100 border-0 shadow-sm" data-menu-id="{{ $menu->id }}"
                                 data-menu-name="{{ $menu->name }}"
                                 data-menu-price="{{ number_format((float) $menu->selling_price, 2, '.', '') }}"
                                 data-menu-branch="{{ $menu->branch_id }}"
                                 data-menu-image="{{ $menu->image ? asset('storage/' . $menu->image) : '' }}">
                                <div class="position-relative menu-card-img-wrapper" title="Click to add to order">
                                    @if($menu->image)
                                    <img src="{{ asset('storage/' . $menu->image) }}" style="width: 100%; height: 100%; object-fit: cover;">
                                    @else
                                    <div class="text-center text-muted">
                                        <i data-lucide="image" style="width:32px; height:32px; opacity: 0.5; margin-bottom: 0.5rem;"></i>
                                        <div class="small fw-medium">No Image</div>
                                    </div>
                                    @endif
                                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-0 hover-overlay"></div>
                                </div>

                                <div class="card-body p-3 d-flex flex-column">
                                    <div class="fw-bold text-dark text-truncate mb-1 menu-modal-name" title="{{ $menu->name }}">{{ $menu->name }}</div>
                                    <div class="text-primary fw-bold mb-2">&#x20B1;{{ number_format((float) $menu->selling_price, 2) }}</div>

                                    <div class="mb-3">
                                        @if($menu->items->isNotEmpty())
                                        <span class="badge bg-success-subtle text-success menu-availability-badge border border-success-subtle" data-menu-badge="{{ $menu->id }}">Recipe ready</span>
                                        @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">No recipe</span>
                                        @endif
                                    </div>

                                    <div class="mt-auto">
                                        <div class="input-group input-group-sm">
                                            <button class="btn btn-outline-secondary px-2 btn-qty-minus" type="button"><i data-lucide="minus" style="width:14px; height:14px;"></i></button>
                                            <input type="number" class="form-control modal-item-qty text-center fw-bold" value="0" min="0" max="999">
                                            <button class="btn btn-outline-primary px-2 btn-qty-plus" type="button"><i data-lucide="plus" style="width:14px; height:14px;"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <div id="modalError" class="alert alert-danger d-none mb-0">
                        <i data-lucide="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>Please enter a quantity of at least 1 for the selected items.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary text-light" data-bs-dismiss="modal">Cancel</button>
                <button type="{{ $submitType ?? 'submit' }}" class="btn btn-primary" id="addSelectedItemsBtn">
                    <i data-lucide="check-circle" class="me-1"></i> Add to Order
                </button>
            </div>
        </form>
    </div>
</div>
