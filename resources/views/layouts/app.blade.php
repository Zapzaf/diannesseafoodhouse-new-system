<!DOCTYPE html>
<html lang="en">
    <head>
        <script>
            // Immediately apply stored theme to avoid flashing
            (function() {
                const currentTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-bs-theme', currentTheme);
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
        <link href="{{ asset('css/styles-old.css') }}" rel="stylesheet" />
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
        
        <link href="{{ asset('css/style.css') }}" rel="stylesheet" />
        @stack('styles')
    </head>
    <body class="bg-body text-main">
        <div class="wrapper">
            <!-- Sidebar Navigation -->
            <nav class="sidebar d-flex flex-column" id="sidebar">
                <div class="sidebar-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="icon-circle bg-white text-dark d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                            <i data-lucide="home" style="color: #f07c59; width: 18px; height: 18px;"></i>
                        </div>
                        <span class="fs-5 fw-bold text-white">Dianne Seafood</span>
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

                <!-- Branch Selector -->
                @if($authUser && $authUser->isAdmin())
                <div class="branch-select-wrapper mb-3">
                    <i data-lucide="map-pin" style="width: 18px; height: 18px;"></i>
                    @php
                        $branches = \App\Models\Branch::where('is_active', true)->get();
                        $selectedBranchId = session('selected_branch_id');
                    @endphp
                    <form action="{{ route('branch.select') }}" method="POST" id="branchSelectForm" class="w-100 m-0">
                        @csrf
                        <select class="branch-select-input" name="branch_id" onchange="document.getElementById('branchSelectForm').submit()">
                            <option value="" {{ empty($selectedBranchId) ? 'selected' : '' }}>All Branches</option>
                            @foreach($branches as $br)
                                <option value="{{ $br->id }}" {{ (string) $selectedBranchId === (string) $br->id ? 'selected' : '' }}>{{ $br->name }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                @endif

                <!-- Navigation Links -->
                <ul class="nav nav-pills flex-column mb-auto mt-2 px-2 gap-1" style="overflow-y: auto;">
                    <!-- Core Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Core</div>
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i data-lucide="activity"></i>
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

                    <!-- Finance Section -->
                    <div class="sidenav-menu-heading text-uppercase px-3 py-2 mt-2 small fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.08em; opacity: 0.7;">Finance</div>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">
                            <i data-lucide="trending-down"></i>
                            <span>Expenses</span>
                        </a>
                    </li>

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
                            <button class="btn btn-outline-secondary d-lg-none py-1.5 px-2 border-opacity-10" id="sidebarToggle">
                                <i data-lucide="menu" style="width: 20px; height: 20px;"></i>
                            </button>
                            
                            <div class="position-relative d-none d-md-block" style="max-width: 320px; width: 100%;">
                                <span class="position-absolute top-50 start-0 translate-middle-y ps-3 text-muted">
                                    <i data-lucide="search" style="width: 18px; height: 18px;"></i>
                                </span>
                                <input type="text" class="form-control ps-5 py-2 border-0 bg-light" id="globalSearch" placeholder="Search inventory, categories...">
                            </div>
                        </div>

                        <!-- Right: Theme Switch, User Dropdown -->
                        <div class="d-flex align-items-center gap-3">
                            <!-- Theme Toggle -->
                            <div class="theme-switch-label" id="themeToggler" title="Toggle Light/Dark Theme">
                                <i class="theme-icon-dark" data-lucide="moon" style="width: 20px; height: 20px;"></i>
                                <i class="theme-icon-light d-none" data-lucide="sun" style="width: 20px; height: 20px;"></i>
                            </div>

                            <!-- User Profile Dropdown -->
                            <div class="dropdown">
                                <button class="btn p-0 border-0 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <img src="{{ $profilePhotoUrl }}" alt="Avatar" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;">
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

                <!-- Sticky Footer -->
                <footer class="py-3 px-4 border-top mt-auto bg-body" style="border-color: var(--border-color) !important;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                        <span class="small text-muted">&copy; {{ date('Y') }} Dianne Seafood House. All rights reserved.</span>
                        <div class="d-flex gap-3">
                            <span class="small text-muted">Inventory Management System</span>
                        </div>
                    </div>
                </footer>
            </div>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="{{ asset('js/scripts.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Responsive Sidebar Toggle logic
                const sidebar = document.getElementById('sidebar');
                const sidebarToggle = document.getElementById('sidebarToggle');
                const sidebarClose = document.getElementById('sidebarClose');

                if(sidebarToggle) {
                    sidebarToggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        sidebar.classList.toggle('active');
                    });
                }
                if(sidebarClose) {
                    sidebarClose.addEventListener('click', (e) => {
                        e.preventDefault();
                        sidebar.classList.remove('active');
                    });
                }

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
            });
        </script>
        @stack('scripts')
    </body>
</html>
