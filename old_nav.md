<nav class="topnav navbar navbar-expand shadow justify-content-between justify-content-sm-start navbar-light bg-white" id="sidenavAccordion">
            <!-- Sidenav Toggle Button-->
            <button class="btn btn-icon btn-transparent-dark order-1 order-lg-0 me-2 ms-lg-2 me-lg-0" id="sidebarToggle"><i data-feather="menu"></i></button>
            <!-- Navbar Brand-->
            <!-- * * Tip * * You can use text or an image for your navbar brand.-->
            <!-- * * * * * * When using an image, we recommend the SVG format.-->
            <!-- * * * * * * Dimensions: Maximum height: 32px, maximum width: 240px-->
            <a class="navbar-brand pe-3 ps-4 ps-lg-2" href="{{ route('dashboard') }}" style="color: var(--primary-color)">Dianne's System</a>
            <!-- Navbar Search Input-->
            <!-- Navbar Items-->
            <ul class="navbar-nav align-items-center ms-auto">
                <!-- Navbar Search Dropdown-->
                <!-- * * Note: * * Visible only below the lg breakpoint-->
                <li class="nav-item dropdown no-caret me-3 d-lg-none">
                    <a class="btn btn-icon btn-transparent-dark dropdown-toggle" id="searchDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i data-feather="search"></i></a>
                    <!-- Dropdown - Search-->
                    <div class="dropdown-menu dropdown-menu-end p-3 shadow animated--fade-in-up" aria-labelledby="searchDropdown">
                        <form class="form-inline me-auto w-100">
                            <div class="input-group input-group-joined input-group-solid">
                                <input class="form-control pe-0" type="text" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2" />
                                <div class="input-group-text"><i data-feather="search"></i></div>
                            </div>
                        </form>
                    </div>
                </li>
                <!-- Alerts Dropdown-->
                <li class="nav-item dropdown no-caret d-none d-sm-block me-3 dropdown-notifications">
                    <a class="btn btn-icon btn-transparent-dark dropdown-toggle" id="navbarDropdownAlerts" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i data-feather="bell"></i></a>
                    <div class="dropdown-menu dropdown-menu-end border-0 shadow animated--fade-in-up" aria-labelledby="navbarDropdownAlerts">
                        <h6 class="dropdown-header dropdown-notifications-header">
                            <i class="me-2" data-feather="bell"></i>
                            Alerts Center
                        </h6>
                        <!-- Example Alert 1-->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <div class="dropdown-notifications-item-icon bg-warning"><i data-feather="activity"></i></div>
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-details">December 29, 2021</div>
                                <div class="dropdown-notifications-item-content-text">This is an alert message. It's nothing serious, but it requires your attention.</div>
                            </div>
                        </a>
                        <!-- Example Alert 2-->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <div class="dropdown-notifications-item-icon bg-info"><i data-feather="bar-chart"></i></div>
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-details">December 22, 2021</div>
                                <div class="dropdown-notifications-item-content-text">A new monthly report is ready. Click here to view!</div>
                            </div>
                        </a>
                        <!-- Example Alert 3-->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <div class="dropdown-notifications-item-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-details">December 8, 2021</div>
                                <div class="dropdown-notifications-item-content-text">Critical system failure, systems shutting down.</div>
                            </div>
                        </a>
                        <!-- Example Alert 4-->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <div class="dropdown-notifications-item-icon bg-success"><i data-feather="user-plus"></i></div>
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-details">December 2, 2021</div>
                                <div class="dropdown-notifications-item-content-text">New user request. Woody has requested access to the organization.</div>
                            </div>
                        </a>
                        <a class="dropdown-item dropdown-notifications-footer" href="#!">View All Alerts</a>
                    </div>
                </li>
                <!-- Messages Dropdown-->
                <li class="nav-item dropdown no-caret d-none d-sm-block me-3 dropdown-notifications">
                    <a class="btn btn-icon btn-transparent-dark dropdown-toggle" id="navbarDropdownMessages" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i data-feather="mail"></i></a>
                    <div class="dropdown-menu dropdown-menu-end border-0 shadow animated--fade-in-up" aria-labelledby="navbarDropdownMessages">
                        <h6 class="dropdown-header dropdown-notifications-header">
                            <i class="me-2" data-feather="mail"></i>
                            Message Center
                        </h6>
                        <!-- Example Message 1  -->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <img class="dropdown-notifications-item-img" src="assets/img/illustrations/profiles/profile-2.png" />
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-text">Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</div>
                                <div class="dropdown-notifications-item-content-details">Thomas Wilcox · 58m</div>
                            </div>
                        </a>
                        <!-- Example Message 2-->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <img class="dropdown-notifications-item-img" src="assets/img/illustrations/profiles/profile-3.png" />
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-text">Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</div>
                                <div class="dropdown-notifications-item-content-details">Emily Fowler · 2d</div>
                            </div>
                        </a>
                        <!-- Example Message 3-->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <img class="dropdown-notifications-item-img" src="assets/img/illustrations/profiles/profile-4.png" />
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-text">Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</div>
                                <div class="dropdown-notifications-item-content-details">Marshall Rosencrantz · 3d</div>
                            </div>
                        </a>
                        <!-- Example Message 4-->
                        <a class="dropdown-item dropdown-notifications-item" href="#!">
                            <img class="dropdown-notifications-item-img" src="assets/img/illustrations/profiles/profile-5.png" />
                            <div class="dropdown-notifications-item-content">
                                <div class="dropdown-notifications-item-content-text">Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</div>
                                <div class="dropdown-notifications-item-content-details">Colby Newton · 3d</div>
                            </div>
                        </a>
                        <!-- Footer Link-->
                        <a class="dropdown-item dropdown-notifications-footer" href="#!">Read All Messages</a>
                    </div>
                </li>
                <!-- User Dropdown-->
                <li class="nav-item dropdown no-caret dropdown-user me-3 me-lg-4">
                    <a class="btn btn-icon btn-transparent-dark dropdown-toggle" id="navbarDropdownUserImage" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        @if(auth()->user()->image)
                            <img class="rounded-circle" src="{{ auth()->user()->getImageUrl() }}" alt="User Profile" />
                        @else
                            <img class="rounded-circle" src="{{ asset('assets/img/illustrations/profiles/profile-1.png') }}" alt="User Profile" />
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end border-0 shadow animated--fade-in-up" aria-labelledby="navbarDropdownUserImage">
                        <h6 class="dropdown-header d-flex align-items-center">
                            @if(auth()->user()->image)
                                <img class="dropdown-user-img" src="{{ auth()->user()->getImageUrl() }}" alt="User Profile" />
                            @else
                                <img class="dropdown-user-img" src="{{ asset('assets/img/illustrations/profiles/profile-1.png') }}" alt="User Profile" />
                            @endif
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
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                        
                        <a class="dropdown-item" href="#" 
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <div class="dropdown-item-icon"><i data-feather="log-out"></i></div>
                            Logout
                        </a>

                    </div>
                </li>
            </ul>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sidenav shadow-right sidenav-light">
                    <div class="sidenav-menu">
                        <div class="nav accordion" id="accordionSidenav">
                        
                            <!-- Core Section -->
                            <div class="sidenav-menu-heading">Core</div>
                            <a class="nav-link" href="{{ route('dashboard') }}">
                                <div class="nav-link-icon"><i data-feather="activity"></i></div>
                                Dashboard
                            </a>
                        
                            <!-- Inventory Management Section -->
                            <div class="sidenav-menu-heading">Inventory Management</div>
                        
                            <!-- Items/Inventory -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseItems" aria-expanded="false" aria-controls="collapseItems">
                                <div class="nav-link-icon"><i data-feather="package"></i></div>
                                Items
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseItems" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('items.index') }}">All Items</a>
                                    <a class="nav-link" href="{{ route('items.create') }}">Add New Item</a>
                                    <a class="nav-link" href="{{ route('items.low-stock') }}">Low Stock Alerts</a>
                                </nav>
                            </div>
                        
                            <!-- Activities/Transactions -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseActivities" aria-expanded="false" aria-controls="collapseActivities">
                                <div class="nav-link-icon"><i data-feather="list"></i></div>
                                Transactions
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseActivities" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('transactions.index') }}">All Transactions</a>
                                    <a class="nav-link" href="{{ route('transactions.create') }}">New Transaction</a>
                                    <a class="nav-link" href="{{ route('transactions.pending') }}">Pending Approval</a>
                                </nav>
                            </div>
                        
                            <!-- Restaurant Management Section -->
                            <div class="sidenav-menu-heading">Restaurant</div>
                        
                            <!-- Menu Management -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseMenu" aria-expanded="false" aria-controls="collapseMenu">
                                <div class="nav-link-icon"><i data-feather="coffee"></i></div>
                                Menu Management
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseMenu" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('menu.index') }}">All Menu Items</a>
                                    <a class="nav-link" href="{{ route('menu.create') }}">Add New Menu Item</a>
                                </nav>
                            </div>

                            <!-- Sales Management -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseSales" aria-expanded="false" aria-controls="collapseSales">
                                <div class="nav-link-icon"><i data-feather="dollar-sign"></i></div>
                                Sales Management
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseSales" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('sales.index') }}">All Sales</a>
                                    <a class="nav-link" href="{{ route('sales.create') }}">New Sale</a>
                                </nav>
                            </div>
                        
                            <!-- Production Section -->
                            <div class="sidenav-menu-heading">Production & Delivery</div>
                        
                            <!-- Production Reports -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseProduction" aria-expanded="false" aria-controls="collapseProduction">
                                <div class="nav-link-icon"><i data-feather="box"></i></div>
                                Production Reports
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseProduction" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('production.index') }}">All Reports</a>
                                    <a class="nav-link" href="{{ route('production.processing') }}">Processing</a>
                                </nav>
                            </div>

                            <!-- Scrap Materials -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseWaste" aria-expanded="false" aria-controls="collapseWaste">
                                <div class="nav-link-icon"><i data-feather="trash-2"></i></div>
                                Scrap Materials
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseWaste" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('scrap.index') }}">List Scrap Waste</a>
                                </nav>
                            </div>
                        
                            <!-- Delivery -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseDeliveries" aria-expanded="false" aria-controls="collapseDeliveries">
                                <div class="nav-link-icon"><i data-feather="truck"></i></div>
                                Delivery
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseDeliveries" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('delivery.index') }}">All Deliveries</a>
                                    <a class="nav-link" href="{{ route('delivery.create') }}">New Delivery</a>
                                    <a class="nav-link" href="{{ route('delivery.pending') }}">Pending Approval</a>
                                </nav>
                            </div>
                        
                            <!-- Suppliers Section -->
                            <div class="sidenav-menu-heading">Partners</div>
                        
                            <!-- Suppliers -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseSuppliers" aria-expanded="false" aria-controls="collapseSuppliers">
                                <div class="nav-link-icon"><i data-feather="shopping-bag"></i></div>
                                Suppliers
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseSuppliers" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('suppliers.index') }}">All Suppliers</a>
                                    <a class="nav-link" href="{{ route('suppliers.create') }}">Add New Supplier</a>
                                </nav>
                            </div>

                            <!-- Reports Section -->
                            <div class="sidenav-menu-heading">Reports & Analytics</div>

                            <!-- Reports -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseReports" aria-expanded="false" aria-controls="collapseReports">
                                <div class="nav-link-icon"><i data-feather="bar-chart-2"></i></div>
                                Reports
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseReports" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('reports.inventory.index') }}">Inventory Report</a>
                                    <a class="nav-link" href="{{ route('reports.transaction.index') }}">Transaction Report</a>
                                    <a class="nav-link" href="{{ route('reports.delivery.index') }}">Delivery Report</a>
                                </nav>
                            </div>

                            <!-- Web Management Section -->
                            <div class="sidenav-menu-heading">Web Management</div>

                            <!-- Blogs -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseBlogs" aria-expanded="false" aria-controls="collapseBlogs">
                                <div class="nav-link-icon"><i data-feather="layout"></i></div>
                                Blogs
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseBlogs" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('blogs.index') }}">All Blogs</a>
                                    <a class="nav-link" href="{{ route('blogs.create') }}">New Blog Post</a>
                                </nav>
                            </div>

                            <!-- Feedback -->
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" 
                               data-bs-target="#collapseFeedback" aria-expanded="false" aria-controls="collapseFeedback">
                                <div class="nav-link-icon"><i data-feather="message-square"></i></div>
                                Feedback
                                <div class="sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseFeedback" data-bs-parent="#accordionSidenav">
                                <nav class="sidenav-menu-nested nav">
                                    <a class="nav-link" href="{{ route('feedback.index') }}">All Feedback</a>
                                    <a class="nav-link" href="{{ route('feedback.statistics') }}">Statistics</a>
                                </nav>
                            </div>

                            <!-- Management Section -->
                            <div class="sidenav-menu-heading">System Management</div>
                        
                            <!-- Users -->
                            <a class="nav-link" href="{{ route('users.index') }}">
                                <div class="nav-link-icon"><i data-feather="users"></i></div>
                                Users
                            </a>
                        
                            <!-- Settings -->
                            <a class="nav-link" href="{{ route('settings.show') }}">
                                <div class="nav-link-icon"><i data-feather="settings"></i></div>
                                Settings
                            </a>
                        
                        </div>
                    </div>
                
                    <!-- Sidenav Footer -->
                    <div class="sidenav-footer">
                        <div class="sidenav-footer-content">
                            <div class="sidenav-footer-subtitle">Logged in as:</div>
                            <div class="sidenav-footer-title text-truncate">{{ auth()->user()->name }}</div>
                        </div>
                    </div>
                </nav>