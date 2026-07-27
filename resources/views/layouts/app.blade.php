<!DOCTYPE html>
<html lang="en">
    <head>
        <script>
            // Immediately apply stored theme to avoid flashing
            (function() {
                const currentTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-bs-theme', currentTheme);
                // Restore the desktop sidebar collapsed state before paint
                if (localStorage.getItem('sidebar-collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            })();
        </script>
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
        <meta name="theme-color" content="#f07c59" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="Dianne Seafood House" />
        <meta name="application-name" content="Dianne Seafood House Inventory" />
        
        <title>@yield('page_title', 'Dashboard - Dianne Seafood House')</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="{{ asset('css/styles-old.css') }}?v={{ filemtime(public_path('css/styles-old.css')) }}" rel="stylesheet" />
        <!-- Favicon and Icons -->
        <link rel="icon" type="image/x-icon" href="{{ asset('assets/icons/favicon.ico') }}" />
        <script data-search-pseudo-elements defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js" crossorigin="anonymous"></script>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script>
            window.__isRefreshingLucideIcons = false;
            window.refreshLucideIcons = function(root) {
                if (!window.lucide) {
                    return;
                }

                const scope = root && root.querySelectorAll ? root : document;
                scope.querySelectorAll('svg[data-lucide]').forEach(svg => {
                    svg.removeAttribute('data-lucide');
                });

                if (!scope.querySelector('[data-lucide]:not(svg)')) {
                    return;
                }

                window.__isRefreshingLucideIcons = true;
                try {
                    window.lucide.createIcons();
                    document.querySelectorAll('svg[data-lucide]').forEach(svg => {
                        svg.removeAttribute('data-lucide');
                    });
                } finally {
                    window.setTimeout(() => {
                        window.__isRefreshingLucideIcons = false;
                    }, 0);
                }
            };
            document.addEventListener('DOMContentLoaded', () => {
                window.refreshLucideIcons();
            });
        </script>
        
        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        
        <link href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}" rel="stylesheet" />
        @php
            try {
                $customSidebarLight = \App\Models\AppSetting::get('sidebar_bg_light');
                $customSidebarDark = \App\Models\AppSetting::get('sidebar_bg_dark');
            } catch (\Throwable $e) {
                $customSidebarLight = $customSidebarDark = null;
            }
        @endphp
        @if($customSidebarLight || $customSidebarDark)
        <style>
            @if($customSidebarLight)
            :root { --bg-sidebar: {{ $customSidebarLight }}; }
            @endif
            @if($customSidebarDark)
            [data-bs-theme="dark"] { --bg-sidebar: {{ $customSidebarDark }}; }
            @endif
        </style>
        @endif
        @stack('styles')
    </head>
    <body class="bg-body text-main">
        <div class="wrapper">
            <!-- Sidebar Navigation -->
            <nav class="sidebar d-flex flex-column" id="sidebar">
                <div class="sidebar-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <img src="{{ asset('assets/icons/apple-touch-icon.png') }}" alt="Logo" class="rounded" style="width: 32px; height: 32px; object-fit: contain;">
                        <span class="fs-5 fw-bold text-white">Dianne's Seafood House</span>
                    </div>
                    <button class="btn btn-sm d-lg-none text-white border-0" id="sidebarClose">
                        <i data-lucide="x" style="width: 20px; height: 20px;"></i>
                    </button>
                </div>

                @php
                    $authUser = auth()->user();
                    $profilePhotoUrl = $authUser && $authUser->profile_photo_path
                        ? route('profile-photos.show', $authUser)
                        : asset('assets/img/illustrations/profiles/profile-1.png');
                @endphp

                <!-- Branch Selector (Mobile dropdown) -->
                @if($authUser && $authUser->isAdmin())
                @php
                    $branches = \App\Models\Branch::where('is_active', true)->get();
                    $selectedBranchId = session('selected_branch_id');
                    $selectedBranchName = 'All Branches';
                    if (!empty($selectedBranchId)) {
                        $selectedBranch = $branches->firstWhere('id', $selectedBranchId);
                        if ($selectedBranch) {
                            $selectedBranchName = $selectedBranch->name;
                        }
                    }
                @endphp
                <div class="dropdown d-lg-none mx-3 mb-3">
                    <button class="btn btn-primary dropdown-toggle d-flex align-items-center justify-content-between w-100 py-2.5 px-3 fw-semibold text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px;">
                        <span class="d-flex align-items-center gap-2">
                            <i data-lucide="git-branch" style="width: 16px; height: 16px;"></i>
                            <span>{{ $selectedBranchName }}</span>
                        </span>
                    </button>
                    <ul class="dropdown-menu p-2 border-0 shadow-lg mt-2 w-100" style="border-radius: 12px; min-width: 180px;">
                        <li>
                            <form action="{{ route('branch.select') }}" method="POST" class="m-0">
                                @csrf
                                <input type="hidden" name="branch_id" value="">
                                <button type="submit" class="dropdown-item py-2 rounded-3 border-0 w-100 text-start {{ empty($selectedBranchId) ? 'active' : '' }}">
                                    All Branches
                                </button>
                            </form>
                        </li>
                        @foreach($branches as $br)
                        <li>
                            <form action="{{ route('branch.select') }}" method="POST" class="m-0">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $br->id }}">
                                <button type="submit" class="dropdown-item py-2 rounded-3 border-0 w-100 text-start {{ (string) $selectedBranchId === (string) $br->id ? 'active' : '' }}">
                                    {{ $br->name }}
                                </button>
                            </form>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!-- Navigation Links -->
                <ul class="nav nav-pills flex-column mb-auto mt-2 px-2 gap-1" style="overflow-y: auto;">
                    <!-- Core Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Core</div>
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i data-lucide="layout-grid"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <!-- Inventory Management Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Inventory Management</div>
                    
                    <!-- Items -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('items.*') || request()->routeIs('inventory.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseItems" aria-expanded="{{ request()->routeIs('items.*') || request()->routeIs('inventory.*') ? 'true' : 'false' }}">
                            <i data-lucide="package"></i>
                            <span>Items</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('items.*') || request()->routeIs('inventory.*') ? 'show' : '' }} ps-3" id="collapseItems">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('items.index') || request()->routeIs('inventory.index') ? 'active' : '' }}" href="{{ route('items.index') }}">All Items</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('items.create') || request()->routeIs('inventory.create') ? 'active' : '' }}" href="{{ route('items.create') }}">Add New Item</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('items.low-stock') ? 'active' : '' }}" href="{{ route('items.low-stock') }}">Low Stock Alerts</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Locations -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseLocationCategories" aria-expanded="{{ request()->routeIs('categories.*') ? 'true' : 'false' }}">
                            <i data-lucide="folder"></i>
                            <span>Locations</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('categories.*') ? 'show' : '' }} ps-3" id="collapseLocationCategories">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('categories.all') || request()->routeIs('categories.module') ? 'active' : '' }}" href="{{ route('categories.all') }}">All Locations</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Transactions -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('transactions.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseTransactions" aria-expanded="{{ request()->routeIs('transactions.*') ? 'true' : 'false' }}">
                            <i data-lucide="list"></i>
                            <span>Transactions</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('transactions.*') ? 'show' : '' }} ps-3" id="collapseTransactions">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('transactions.index') ? 'active' : '' }}" href="{{ route('transactions.index') }}">All Transactions</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('transactions.create') ? 'active' : '' }}" href="{{ route('transactions.create') }}">New Transaction</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('transactions.pending') ? 'active' : '' }}" href="{{ route('transactions.pending') }}">Pending Approval</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Restaurant Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Restaurant</div>
                    
                    <!-- Menu Management -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('menus.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseMenu" aria-expanded="{{ request()->routeIs('menus.*') ? 'true' : 'false' }}">
                            <i data-lucide="coffee"></i>
                            <span>Menu Management</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('menus.*') ? 'show' : '' }} ps-3" id="collapseMenu">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('menus.index') ? 'active' : '' }}" href="{{ route('menus.index') }}">All Menu Items</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('menus.create') ? 'active' : '' }}" href="{{ route('menus.create') }}">Add New Menu Item</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Menu Categories -->
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('menu-categories.*') ? 'active' : '' }}" href="{{ route('menu-categories.index') }}">
                            <i data-lucide="tag"></i>
                            <span>Menu Categories</span>
                        </a>
                    </li>

                    <!-- Menu Orders -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('menu-orders.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseMenuOrders" aria-expanded="{{ request()->routeIs('menu-orders.*') ? 'true' : 'false' }}">
                            <i data-lucide="shopping-bag"></i>
                            <span>Menu Orders</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('menu-orders.*') ? 'show' : '' }} ps-3" id="collapseMenuOrders">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('menu-orders.index') ? 'active' : '' }}" href="{{ route('menu-orders.index') }}">All Orders</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('menu-orders.create') ? 'active' : '' }}" href="{{ route('menu-orders.create') }}">New Order</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Table Management -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('tables.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseTables" aria-expanded="{{ request()->routeIs('tables.*') ? 'true' : 'false' }}">
                            <i data-lucide="grid"></i>
                            <span>Tables</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('tables.*') ? 'show' : '' }} ps-3" id="collapseTables">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('tables.index') ? 'active' : '' }}" href="{{ route('tables.index') }}">All Tables</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('tables.create') ? 'active' : '' }}" href="{{ route('tables.create') }}">Add New Table</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Payments -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapsePayments" aria-expanded="{{ request()->routeIs('payments.*') ? 'true' : 'false' }}">
                            <i data-lucide="credit-card"></i>
                            <span>Payments</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('payments.*') ? 'show' : '' }} ps-3" id="collapsePayments">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('payments.index') ? 'active' : '' }}" href="{{ route('payments.index') }}">All Payments</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('payments.create') ? 'active' : '' }}" href="{{ route('payments.create') }}">Record Payment</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Production & Delivery Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Production &amp; Delivery</div>
                    
                    <!-- Production Reports -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('production.*') || request()->routeIs('productions.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseProduction" aria-expanded="{{ request()->routeIs('production.*') || request()->routeIs('productions.*') ? 'true' : 'false' }}">
                            <i data-lucide="box"></i>
                            <span>Production Reports</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('production.*') || request()->routeIs('productions.*') ? 'show' : '' }} ps-3" id="collapseProduction">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('productions.index') || request()->routeIs('production.index') ? 'active' : '' }}" href="{{ route('production.index') }}">All Reports</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('production.processing') ? 'active' : '' }}" href="{{ route('production.processing') }}">Processing</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Scrap Materials -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('scrap.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseScrap" aria-expanded="{{ request()->routeIs('scrap.*') ? 'true' : 'false' }}">
                            <i data-lucide="trash-2"></i>
                            <span>Scrap Materials</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('scrap.*') ? 'show' : '' }} ps-3" id="collapseScrap">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('scrap.index') ? 'active' : '' }}" href="{{ route('scrap.index') }}">List Scrap Waste</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Waste Reports -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('waste-reports.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseWasteReports" aria-expanded="{{ request()->routeIs('waste-reports.*') ? 'true' : 'false' }}">
                            <i data-lucide="alert-triangle"></i>
                            <span>Waste Reports</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('waste-reports.*') ? 'show' : '' }} ps-3" id="collapseWasteReports">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('waste-reports.index') ? 'active' : '' }}" href="{{ route('waste-reports.index') }}">List Waste Reports</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('waste-reports.create') ? 'active' : '' }}" href="{{ route('waste-reports.create') }}">Create Waste Report</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Delivery -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('delivery.*') || request()->routeIs('deliveries.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseDeliveries" aria-expanded="{{ request()->routeIs('delivery.*') || request()->routeIs('deliveries.*') ? 'true' : 'false' }}">
                            <i data-lucide="truck"></i>
                            <span>Delivery</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('delivery.*') || request()->routeIs('deliveries.*') ? 'show' : '' }} ps-3" id="collapseDeliveries">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('deliveries.index') || request()->routeIs('delivery.index') ? 'active' : '' }}" href="{{ route('deliveries.index') }}">All Deliveries</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('deliveries.create') || request()->routeIs('delivery.create') ? 'active' : '' }}" href="{{ route('deliveries.create') }}">New Delivery</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('delivery.pending') ? 'active' : '' }}" href="{{ route('delivery.pending') }}">Pending Approval</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Feedback -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('feedback.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseFeedback" aria-expanded="{{ request()->routeIs('feedback.*') ? 'true' : 'false' }}">
                            <i data-lucide="message-square"></i>
                            <span>Feedback</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('feedback.*') ? 'show' : '' }} ps-3" id="collapseFeedback">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('feedback.index') ? 'active' : '' }}" href="{{ route('feedback.index') }}">List Feedback</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('feedback.create') ? 'active' : '' }}" href="{{ route('feedback.create') }}">Record Feedback</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Partners Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Partners</div>
                    
                    <!-- Suppliers -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseSuppliers" aria-expanded="{{ request()->routeIs('suppliers.*') ? 'true' : 'false' }}">
                            <i data-lucide="shopping-bag"></i>
                            <span>Suppliers</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('suppliers.*') ? 'show' : '' }} ps-3" id="collapseSuppliers">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('suppliers.index') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">All Suppliers</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('suppliers.create') ? 'active' : '' }}" href="{{ route('suppliers.create') }}">Add New Supplier</a>
                               </li>
                           </ul>
                        </div>
                    </li>

                    <!-- Finance Section (admins and branch managers only) -->
                    @if($authUser && ($authUser->isAdmin() || $authUser->isBranchManager()))
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Finance</div>

                    <!-- Purchase & Disbursement Book -->
                    @php
                        $purchaseBookActive = request()->routeIs('purchase-vouchers.*')
                            || request()->routeIs('petty-cash-vouchers.*')
                            || request()->routeIs('check-vouchers.*')
                            || request()->routeIs('check-register.*')
                            || request()->routeIs('chart-of-accounts.*');
                    @endphp
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ $purchaseBookActive ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapsePurchaseBook" aria-expanded="{{ $purchaseBookActive ? 'true' : 'false' }}">
                            <i data-lucide="book-text"></i>
                            <span>Disbursements</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ $purchaseBookActive ? 'show' : '' }} ps-3" id="collapsePurchaseBook">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('purchase-vouchers.*') ? 'active' : '' }}" href="{{ route('purchase-vouchers.index') }}">Purchase Vouchers (APV)</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('petty-cash-vouchers.*') ? 'active' : '' }}" href="{{ route('petty-cash-vouchers.index') }}">Petty Cash Vouchers (PCV)</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('check-vouchers.*') ? 'active' : '' }}" href="{{ route('check-vouchers.index') }}">Check Vouchers (CV)</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('check-register.*') ? 'active' : '' }}" href="{{ route('check-register.index') }}">Check Register</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('chart-of-accounts.*') ? 'active' : '' }}" href="{{ route('chart-of-accounts.index') }}">Chart of Accounts</a>
                               </li>
                           </ul>
                        </div>
                    </li>
                    @endif

                    <!-- Reports & Analytics Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Reports &amp; Analytics</div>
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseReports" aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}">
                            <i data-lucide="bar-chart-2"></i>
                            <span>Reports</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('reports.*') ? 'show' : '' }} ps-3" id="collapseReports">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.inventory.*') ? 'active' : '' }}" href="{{ route('reports.inventory.index') }}">Inventory Report</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.transaction.*') ? 'active' : '' }}" href="{{ route('reports.transaction.index') }}">Transaction Report</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.delivery.*') ? 'active' : '' }}" href="{{ route('reports.delivery.index') }}">Delivery Report</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.costing.*') ? 'active' : '' }}" href="{{ route('reports.costing.index') }}">Costing Report</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.feedback.*') ? 'active' : '' }}" href="{{ route('reports.feedback.index') }}">Feedback Report</a>
                               </li>
                               @if($authUser && ($authUser->isAdmin() || $authUser->isBranchManager()))
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.menu-order-sales.*') ? 'active' : '' }}" href="{{ route('reports.menu-order-sales.index') }}">Menu Order Sales Report</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.purchase-disbursement.summary') ? 'active' : '' }}" href="{{ route('reports.purchase-disbursement.summary') }}">Purchase &amp; Disbursement Summary</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.purchase-disbursement.aging') ? 'active' : '' }}" href="{{ route('reports.purchase-disbursement.aging') }}">Unpaid APV Aging</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('reports.purchase-disbursement.petty-cash-fund') ? 'active' : '' }}" href="{{ route('reports.purchase-disbursement.petty-cash-fund') }}">Petty Cash Fund Status</a>
                               </li>
                               @endif
                           </ul>
                        </div>
                    </li>

                    <!-- System Management Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">System Management</div>
                    
                    @if($authUser && $authUser->isAdmin())
                    <!-- Branches -->
                    <li class="nav-item">
                        <a class="nav-link collapsed {{ request()->routeIs('branches.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse"
                           data-bs-target="#collapseBranches" aria-expanded="{{ request()->routeIs('branches.*') ? 'true' : 'false' }}">
                            <i data-lucide="map-pin"></i>
                            <span>Branches</span>
                            <i data-lucide="chevron-down" class="ms-auto collapse-arrow" style="width: 14px; height: 14px;"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('branches.*') ? 'show' : '' }} ps-3" id="collapseBranches">
                           <ul class="nav flex-column mt-1 gap-1">
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('branches.index') ? 'active' : '' }}" href="{{ route('branches.index') }}">All Branches</a>
                               </li>
                               <li class="nav-item">
                                   <a class="nav-link py-1.5 px-3 {{ request()->routeIs('branches.create') ? 'active' : '' }}" href="{{ route('branches.create') }}">Add Branch</a>
                               </li>
                           </ul>
                        </div>
                    </li>
                    @endif

                    <!-- Users -->
                    @if($authUser && ($authUser->isAdmin() || $authUser->isBranchManager()))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                            <i data-lucide="users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    @endif

                    <!-- Settings -->
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.show') }}">
                            <i data-lucide="settings"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>

                <!-- Sidebar Footer User Profile -->
                <div class="sidebar-footer">
                    <div class="d-flex align-items-center gap-3 p-2 rounded hover-bg-sidebar">
                        <img src="{{ $profilePhotoUrl }}" alt="Avatar" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover;">
                        <div class="overflow-hidden">
                            <h6 class="mb-0 text-white text-truncate small" style="font-weight: 600;">{{ $authUser ? $authUser->name : 'Guest' }}</h6>
                            <span class="text-white-50 text-truncate d-block text-capitalize" style="font-size: 0.75rem;">{{ $authUser ? $authUser->role : '' }}</span>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <div class="main-content">
                <!-- Glassmorphic Header -->
                <header class="glass-header py-3 px-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <!-- Left: Sidebar Toggler and Title/Search placeholder -->
                        <div class="d-flex align-items-center gap-3 flex-grow-1 me-3">
                            <button class="btn btn-outline-secondary py-1.5 px-2 border-opacity-10" id="sidebarToggle" title="Toggle sidebar">
                                <i data-lucide="panel-left" class="d-none d-lg-inline-block" style="width: 20px; height: 20px;"></i>
                                <i data-lucide="menu" class="d-lg-none" style="width: 20px; height: 20px;"></i>
                            </button>
                            
                            <div class="position-relative flex-grow-1" style="max-width: 320px; width: 100%; z-index: 1050;">
                                <span class="position-absolute start-0 top-0 h-100 d-flex align-items-center ps-3 text-muted">
                                    <i data-lucide="search" style="width: 18px; height: 18px;"></i>
                                </span>
                                <input type="text" class="form-control ps-5 py-2 border-0 bg-light" id="globalSearch" placeholder="Search inventory, categories..." autocomplete="off">
                                <div id="globalSearchSuggestions" class="dropdown-menu w-100 p-0 border-0 shadow-lg mt-2 overflow-hidden" style="border-radius: 12px; max-height: 350px; overflow-y: auto;"></div>
                            </div>
                        </div>

                        <!-- Right: Branch Selector, Theme Switch, User Dropdown -->
                        <div class="d-flex align-items-center gap-3">
                            <!-- Branch Selector (Admin only) -->
                            @if($authUser && $authUser->isAdmin())
                            @php
                                $branches = \App\Models\Branch::where('is_active', true)->get();
                                $selectedBranchId = session('selected_branch_id');
                                $selectedBranchName = 'All Branches';
                                if (!empty($selectedBranchId)) {
                                    $selectedBranch = $branches->firstWhere('id', $selectedBranchId);
                                    if ($selectedBranch) {
                                        $selectedBranchName = $selectedBranch->name;
                                    }
                                }
                            @endphp
                            <div class="dropdown d-none d-lg-block">
                                <button class="btn btn-primary dropdown-toggle d-flex align-items-center gap-2 py-2 px-3 fw-semibold text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px;">
                                    <i data-lucide="git-branch" style="width: 16px; height: 16px;"></i>
                                    <span>{{ $selectedBranchName }}</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end p-2 border-0 shadow-lg mt-2" style="border-radius: 12px; min-width: 180px;">
                                    <li>
                                        <form action="{{ route('branch.select') }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="branch_id" value="">
                                            <button type="submit" class="dropdown-item py-2 rounded-3 border-0 w-100 text-start {{ empty($selectedBranchId) ? 'active' : '' }}">
                                                All Branches
                                            </button>
                                        </form>
                                    </li>
                                    @foreach($branches as $br)
                                    <li>
                                        <form action="{{ route('branch.select') }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="branch_id" value="{{ $br->id }}">
                                            <button type="submit" class="dropdown-item py-2 rounded-3 border-0 w-100 text-start {{ (string) $selectedBranchId === (string) $br->id ? 'active' : '' }}">
                                                {{ $br->name }}
                                            </button>
                                        </form>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif

                            <!-- Theme Toggle -->
                            <div class="theme-switch-label" id="themeToggler" title="Toggle Light/Dark Theme">
                                <i class="theme-icon-dark" data-lucide="moon" style="width: 20px; height: 20px;"></i>
                                <i class="theme-icon-light d-none" data-lucide="sun" style="width: 20px; height: 20px;"></i>
                            </div>

                            <!-- User Profile Dropdown -->
                            <div class="dropdown">
                                <button class="btn p-0 border-0 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <img src="{{ $profilePhotoUrl }}" alt="Avatar" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;">
                                    <span class="d-none d-sm-inline fw-semibold text-main">{{ $authUser ? $authUser->name : 'Guest' }}</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end p-2 border-0 shadow-lg mt-2" style="border-radius: 12px;">
                                    <li class="px-3 py-2 border-bottom">
                                        <p class="mb-0 fw-bold">{{ $authUser ? $authUser->name : 'Guest' }}</p>
                                        <small class="text-muted">{{ $authUser ? $authUser->email : '' }}</small>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex gap-2 align-items-center py-2 mt-1 rounded-3 border-0" href="{{ route('account.index') }}">
                                            <i data-lucide="settings" style="width: 16px;"></i>Account Settings
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider opacity-10"></li>
                                    <li>
                                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="dropdown-item d-flex gap-2 align-items-center py-2 text-danger rounded-3 border-0 w-100 bg-transparent text-start">
                                                <i data-lucide="log-out" style="width: 16px;"></i>Sign Out
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Main Content Body -->
                <main class="flex-grow-1 p-4">
                    @yield('content')
                </main>

                <!-- Footer -->
                <footer class="app-footer py-3 px-4 border-top bg-body" style="border-color: var(--border-color) !important;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                        <span class="small text-muted">&copy; {{ date('Y') }} Dianne's Seafood House. All rights reserved.</span>
                        <div class="d-flex gap-3">
                            <span class="small text-muted">Powered by <span class="app-footer-brand">Zapzaf Studios</span></span>
                        </div>
                    </div>
                </footer>
            </div>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="{{ asset('js/scripts.js') }}?v={{ filemtime(public_path('js/scripts.js')) }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Responsive Sidebar Toggle logic
                const sidebar = document.getElementById('sidebar');
                const sidebarToggle = document.getElementById('sidebarToggle');
                const sidebarClose = document.getElementById('sidebarClose');

                if(sidebarToggle) {
                    sidebarToggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (window.matchMedia('(min-width: 992px)').matches) {
                            // Desktop: collapse/expand and remember the choice
                            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
                            localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
                        } else {
                            sidebar.classList.toggle('active');
                        }
                    });
                }
                if(sidebarClose) {
                    sidebarClose.addEventListener('click', (e) => {
                        e.preventDefault();
                        sidebar.classList.remove('active');
                    });
                }

                // Fade the active state onto the clicked menu item right away,
                // so the highlight transitions smoothly before navigation.
                sidebar.querySelectorAll('.nav-link[href]:not([data-bs-toggle])').forEach((link) => {
                    link.addEventListener('click', () => {
                        if (link.classList.contains('active')) return;
                        sidebar.querySelectorAll('.nav-link.active').forEach((active) => active.classList.remove('active'));
                        link.classList.add('active');
                    });
                });

                // Theme Toggler logic
                const themeToggler = document.getElementById('themeToggler');
                if (themeToggler) {
                    const sunIcon = themeToggler.querySelector('.theme-icon-light');
                    const moonIcon = themeToggler.querySelector('.theme-icon-dark');
                    
                    const updateToggleIcons = (theme) => {
                        if (theme === 'dark') {
                            sunIcon.classList.remove('d-none');
                            moonIcon.classList.add('d-none');
                        } else {
                            sunIcon.classList.add('d-none');
                            moonIcon.classList.remove('d-none');
                        }
                    };

                    const currentTheme = localStorage.getItem('theme') || 'light';
                    updateToggleIcons(currentTheme);

                    themeToggler.addEventListener('click', () => {
                        const activeTheme = document.documentElement.getAttribute('data-bs-theme');
                        const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
                        document.documentElement.setAttribute('data-bs-theme', newTheme);
                        localStorage.setItem('theme', newTheme);
                        updateToggleIcons(newTheme);
                        
                        window.dispatchEvent(new CustomEvent('theme-changed', { detail: newTheme }));
                    });
                }

                // Global Search Suggestions Logic
                const searchInput = document.getElementById('globalSearch');
                const suggestionsContainer = document.getElementById('globalSearchSuggestions');
                
                if (searchInput && suggestionsContainer) {
                    let debounceTimer = null;
                    
                    searchInput.addEventListener('input', () => {
                        const query = searchInput.value.trim();
                        clearTimeout(debounceTimer);
                        
                        if (query.length < 2) {
                            suggestionsContainer.classList.remove('show');
                            suggestionsContainer.innerHTML = '';
                            return;
                        }
                        
                        debounceTimer = setTimeout(() => {
                            fetch(`/global-search-suggestions?q=${encodeURIComponent(query)}`)
                                .then(response => response.json())
                                .then(data => {
                                    suggestionsContainer.innerHTML = '';
                                    if (data.length === 0) {
                                        const noResults = document.createElement('div');
                                        noResults.className = 'text-muted small p-3 text-center';
                                        noResults.innerText = 'No suggestions found';
                                        suggestionsContainer.appendChild(noResults);
                                        suggestionsContainer.classList.add('show');
                                        return;
                                    }
                                    
                                    // Group results by category
                                    const groups = {};
                                    data.forEach(item => {
                                        if (!groups[item.category]) {
                                            groups[item.category] = [];
                                        }
                                        groups[item.category].push(item);
                                    });
                                    
                                    // Render grouped items
                                    for (const [category, items] of Object.entries(groups)) {
                                        const header = document.createElement('div');
                                        header.className = 'dropdown-header py-2 text-uppercase fw-bold text-muted small';
                                        header.innerText = category;
                                        suggestionsContainer.appendChild(header);
                                        
                                        items.forEach(item => {
                                            const link = document.createElement('a');
                                            link.href = item.url;
                                            link.className = 'dropdown-item d-flex align-items-center gap-2 py-2 px-3 text-wrap';
                                            link.style.transition = 'background-color 0.15s ease';
                                            
                                            const iconSpan = document.createElement('span');
                                            iconSpan.className = 'text-muted d-flex align-items-center';
                                            iconSpan.innerHTML = `<i data-lucide="${item.icon || 'arrow-right'}" style="width: 15px; height: 15px;"></i>`;
                                            
                                            const titleSpan = document.createElement('span');
                                            titleSpan.className = 'small fw-medium';
                                            titleSpan.innerText = item.title;
                                            
                                            link.appendChild(iconSpan);
                                            link.appendChild(titleSpan);
                                            
                                            suggestionsContainer.appendChild(link);
                                        });
                                    }
                                    
                                    suggestionsContainer.classList.add('show');
                                    
                                    // Render lucide icons
                                    if (typeof lucide !== 'undefined' && lucide.createIcons) {
                                        lucide.createIcons();
                                    }
                                })
                                .catch(err => console.error('Error fetching suggestions:', err));
                        }, 250);
                    });
                    
                    // Hide suggestions when clicking outside
                    document.addEventListener('click', (e) => {
                        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                            suggestionsContainer.classList.remove('show');
                        }
                    });
                    
                    // Show suggestions again on focus if there is value
                    searchInput.addEventListener('focus', () => {
                        if (searchInput.value.trim().length >= 2) {
                            suggestionsContainer.classList.add('show');
                        }
                    });

                    // Add keyboard navigation support (Up/Down arrow & Enter)
                    const searchWrapper = searchInput.closest('.position-relative') || searchInput.parentElement;
                    searchWrapper.addEventListener('keydown', (e) => {
                        const items = Array.from(suggestionsContainer.querySelectorAll('a.dropdown-item'));
                        if (items.length === 0) return;
                        
                        const activeElement = document.activeElement;
                        
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            if (activeElement === searchInput) {
                                items[0].focus();
                            } else {
                                const activeIndex = items.indexOf(activeElement);
                                if (activeIndex !== -1) {
                                    const nextIndex = (activeIndex + 1) % items.length;
                                    items[nextIndex].focus();
                                }
                            }
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            if (activeElement === searchInput) {
                                items[items.length - 1].focus();
                            } else {
                                const activeIndex = items.indexOf(activeElement);
                                if (activeIndex === 0) {
                                    searchInput.focus();
                                } else if (activeIndex !== -1) {
                                    const prevIndex = (activeIndex - 1 + items.length) % items.length;
                                    items[prevIndex].focus();
                                }
                            }
                        } else if (e.key === 'Escape') {
                            suggestionsContainer.classList.remove('show');
                            searchInput.focus();
                        } else if (e.key === 'Enter') {
                            if (activeElement === searchInput) {
                                e.preventDefault();
                                if (items.length > 0) {
                                    window.location.href = items[0].href;
                                }
                            }
                        }
                    });
                }
            });
        </script>
        @stack('scripts')
    </body>
</html>
