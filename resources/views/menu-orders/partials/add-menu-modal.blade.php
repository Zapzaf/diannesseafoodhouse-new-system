<div class="modal fade menu-order-modal" id="addMenuModal" tabindex="-1" aria-labelledby="addMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="{{ $formAction ?? '#' }}" method="{{ $formMethod ?? 'POST' }}" id="{{ $formId ?? 'addMenuItemsForm' }}" class="modal-content">
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo ?? 'show' }}">
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
                <div class="text-center py-5" id="modalMenuGridSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading menu items...</span>
                    </div>
                    <div class="text-muted small mt-2">Loading menu items...</div>
                </div>
                <div class="p-3 p-md-4 menu-order-modal-grid d-none" id="modalMenuGrid"></div>
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
