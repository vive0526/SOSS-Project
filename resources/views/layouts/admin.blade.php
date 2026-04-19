<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="admin-body @yield('body_class')">
    {{-- Cursor spotlight layer (ADMIN ONLY, click-safe) --}}
    <div class="admin-spotlight" id="adminSpotlight"></div>

    @php
        $userRole = auth()->check() ? auth()->user()->role : null;
        $isAdmin = $userRole === 'admin';
        $isStaff = $userRole === 'staff';
        $profileRouteName = $isAdmin ? 'admin.profile.edit' : 'profile.edit';
        $brandRole = $isStaff ? 'Staff' : 'Admin';
    @endphp

    <div class="admin-shell">
        {{-- SIDEBAR --}}
        <aside class="admin-sidebar">
            <div class="admin-sidebar__brand">
                <a href="{{ url('/') }}" class="admin-brand">
                    <span class="admin-brand__mark">SOSS</span>
                    <span class="admin-brand__sub">{{ $brandRole }}</span>
                </a>
            </div>

              <nav class="admin-nav">
                @if($isStaff)
                    {{-- Dashboard --}}
                    <a class="admin-nav__link {{ request()->is('staff/dashboard') ? 'is-active' : '' }}"
                       href="{{ url('/staff/dashboard') }}">
                        Dashboard
                    </a>

                    {{-- User Management --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-user">
                        User Management
                        <span class="admin-nav__chev">ƒ-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-user">
                        <a class="admin-submenu__link {{ request()->is('staff/users') || request()->is('staff/users/*') ? 'is-active' : '' }}"
                           href="{{ route('staff.users.index') }}">
                            Users
                        </a>
                        <a class="admin-submenu__link {{ request()->is('staff/users/create') ? 'is-active' : '' }}"
                           href="{{ route('staff.users.create') }}">
                            Create User
                        </a>
                    </div>

                    {{-- Payment Process --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-payment">
                        Payment Process
                        <span class="admin-nav__chev">ƒ-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-payment">
                        <a class="admin-submenu__link {{ request()->fullUrlIs('*orders*payment=unverified*') ? 'is-active' : '' }}"
                           href="{{ route('orders.index', ['payment' => 'unverified']) }}">
                            Payment Verification
                        </a>
                        <a class="admin-submenu__link {{ request()->fullUrlIs('*orders*shipment_status=pending*') ? 'is-active' : '' }}"
                           href="{{ route('orders.index', ['shipment_status' => 'pending', 'payment' => 'verified']) }}">
                            Shipment Process
                        </a>
                    </div>

                    {{-- Products --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-products">
                        Products
                        <span class="admin-nav__chev">’'-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-products">
                        <a class="admin-submenu__link {{ request()->is('products') || request()->is('products/*') ? 'is-active' : '' }}"
                           href="{{ route('products.index') }}">
                            Product List
                        </a>
                        <a class="admin-submenu__link {{ request()->is('products/inventory') ? 'is-active' : '' }}"
                           href="{{ route('products.inventory') }}">
                            Inventory
                        </a>
                        <a class="admin-submenu__link {{ request()->is('categories') ? 'is-active' : '' }}"
                           href="{{ route('categories.index') }}">
                            Categories
                        </a>
                    </div>

                    {{-- Orders --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-orders-staff">
                        Orders
                        <span class="admin-nav__chev">’'-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-orders-staff">
                        <a class="admin-submenu__link {{ request()->is('orders') || request()->is('orders/*') ? 'is-active' : '' }}"
                           href="{{ route('orders.index') }}">
                            Orders List
                        </a>
                        <a class="admin-submenu__link {{ request()->is('cattle-requests') || request()->is('cattle-requests/*') ? 'is-active' : '' }}"
                           href="{{ route('cattle-requests.index') }}">
                            Cattle Requests
                        </a>
                    </div>

                    {{-- Report --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-report">
                        Report
                        <span class="admin-nav__chev">ƒ-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-report">
                        <a class="admin-submenu__link" href="#">
                            Inventory Updates
                        </a>
                        <a class="admin-submenu__link {{ request()->is('inventory/reports/levels') ? 'is-active' : '' }}"
                           href="{{ route('inventory.reports.levels') }}">
                            Inventory Level Report
                        </a>
                        <a class="admin-submenu__link {{ request()->is('inventory/reports/low-stock') ? 'is-active' : '' }}"
                           href="{{ route('inventory.reports.low-stock') }}">
                            Low Stock Report
                        </a>
                        <a class="admin-submenu__link {{ request()->is('inventory/reports/movements') ? 'is-active' : '' }}"
                           href="{{ route('inventory.reports.movements') }}">
                            Stock Movement Report
                        </a>
                    </div>
                @else
                    {{-- Dashboard --}}
                    <a class="admin-nav__link {{ request()->is('admin/dashboard') ? 'is-active' : '' }}"
                       href="{{ url('/admin/dashboard') }}">
                        Dashboard
                    </a>

                    {{-- User Management --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-user">
                        User Management
                        <span class="admin-nav__chev">ƒ-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-user">
                        <a class="admin-submenu__link {{ request()->is('users') ? 'is-active' : '' }}"
                           href="{{ route('users.index') }}">
                            Users
                        </a>
                        <a class="admin-submenu__link" href="#">
                            Roles
                        </a>
                    </div>

                    {{-- Report --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-report-admin">
                        Report
                        <span class="admin-nav__chev">ƒ-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-report-admin">
                        <a class="admin-submenu__link" href="#">
                            Inventory Updates
                        </a>
                        <a class="admin-submenu__link {{ request()->is('reports/orders/summary') ? 'is-active' : '' }}"
                           href="{{ route('orders.reports.summary') }}">
                            Order Summary
                        </a>
                        <a class="admin-submenu__link {{ request()->is('inventory/reports/levels') ? 'is-active' : '' }}"
                           href="{{ route('inventory.reports.levels') }}">
                            Inventory Level Report
                        </a>
                        <a class="admin-submenu__link {{ request()->is('inventory/reports/low-stock') ? 'is-active' : '' }}"
                           href="{{ route('inventory.reports.low-stock') }}">
                            Low Stock Report
                        </a>
                        <a class="admin-submenu__link {{ request()->is('inventory/reports/movements') ? 'is-active' : '' }}"
                           href="{{ route('inventory.reports.movements') }}">
                            Stock Movement Report
                        </a>
                    </div>

                    {{-- Products --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-products">
                        Products
                        <span class="admin-nav__chev">’'-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-products">
                        <a class="admin-submenu__link {{ request()->is('products') || request()->is('products/*') ? 'is-active' : '' }}"
                           href="{{ route('products.index') }}">
                            Products
                        </a>
                        @if(auth()->check() && auth()->user()->role === 'admin')
                            <a class="admin-submenu__link {{ request()->is('products/inventory') ? 'is-active' : '' }}"
                               href="{{ route('products.inventory') }}">
                                Inventory
                            </a>
                            <a class="admin-submenu__link {{ request()->is('categories') ? 'is-active' : '' }}"
                               href="{{ route('categories.index') }}">
                                Categories
                            </a>
                        @endif
                    </div>

                    {{-- Orders --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-orders">
                        Orders
                        <span class="admin-nav__chev">’'-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-orders">
                        <a class="admin-submenu__link {{ request()->is('orders') || request()->is('orders/*') ? 'is-active' : '' }}"
                           href="{{ route('orders.index') }}">
                            All Orders
                        </a>
                        <a class="admin-submenu__link {{ request()->is('cattle-requests') || request()->is('cattle-requests/*') ? 'is-active' : '' }}"
                           href="{{ route('cattle-requests.index') }}">
                            Cattle Requests
                        </a>
                    </div>

                    {{-- General Settings --}}
                    <button class="admin-nav__link admin-nav__toggle"
                            type="button"
                            data-toggle="submenu"
                            data-target="#submenu-gs">
                        General Settings
                        <span class="admin-nav__chev">ƒ-_</span>
                    </button>
                    <div class="admin-submenu" id="submenu-gs">
                        <a class="admin-submenu__link {{ request()->is('regions') ? 'is-active' : '' }}"
                           href="{{ route('regions.index') }}">
                            Regions
                        </a>
                        <a class="admin-submenu__link {{ request()->is('operating-units') ? 'is-active' : '' }}"
                           href="{{ route('operating-units.index') }}">
                            Operating Units
                        </a>
                        <a class="admin-submenu__link {{ request()->is('companies') ? 'is-active' : '' }}"
                           href="{{ route('companies.index') }}">
                            Company
                        </a>
                        <a class="admin-submenu__link {{ request()->is('codes') ? 'is-active' : '' }}"
                           href="{{ route('codes.index') }}">
                            Codes
                        </a>
                    </div>

                    {{-- Monitor Verified Payments --}}
                    <a class="admin-nav__link {{ request()->fullUrlIs('*orders*payment=unverified*') ? 'is-active' : '' }}"
                       href="{{ route('orders.index', ['payment' => 'unverified']) }}">
                        Monitor Verified Payments
                    </a>
                @endif
            </nav>

            <div class="admin-sidebar__footer">
                <div class="admin-footnote">Premium Black / Gold Theme</div>
            </div>
        </aside>

        {{-- MAIN --}}
        <div class="admin-main">
            {{-- HEADER --}}
            <header class="admin-header">
                <div class="admin-header__left">
                    <div class="admin-header__title">
                        @yield('page_title', 'Dashboard')
                    </div>
                    <div class="admin-header__crumb">
                        @yield('page_subtitle', 'Welcome to your control panel')
                    </div>
                </div>

                <div class="admin-header__right">
                    @auth
                        @php
                            $user = auth()->user();
                        @endphp

                        <div class="admin-user">
                            <button class="admin-user__btn" type="button" id="adminUserBtn">
                                <span class="admin-avatar">
                                    @if($user->profile_photo)
                                        <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="Avatar">
                                    @else
                                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                    @endif
                                </span>

                                <span class="admin-user__name">
                                    {{ $user->name }}
                                </span>

                                <span class="admin-user__chev">ƒ-_</span>
                            </button>

                            <div class="admin-user__menu" id="adminUserMenu">
                                <a class="admin-user__item" href="{{ route($profileRouteName) }}">
                                    Profile
                                </a>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                            class="admin-user__item admin-user__logout">
                                        Log Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </header>

            {{-- CONTENT --}}
            <main class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Admin-only JS --}}
    <script>
        // Cursor-follow spotlight (ADMIN ONLY)
        (function () {
            const root = document.documentElement;
            const spotlight = document.getElementById('adminSpotlight');
            if (!spotlight) return;

            window.addEventListener('mousemove', (e) => {
                root.style.setProperty('--mx', e.clientX + 'px');
                root.style.setProperty('--my', e.clientY + 'px');
            }, { passive: true });
        })();

        // Sidebar submenu toggle
        (function () {
            document.querySelectorAll('[data-toggle="submenu"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const target = document.querySelector(btn.dataset.target);
                    if (target) target.classList.toggle('is-open');
                });
            });
        })();

        // User dropdown
        (function () {
            const btn = document.getElementById('adminUserBtn');
            const menu = document.getElementById('adminUserMenu');
            if (!btn || !menu) return;

            btn.addEventListener('click', () => {
                menu.classList.toggle('is-open');
            });

            document.addEventListener('click', (e) => {
                if (!menu.classList.contains('is-open')) return;
                if (btn.contains(e.target) || menu.contains(e.target)) return;
                menu.classList.remove('is-open');
            });
        })();
    </script>
</body>
</html>
