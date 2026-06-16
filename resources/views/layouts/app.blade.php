<!DOCTYPE html>
<html lang="en">
    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover" />
        <meta name="description" content="@yield('meta_description', 'Dianne Seafood House Inventory System')" />
        <meta name="author" content="Dianne Seafood House" />
        <meta name="keywords" content="@yield('meta_keywords', 'seafood, inventory, suppliers, reports, management')" />
        <meta property="og:title" content="@yield('page_title', 'Dashboard - Dianne Seafood House')" />
        <meta property="og:description" content="@yield('meta_description', 'Dianne Seafood House Inventory System')" />
        <meta property="og:type" content="website" />

        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#a22c29" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="Dianne Seafood House" />
        <meta name="application-name" content="Dianne Seafood House Inventory" />
        
        <title>@yield('page_title', 'Dashboard - Dianne Seafood House')</title>
        <!-- Setup CSS for Color Configurations -->
        <link href="{{ asset('css/setup.css') }}" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="{{ asset('css/custom-table.css') }}" rel="stylesheet" />
        <link href="{{ asset('css/styles.css') }}" rel="stylesheet" />
        <link href="{{ asset('css/theme-overrides.css') }}" rel="stylesheet" />
        <!-- Favicon and Icons -->
        <link rel="icon" type="image/x-icon" href="{{ asset('assets/icons/favicon.ico') }}" />
        <script data-search-pseudo-elements defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js" crossorigin="anonymous"></script>
        <style>
            .no-wrap {
                white-space: nowrap;
            }
            .dropdown-user .rounded-circle,
            .dropdown-user-img {
                object-fit: cover;
                object-position: center;
            }
            .dropdown-user .rounded-circle {
                width: 2.5rem;
                height: 2.5rem;
            }
            :root {
                --bs-primary: #a22c29;
                --bs-primary-rgb: 162, 44, 41;
                --bs-link-color: #a22c29;
                --bs-link-hover-color: #8b2522;
                --btn-primary-solid: #a22c29;
                --btn-primary-hover: #8b2522;
                --btn-secondary-solid: #4b5563;
                --btn-secondary-hover: #374151;
                --btn-success-solid: #1f7a4d;
                --btn-success-hover: #16613c;
                --btn-danger-solid: #b3261e;
                --btn-danger-hover: #8f1f18;
                --btn-warning-solid: #b45309;
                --btn-warning-hover: #92400e;
                --btn-info-solid: #0b7285;
                --btn-info-hover: #0a5969;
                --btn-light-solid: #f3f4f6;
                --btn-light-hover: #e5e7eb;
                --btn-dark-solid: #111827;
                --btn-dark-hover: #030712;
                --pagination-bg: #a22c29;
                --pagination-hover: #8b2522;
                --action-view-bg: #2563eb;
                --action-view-hover: #1d4ed8;
                --action-edit-bg: #d97706;
                --action-edit-hover: #b45309;
                --action-delete-bg: #dc2626;
                --action-delete-hover: #b91c1c;
            }
            .bg-gradient-primary-to-secondary {
                background: #a22c29 !important;
                background-image: none !important;
            }
            .page-header.page-header-dark {
                background: #a22c29 !important;
                background-image: none !important;
            }
            .btn-primary,
            .bg-primary {
                background-color: #a22c29 !important;
                border-color: #a22c29 !important;
            }
            .btn-primary:hover,
            .btn-primary:focus {
                background-color: #8b2522 !important;
                border-color: #8b2522 !important;
            }
            .text-primary {
                color: #a22c29 !important;
            }
            .border-primary {
                border-color: #a22c29 !important;
            }
            .topnav .navbar-brand {
                color: #a22c29 !important;
            }

            .btn {
                border-radius: 0.45rem;
                font-weight: 600;
                border-width: 1px;
                background-image: none !important;
                box-shadow: none;
                transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
            }

            .btn i,
            .btn svg,
            .btn .fas,
            .btn .far,
            .btn .fa,
            .btn [class^="fa-"] {
                color: currentColor !important;
                stroke: currentColor !important;
            }

            .btn-primary,
            .btn-outline-primary {
                background-color: var(--btn-primary-solid) !important;
                border-color: var(--btn-primary-solid) !important;
                color: #ffffff !important;
            }
            .btn-primary:hover,
            .btn-primary:focus,
            .btn-outline-primary:hover,
            .btn-outline-primary:focus {
                background-color: var(--btn-primary-hover) !important;
                border-color: var(--btn-primary-hover) !important;
                color: #ffffff !important;
            }

            .btn-secondary,
            .btn-outline-secondary,
            .btn-cancel {
                background-color: var(--btn-secondary-solid) !important;
                border-color: var(--btn-secondary-solid) !important;
                color: #ffffff !important;
            }
            .btn-secondary:hover,
            .btn-secondary:focus,
            .btn-outline-secondary:hover,
            .btn-outline-secondary:focus,
            .btn-cancel:hover,
            .btn-cancel:focus {
                background-color: var(--btn-secondary-hover) !important;
                border-color: var(--btn-secondary-hover) !important;
                color: #ffffff !important;
            }

            .btn-success,
            .btn-outline-success {
                background-color: var(--btn-success-solid) !important;
                border-color: var(--btn-success-solid) !important;
                color: #ffffff !important;
            }
            .btn-success:hover,
            .btn-success:focus,
            .btn-outline-success:hover,
            .btn-outline-success:focus {
                background-color: var(--btn-success-hover) !important;
                border-color: var(--btn-success-hover) !important;
                color: #ffffff !important;
            }

            .btn-danger,
            .btn-outline-danger {
                background-color: var(--btn-danger-solid) !important;
                border-color: var(--btn-danger-solid) !important;
                color: #ffffff !important;
            }
            .btn-danger:hover,
            .btn-danger:focus,
            .btn-outline-danger:hover,
            .btn-outline-danger:focus {
                background-color: var(--btn-danger-hover) !important;
                border-color: var(--btn-danger-hover) !important;
                color: #ffffff !important;
            }

            .btn-warning,
            .btn-outline-warning {
                background-color: var(--btn-warning-solid) !important;
                border-color: var(--btn-warning-solid) !important;
                color: #ffffff !important;
            }
            .btn-warning:hover,
            .btn-warning:focus,
            .btn-outline-warning:hover,
            .btn-outline-warning:focus {
                background-color: var(--btn-warning-hover) !important;
                border-color: var(--btn-warning-hover) !important;
                color: #ffffff !important;
            }

            .btn-info,
            .btn-outline-info {
                background-color: var(--btn-info-solid) !important;
                border-color: var(--btn-info-solid) !important;
                color: #ffffff !important;
            }
            .btn-info:hover,
            .btn-info:focus,
            .btn-outline-info:hover,
            .btn-outline-info:focus {
                background-color: var(--btn-info-hover) !important;
                border-color: var(--btn-info-hover) !important;
                color: #ffffff !important;
            }

            .btn-dark,
            .btn-outline-dark {
                background-color: var(--btn-dark-solid) !important;
                border-color: var(--btn-dark-solid) !important;
                color: #ffffff !important;
            }
            .btn-dark:hover,
            .btn-dark:focus,
            .btn-outline-dark:hover,
            .btn-outline-dark:focus {
                background-color: var(--btn-dark-hover) !important;
                border-color: var(--btn-dark-hover) !important;
                color: #ffffff !important;
            }

            .btn-light,
            .btn-outline-light {
                background-color: var(--btn-light-solid);
                border-color: #d1d5db;
                color: #111827;
            }
            .btn-light:hover,
            .btn-light:focus,
            .btn-outline-light:hover,
            .btn-outline-light:focus {
                background-color: var(--btn-light-hover);
                border-color: #9ca3af;
                color: #111827;
            }
            .btn-light i,
            .btn-light svg,
            .btn-outline-light i,
            .btn-outline-light svg {
                color: #a22c29 !important;
                stroke: #a22c29 !important;
            }

            :root {
                --report-chart-height: 28rem;
            }

            .report-chart-card .card-body {
                height: var(--report-chart-height);
                min-height: var(--report-chart-height);
                display: flex;
                flex-direction: column;
            }

            .report-chart-frame {
                position: relative;
                width: 100%;
                height: 100%;
                min-height: 0;
            }

            .report-chart-card .table-responsive {
                flex: 1 1 auto;
                min-height: 0;
                overflow: auto;
            }

            .report-chart-frame canvas {
                width: 100% !important;
                height: 100% !important;
                display: block;
            }

            @media (max-width: 767.98px) {
                :root {
                    --report-chart-height: 22rem;
                }
            }

            .pagination .page-link {
                background-color: var(--pagination-bg);
                border-color: var(--pagination-bg);
                color: #ffffff;
                font-weight: 600;
                min-width: 2.25rem;
                text-align: center;
            }
            .pagination .page-link:hover,
            .pagination .page-link:focus {
                background-color: var(--pagination-hover);
                border-color: var(--pagination-hover);
                color: #ffffff;
            }
            .pagination .page-item.active .page-link {
                background-color: #111827;
                border-color: #111827;
                color: #ffffff;
            }
            .pagination .page-item.disabled .page-link {
                background-color: #9ca3af;
                border-color: #9ca3af;
                color: #ffffff;
                opacity: 0.75;
            }

            .pagination .pagination-ellipsis {
                pointer-events: none;
                min-width: 2.25rem;
            }

            .pagination .page-jump-item {
                margin-left: 0.5rem;
            }

            .pagination .page-jump-wrap {
                display: flex;
                gap: 0.4rem;
                align-items: center;
                min-width: auto;
            }

            .pagination .page-jump-wrap .form-control {
                min-width: 2.5rem;
                width: 2.5rem;
                max-width: 2.5rem;
                border-color: #9ca3af;
                height: calc(2.25rem + 2px);
                text-align: center;
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }

            .pagination .page-jump-wrap .btn {
                min-width: 2.5rem;
                height: calc(2.25rem + 2px);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .table td,
            .table th {
                white-space: nowrap;
            }

            .table th.table-sortable {
                vertical-align: middle;
            }

            .table th.table-sortable .table-sort-label {
                display: inline-block;
                vertical-align: middle;
            }

            .table th.table-sortable .table-sort-btn {
                border: 0;
                background-color: transparent;
                color: #ffffff;
                border-radius: 0.35rem;
                line-height: 1;
                margin-left: 0.35rem;
                min-width: 1.6rem;
                height: 1.6rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 0.25rem;
                vertical-align: middle;
            }

            .table th.table-sortable .table-sort-btn i,
            .table th.table-sortable .table-sort-btn svg {
                width: 0.95rem;
                height: 0.95rem;
                color: #ffffff !important;
                stroke: #ffffff !important;
            }

            .table th.table-sortable .table-sort-btn:hover,
            .table th.table-sortable .table-sort-btn:focus {
                background-color: transparent;
                border-color: transparent;
                color: #ffffff;
                opacity: 0.82;
            }

            .table th.table-sortable .table-sort-btn[data-direction='asc'],
            .table th.table-sortable .table-sort-btn[data-direction='desc'] {
                background-color: transparent;
                border-color: transparent;
                color: #ffffff;
            }

            .table .table-actions-head,
            .table .table-actions-cell {
                text-align: center !important;
            }

            .table .table-actions-head,
            .table .table-actions-cell {
                position: sticky;
                right: 0;
                z-index: 3;
                background-color: #ffffff;
                background-clip: padding-box;
                box-sizing: border-box;
            }

            .table .table-actions-head {
                z-index: 4;
            }

            .table.table-striped > tbody > tr:nth-of-type(odd) .table-actions-cell {
                background-color: #f8f9fc;
            }

            .table .table-actions-head::before,
            .table .table-actions-cell::before {
                content: '';
                position: absolute;
                left: -0.5rem;
                top: 0;
                bottom: 0;
                pointer-events: none;
                background: linear-gradient(to left, rgba(0, 0, 0, 0.12), rgba(0, 0, 0, 0));
            }


            .table-responsive {
                position: relative;
                border: 1px solid #e2d8d3;
                border-radius: 0.5rem;
                overflow-x: auto;
            }

            .table td .action-btns {
                display: inline-flex;
                flex-wrap: wrap;
                gap: 0.35rem;
                align-items: center;
                justify-content: center;
                width: 100%;
            }

            .table .table-actions-cell .btn,
            .table .table-actions-cell form,
            .table .table-actions-cell .d-inline,
            .table .table-actions-cell .d-inline-block {
                display: inline-block;
                vertical-align: middle;
            }

            .table td .btn,
            .table td .d-inline,
            .table td .d-inline-block,
            .table td form {
                white-space: nowrap;
            }

            .table td form.d-inline {
                display: inline-block !important;
                margin: 0;
            }

            .table td .btn[title='View' i],
            .table td .btn[title='Edit' i],
            .table td .btn[title='Delete' i] {
                border-width: 1px;
            }

            .table td .btn[title='View' i] {
                background-color: var(--action-view-bg) !important;
                border-color: var(--action-view-bg) !important;
                color: #ffffff !important;
            }

            .table td .btn[title='View' i]:hover,
            .table td .btn[title='View' i]:focus {
                background-color: var(--action-view-hover) !important;
                border-color: var(--action-view-hover) !important;
                color: #ffffff !important;
            }

            .table td .btn[title='Edit' i] {
                background-color: var(--action-edit-bg) !important;
                border-color: var(--action-edit-bg) !important;
                color: #ffffff !important;
            }

            .table td .btn[title='Edit' i]:hover,
            .table td .btn[title='Edit' i]:focus {
                background-color: var(--action-edit-hover) !important;
                border-color: var(--action-edit-hover) !important;
                color: #ffffff !important;
            }

            .table td .btn[title='Delete' i] {
                background-color: var(--action-delete-bg) !important;
                border-color: var(--action-delete-bg) !important;
                color: #ffffff !important;
            }

            .table td .btn[title='Delete' i]:hover,
            .table td .btn[title='Delete' i]:focus {
                background-color: var(--action-delete-hover) !important;
                border-color: var(--action-delete-hover) !important;
                color: #ffffff !important;
            }

            .table td .btn[title='View' i] i,
            .table td .btn[title='View' i] svg,
            .table td .btn[title='Edit' i] i,
            .table td .btn[title='Edit' i] svg,
            .table td .btn[title='Delete' i] i,
            .table td .btn[title='Delete' i] svg {
                color: #ffffff !important;
                stroke: #ffffff !important;
            }

            /* ---- Table visual design enhancements ---- */
            .table {
                font-size: 0.875rem;
            }

            /* Horizontal-only borders — remove left/right column dividers */
            .table.table-bordered > :not(caption) > * > * {
                border-left-width: 0 !important;
                border-right-width: 0 !important;
            }

            /* Thead — uppercase compact labels */
            .table thead th {
                font-size: 0.73rem;
                font-weight: 700;
                letter-spacing: 0.07em;
                text-transform: uppercase;
                padding: 0.85rem 1rem;
            }

            /* Tbody — comfortable padding + vertical centering */
            .table tbody td {
                padding: 0.6rem 1rem;
                vertical-align: middle;
                transition: background-color 0.12s ease;
            }

            /* Row hover — all tables, no extra class needed */
            .table tbody tr:hover > td,
            .table tbody tr:hover > th {
                background-color: #f3ece8 !important;
            }

            .table tbody tr:hover .table-actions-cell {
                background-color: #f3ece8 !important;
            }

            /* ---- Auto-status badges ---- */
            .badge-status {
                display: inline-block;
                font-size: 0.72rem;
                font-weight: 600;
                padding: 0.28em 0.7em;
                border-radius: 0.35rem;
                letter-spacing: 0.03em;
                white-space: nowrap;
                border: 1px solid transparent;
                background-image: none !important;
                opacity: 1 !important;
            }

            .badge-status.badge-active     { background-color: #dcfce7 !important; color: #166534 !important; border-color: #86efac !important; }
            .badge-status.badge-inactive   { background-color: #f1f5f9 !important; color: #475569 !important; border-color: #cbd5e1 !important; }
            .badge-status.badge-expired    { background-color: #fee2e2 !important; color: #991b1b !important; border-color: #fca5a5 !important; }
            .badge-status.badge-pending    { background-color: #fef9c3 !important; color: #854d0e !important; border-color: #fde68a !important; }
            .badge-status.badge-partial    { background-color: #ffedd5 !important; color: #9a3412 !important; border-color: #fdba74 !important; }
            .badge-status.badge-cancelled,
            .badge-status.badge-canceled   { background-color: #f3f4f6 !important; color: #374151 !important; border-color: #d1d5db !important; }
            .badge-status.badge-paid       { background-color: #dbeafe !important; color: #1e40af !important; border-color: #93c5fd !important; }
            .badge-status.badge-unpaid     { background-color: #fce7f3 !important; color: #9d174d !important; border-color: #f9a8d4 !important; }
            .badge-status.badge-approved   { background-color: #d1fae5 !important; color: #065f46 !important; border-color: #6ee7b7 !important; }
            .badge-status.badge-rejected   { background-color: #fee2e2 !important; color: #9f1239 !important; border-color: #fda4af !important; }
            .badge-status.badge-claimed    { background-color: #ede9fe !important; color: #5b21b6 !important; border-color: #c4b5fd !important; }
            .badge-status.badge-closed     { background-color: #f1f5f9 !important; color: #475569 !important; border-color: #cbd5e1 !important; }
            .badge-status.badge-submitted  { background-color: #e0f2fe !important; color: #075985 !important; border-color: #7dd3fc !important; }
            .badge-status.badge-processing { background-color: #fef3c7 !important; color: #92400e !important; border-color: #fcd34d !important; }
            .badge-status.badge-available  { background-color: #dcfce7 !important; color: #166534 !important; border-color: #86efac !important; }
            .badge-status.badge-occupied   { background-color: #fee2e2 !important; color: #991b1b !important; border-color: #fca5a5 !important; }
            .badge-status.badge-reserved   { background-color: #fef3c7 !important; color: #92400e !important; border-color: #fde68a !important; }
            .badge-status.badge-cleaning   { background-color: #e0f2fe !important; color: #075985 !important; border-color: #7dd3fc !important; }
            .badge-status.badge-maintenance { background-color: #ede9fe !important; color: #5b21b6 !important; border-color: #c4b5fd !important; }
            .badge-status.badge-void       { background-color: #fafafa !important; color: #6b7280 !important; border-color: #e5e7eb !important; }
            .badge-status.badge-confirmed  { background-color: #dbeafe !important; color: #1d4ed8 !important; border-color: #93c5fd !important; }
            .badge-status.badge-checked_in { background-color: #fee2e2 !important; color: #b91c1c !important; border-color: #fca5a5 !important; }
            .badge-status.badge-checked_out { background-color: #dcfce7 !important; color: #166534 !important; border-color: #86efac !important; }
            .badge-status.badge-open       { background-color: #e0f2fe !important; color: #075985 !important; border-color: #7dd3fc !important; }
            .badge-status.badge-completed  { background-color: #dcfce7 !important; color: #166534 !important; border-color: #86efac !important; }

            @media (max-width: 768px) {
                .card-footer {
                    flex-direction: column;
                    align-items: flex-start !important;
                    gap: 0.75rem;
                }

                .card-footer nav {
                    width: 100%;
                    overflow-x: auto;
                }

                .pagination {
                    flex-wrap: nowrap;
                    min-width: max-content;
                }

                .pagination .page-jump-wrap {
                    min-width: auto;
                }

                .table .table-actions-head,
                .table .table-actions-cell {
                    z-index: 5;
                }

            }
        </style>
        @stack('styles')
    </head>
    <body class="nav-fixed">
        <nav class="topnav navbar navbar-expand shadow justify-content-between justify-content-sm-start navbar-light bg-white" id="sidenavAccordion">
            <!-- Sidenav Toggle Button-->
            <button class="btn btn-icon btn-transparent-dark order-1 order-lg-0 me-2 ms-lg-2 me-lg-0" id="sidebarToggle"><i data-feather="menu"></i></button>
            <!-- Navbar Brand-->
            <a class="navbar-brand pe-3 ps-4 ps-lg-2" href="{{ route('dashboard') }}">
                <i data-feather="home" class=""></i>Dianne Seafood House
            </a>
            <!-- Branch Selector (Admin only) -->
            <!-- Navbar Items-->
            @php
                $authUser = auth()->user();
                $profilePhotoUrl = $authUser && $authUser->profile_photo_path
                    ? route('profile-photos.show', $authUser)
                    : asset('assets/img/illustrations/profiles/profile-1.png');
            @endphp
            <ul class="navbar-nav align-items-center ms-auto">
                <!-- User Dropdown-->
                <li class="nav-item dropdown no-caret dropdown-user me-3 me-lg-4">
                    <a class="btn btn-icon btn-transparent-dark dropdown-toggle" id="navbarDropdownUserImage" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <img class="rounded-circle" src="{{ $profilePhotoUrl }}" alt="User Profile" />
                    </a>
                    <div class="dropdown-menu dropdown-menu-end border-0 shadow animated--fade-in-up" aria-labelledby="navbarDropdownUserImage">
                        <h6 class="dropdown-header d-flex align-items-center">
                            <img class="dropdown-user-img" src="{{ $profilePhotoUrl }}" alt="User Profile" />
                            <div class="dropdown-user-details">
                                <div class="dropdown-user-details-name">{{ auth()->user()->name }}</div>
                                <div class="dropdown-user-details-email">{{ auth()->user()->email }}</div>
                            </div>
                        </h6>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('account.index') }}">
                            <div class="dropdown-item-icon"><i data-feather="settings"></i></div>
                            Account Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-start">
                                <div class="dropdown-item-icon"><i data-feather="log-out"></i></div>
                                Logout
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sidenav shadow-right sidenav-light">
                    <div class="sidenav-menu">
                        <div class="nav accordion" id="accordionSidenav">
                            @if(auth()->user()->isAdmin())
                            <div class="sidenav-menu-heading">Select Branch</div>
                            <div class="ms-3 me-3 d-block">
                                @php
                                    $branches = \App\Models\Branch::where('is_active', true)->get();
                                    $selectedBranchId = session('selected_branch_id');
                                    $selectedBranch = $branches->firstWhere('id', $selectedBranchId);
                                @endphp
                                <form action="{{ route('branch.select') }}" method="POST" class="d-inline-block w-100">
                                    @csrf
                                    <div class="btn-group w-100" role="group" aria-label="Branch selector">
                                        <button type="button" class="btn btn-primary btn-sm dropdown-toggle text-start d-flex align-items-center" style="min-width:0" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="text-truncate" style="max-width:140px">{{ $selectedBranch?->name ?? 'All Branches' }}</span>
                                        </button>
                                        <ul class="dropdown-menu w-100" style="max-height:220px;overflow-y:auto">
                                            <li>
                                                <button type="submit" name="branch_id" value="" class="dropdown-item text-wrap {{ empty($selectedBranchId) ? 'active' : '' }}" style="white-space:normal;word-break:break-word" title="All Branches">All Branches</button>
                                            </li>
                                            @foreach($branches as $br)
                                                <li>
                                                    <button type="submit" name="branch_id" value="{{ $br->id }}" class="dropdown-item text-wrap {{ (string) $selectedBranchId === (string) $br->id ? 'active' : '' }}" style="white-space:normal;word-break:break-word" title="{{ $br->name }}">{{ $br->name }}</button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </form>
                            </div>
                            @endif
                            <!-- Core Section -->
                            <div class="sidenav-menu-heading">Core</div>
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <div class="nav-link-icon"><i data-feather="activity"></i></div>
                                Dashboard
                            </a>
                        
                            <!-- Inventory Management Section -->
                            <div class="sidenav-menu-heading">Inventory Management</div>

                            <!-- Items/Inventory -->
                            <a class="nav-link collapsed {{ request()->routeIs('items.*') || request()->routeIs('inventory.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseItems" aria-expanded="{{ request()->routeIs('items.*') || request()->routeIs('inventory.*') ? 'true' : 'false' }}" aria-controls="collapseItems">
                                <div class="nav-link-icon"><i data-feather="package"></i></div>
                                Items
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('items.*') || request()->routeIs('inventory.*') ? 'show' : '' }}" id="collapseItems" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('items.index') || request()->routeIs('inventory.index') ? 'active' : '' }}" href="{{ route('items.index') }}">All Items</a>
                                    <a class="nav-link {{ request()->routeIs('items.create') || request()->routeIs('inventory.create') ? 'active' : '' }}" href="{{ route('items.create') }}">Add New Item</a>
                                    <a class="nav-link {{ request()->routeIs('items.low-stock') ? 'active' : '' }}" href="{{ route('items.low-stock') }}">Low Stock Alerts</a>
                                </nav>
                            </div>

                            <a class="nav-link collapsed {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseLocationCategories" aria-expanded="{{ request()->routeIs('categories.*') ? 'true' : 'false' }}" aria-controls="collapseLocationCategories">
                                <div class="nav-link-icon"><i data-feather="folder"></i></div>
                                Locations
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('categories.*') ? 'show' : '' }}" id="collapseLocationCategories" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('categories.all') || request()->routeIs('categories.module') ? 'active' : '' }}" href="{{ route('categories.all') }}">All Locations</a>
                                </nav>
                            </div>

                            <!-- Transactions -->
                            <a class="nav-link collapsed {{ request()->routeIs('transactions.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseTransactions" aria-expanded="{{ request()->routeIs('transactions.*') ? 'true' : 'false' }}" aria-controls="collapseTransactions">
                                <div class="nav-link-icon"><i data-feather="list"></i></div>
                                Transactions
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('transactions.*') ? 'show' : '' }}" id="collapseTransactions" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('transactions.index') ? 'active' : '' }}" href="{{ route('transactions.index') }}">All Transactions</a>
                                    <a class="nav-link {{ request()->routeIs('transactions.create') ? 'active' : '' }}" href="{{ route('transactions.create') }}">New Transaction</a>
                                    <a class="nav-link {{ request()->routeIs('transactions.pending') ? 'active' : '' }}" href="{{ route('transactions.pending') }}">Pending Approval</a>
                                </nav>
                            </div>

                            <!-- Restaurant Section -->
                            <div class="sidenav-menu-heading">Restaurant</div>

                            <!-- Menu Management -->
                            <a class="nav-link collapsed {{ request()->routeIs('menus.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseMenu" aria-expanded="{{ request()->routeIs('menus.*') ? 'true' : 'false' }}" aria-controls="collapseMenu">
                                <div class="nav-link-icon"><i data-feather="coffee"></i></div>
                                Menu Management
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('menus.*') ? 'show' : '' }}" id="collapseMenu" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('menus.index') ? 'active' : '' }}" href="{{ route('menus.index') }}">All Menu Items</a>
                                    <a class="nav-link {{ request()->routeIs('menus.create') ? 'active' : '' }}" href="{{ route('menus.create') }}">Add New Menu Item</a>
                                </nav>
                            </div>

                            <!-- Menu Categories -->
                            <a class="nav-link {{ request()->routeIs('menu-categories.*') ? 'active' : '' }}" href="{{ route('menu-categories.index') }}">
                                <div class="nav-link-icon"><i data-feather="tag"></i></div>
                                Menu Categories
                            </a>

                            <!-- Menu Orders -->
                            <a class="nav-link collapsed {{ request()->routeIs('menu-orders.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseMenuOrders" aria-expanded="{{ request()->routeIs('menu-orders.*') ? 'true' : 'false' }}" aria-controls="collapseMenuOrders">
                                <div class="nav-link-icon"><i data-feather="shopping-bag"></i></div>
                                Menu Orders
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('menu-orders.*') ? 'show' : '' }}" id="collapseMenuOrders" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('menu-orders.index') ? 'active' : '' }}" href="{{ route('menu-orders.index') }}">All Orders</a>
                                    <a class="nav-link {{ request()->routeIs('menu-orders.create') ? 'active' : '' }}" href="{{ route('menu-orders.create') }}">New Order</a>
                                </nav>
                            </div>

                            <!-- Table Management -->
                            <a class="nav-link collapsed {{ request()->routeIs('tables.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseTables" aria-expanded="{{ request()->routeIs('tables.*') ? 'true' : 'false' }}" aria-controls="collapseTables">
                                <div class="nav-link-icon"><i data-feather="grid"></i></div>
                                Tables
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('tables.*') ? 'show' : '' }}" id="collapseTables" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('tables.index') ? 'active' : '' }}" href="{{ route('tables.index') }}">All Tables</a>
                                    <a class="nav-link {{ request()->routeIs('tables.create') ? 'active' : '' }}" href="{{ route('tables.create') }}">Add New Table</a>
                                </nav>
                            </div>

                            <!-- Payments -->
                            <a class="nav-link collapsed {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapsePayments" aria-expanded="{{ request()->routeIs('payments.*') ? 'true' : 'false' }}" aria-controls="collapsePayments">
                                <div class="nav-link-icon"><i data-feather="credit-card"></i></div>
                                Payments
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('payments.*') ? 'show' : '' }}" id="collapsePayments" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('payments.index') ? 'active' : '' }}" href="{{ route('payments.index') }}">All Payments</a>
                                    <a class="nav-link {{ request()->routeIs('payments.create') ? 'active' : '' }}" href="{{ route('payments.create') }}">Record Payment</a>
                                </nav>
                            </div>

                            <!-- Production & Delivery Section -->
                            <div class="sidenav-menu-heading">Production &amp; Delivery</div>

                            <!-- Production Reports -->
                            <a class="nav-link collapsed {{ request()->routeIs('production.*') || request()->routeIs('productions.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseProduction" aria-expanded="{{ request()->routeIs('production.*') || request()->routeIs('productions.*') ? 'true' : 'false' }}" aria-controls="collapseProduction">
                                <div class="nav-link-icon"><i data-feather="box"></i></div>
                                Production Reports
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('production.*') || request()->routeIs('productions.*') ? 'show' : '' }}" id="collapseProduction" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('productions.index') || request()->routeIs('production.index') ? 'active' : '' }}" href="{{ route('production.index') }}">All Reports</a>
                                    <a class="nav-link {{ request()->routeIs('production.processing') ? 'active' : '' }}" href="{{ route('production.processing') }}">Processing</a>
                                </nav>
                            </div>

                            <!-- Scrap Materials -->
                            <a class="nav-link collapsed {{ request()->routeIs('scrap.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseScrap" aria-expanded="{{ request()->routeIs('scrap.*') ? 'true' : 'false' }}" aria-controls="collapseScrap">
                                <div class="nav-link-icon"><i data-feather="trash-2"></i></div>
                                Scrap Materials
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('scrap.*') ? 'show' : '' }}" id="collapseScrap" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('scrap.index') ? 'active' : '' }}" href="{{ route('scrap.index') }}">List Scrap Waste</a>
                                </nav>
                            </div>

                            <!-- Waste Reports -->
                            <a class="nav-link collapsed {{ request()->routeIs('waste-reports.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseWasteReports" aria-expanded="{{ request()->routeIs('waste-reports.*') ? 'true' : 'false' }}" aria-controls="collapseWasteReports">
                                <div class="nav-link-icon"><i data-feather="alert-triangle"></i></div>
                                Waste Reports
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('waste-reports.*') ? 'show' : '' }}" id="collapseWasteReports" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('waste-reports.index') ? 'active' : '' }}" href="{{ route('waste-reports.index') }}">List Waste Reports</a>
                                    <a class="nav-link {{ request()->routeIs('waste-reports.create') ? 'active' : '' }}" href="{{ route('waste-reports.create') }}">Create Waste Report</a>
                                </nav>
                            </div>

                            <!-- Delivery -->
                            <a class="nav-link collapsed {{ request()->routeIs('delivery.*') || request()->routeIs('deliveries.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseDeliveries" aria-expanded="{{ request()->routeIs('delivery.*') || request()->routeIs('deliveries.*') ? 'true' : 'false' }}" aria-controls="collapseDeliveries">
                                <div class="nav-link-icon"><i data-feather="truck"></i></div>
                                Delivery
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('delivery.*') || request()->routeIs('deliveries.*') ? 'show' : '' }}" id="collapseDeliveries" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('deliveries.index') || request()->routeIs('delivery.index') ? 'active' : '' }}" href="{{ route('deliveries.index') }}">All Deliveries</a>
                                    <a class="nav-link {{ request()->routeIs('deliveries.create') || request()->routeIs('delivery.create') ? 'active' : '' }}" href="{{ route('deliveries.create') }}">New Delivery</a>
                                    <a class="nav-link {{ request()->routeIs('delivery.pending') ? 'active' : '' }}" href="{{ route('delivery.pending') }}">Pending Approval</a>
                                </nav>
                            </div>

                            <!-- Partners Section -->
                            <div class="sidenav-menu-heading">Partners</div>

                            <!-- Suppliers -->
                            <a class="nav-link collapsed {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseSuppliers" aria-expanded="{{ request()->routeIs('suppliers.*') ? 'true' : 'false' }}" aria-controls="collapseSuppliers">
                                <div class="nav-link-icon"><i data-feather="shopping-bag"></i></div>
                                Suppliers
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('suppliers.*') ? 'show' : '' }}" id="collapseSuppliers" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('suppliers.index') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">All Suppliers</a>
                                    <a class="nav-link {{ request()->routeIs('suppliers.create') ? 'active' : '' }}" href="{{ route('suppliers.create') }}">Add New Supplier</a>
                                </nav>
                            </div>

                            <!-- Finance Section -->
                            <div class="sidenav-menu-heading">Finance</div>

                            <a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">
                                <div class="nav-link-icon"><i data-feather="trending-down"></i></div>
                                Expenses
                            </a>

                            <!-- Reports & Analytics Section -->
                            <div class="sidenav-menu-heading">Reports &amp; Analytics</div>

                            <a class="nav-link collapsed {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseReports" aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}" aria-controls="collapseReports">
                                <div class="nav-link-icon"><i data-feather="bar-chart-2"></i></div>
                                Reports
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('reports.*') ? 'show' : '' }}" id="collapseReports" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('reports.inventory.*') ? 'active' : '' }}" href="{{ route('reports.inventory.index') }}">Inventory Report</a>
                                    <a class="nav-link {{ request()->routeIs('reports.transaction.*') ? 'active' : '' }}" href="{{ route('reports.transaction.index') }}">Transaction Report</a>
                                    <a class="nav-link {{ request()->routeIs('reports.delivery.*') ? 'active' : '' }}" href="{{ route('reports.delivery.index') }}">Delivery Report</a>
                                    <a class="nav-link {{ request()->routeIs('reports.costing.*') ? 'active' : '' }}" href="{{ route('reports.costing.index') }}">Costing Report</a>
                                </nav>
                            </div>

                            <!-- System Management Section -->
                            <div class="sidenav-menu-heading">System Management</div>

                            @if(auth()->user()->isAdmin())
                            <!-- Branches -->
                            <a class="nav-link collapsed {{ request()->routeIs('branches.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                               data-bs-target="#collapseBranches" aria-expanded="{{ request()->routeIs('branches.*') ? 'true' : 'false' }}" aria-controls="collapseBranches">
                                <div class="nav-link-icon"><i data-feather="map-pin"></i></div>
                                Branches
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse {{ request()->routeIs('branches.*') ? 'show' : '' }}" id="collapseBranches" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link {{ request()->routeIs('branches.index') ? 'active' : '' }}" href="{{ route('branches.index') }}">All Branches</a>
                                    <a class="nav-link {{ request()->routeIs('branches.create') ? 'active' : '' }}" href="{{ route('branches.create') }}">Add Branch</a>
                                </nav>
                            </div>
                            @endif

                            <!-- Users -->
                            @if(auth()->user()->isAdmin() || auth()->user()->isBranchManager())
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                <div class="nav-link-icon"><i data-feather="users"></i></div>
                                Users
                            </a>
                            @endif

                            <!-- Settings -->
                            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.show') }}">
                                <div class="nav-link-icon"><i data-feather="settings"></i></div>
                                Settings
                            </a>

                        </div>
                    </div>
                    <!-- Sidenav Footer-->
                    <div class="sidenav-footer">
                        <div class="sidenav-footer-content">
                            <div class="sidenav-footer-subtitle">Logged in as:</div>
                            <div class="sidenav-footer-title">{{ auth()->user()->name }}</div>
                            <div class="sidenav-footer-subtitle text-capitalize">{{ auth()->user()->role }}</div>
                        </div>
                    </div>
                </nav>
            </div>
            <div id="layoutSidenav_content">
                @yield('content')
                <footer class="footer-admin mt-auto footer-light">
                    <div class="container-xl px-4">
                        <div class="row">
                            <div class="col-md-6 small">Copyright &copy; Dianne Seafood House {{ date('Y') }}</div>
                            <div class="col-md-6 text-md-end small">
                                Inventory Management System
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="{{ asset('js/scripts.js') }}"></script>
        @stack('scripts')
    </body>
</html>
