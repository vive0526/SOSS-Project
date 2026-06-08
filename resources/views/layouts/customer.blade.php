<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Customer Dashboard')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    @vite(['resources/css/app.css', 'resources/css/customer.css', 'resources/js/app.js'])
</head>
<body class="customer-body @yield('body_class')">
    <div class="customer-shell">
        <aside class="customer-sidebar">
            <div class="customer-brand">
                <a href="{{ url('/') }}" class="customer-brand__link">SOSS</a>
                <div class="customer-brand__sub">Marketplace</div>
            </div>

            <nav class="customer-nav">
                <a class="customer-nav__link {{ request()->is('customer/dashboard') ? 'is-active' : '' }}"
                   href="{{ url('/customer/dashboard') }}">
                    Dashboard
                </a>
                <a class="customer-nav__link {{ request()->is('customer/products') || request()->is('customer/products/*') ? 'is-active' : '' }}"
                   href="{{ route('customer.products.index') }}">
                    Products
                </a>
                <a class="customer-nav__link {{ request()->is('customer/orders') || request()->is('customer/orders/*') ? 'is-active' : '' }}"
                   href="{{ route('customer.orders.index') }}">
                    Orders
                </a>
                <a class="customer-nav__link {{ request()->is('customer/return-requests') || request()->is('customer/return-requests/*') ? 'is-active' : '' }}"
                   href="{{ route('customer.return-requests.index') }}">
                    Return Requests
                </a>
                <a class="customer-nav__link {{ request()->is('customer/cattle-requests') || request()->is('customer/cattle-requests/*') ? 'is-active' : '' }}"
                   href="{{ route('customer.cattle-requests.index') }}">
                    Cattle Requests
                </a>
                <a class="customer-nav__link {{ request()->is('customer/cart') || request()->is('customer/checkout*') ? 'is-active' : '' }}"
                   href="{{ route('customer.cart.index') }}">
                    Cart
                </a>

                <div class="customer-nav__section">Notifications</div>
                <a class="customer-nav__link {{ request()->is('customer/notifications') ? 'is-active' : '' }}"
                   href="{{ route('customer.notifications.index') }}">
                    Notifications
                    @if(!empty($customerUnreadNotificationsCount))
                        <span style="margin-left:auto; font-weight:900; color:#c0161a;">
                            {{ $customerUnreadNotificationsCount }}
                        </span>
                    @endif
                </a>
                <a class="customer-nav__link {{ request()->is('customer/updates') ? 'is-active' : '' }}"
                   href="{{ route('customer.updates.index') }}">
                    Order Updates
                </a>
                <a class="customer-nav__link {{ request()->is('customer/discounts') ? 'is-active' : '' }}"
                   href="{{ route('customer.discounts.index') }}">
                    Discounts
                </a>
            </nav>
        </aside>

        <div class="customer-main">
            <header class="customer-header">
                <div>
                    <div class="customer-header__title">
                        @yield('page_title', 'Customer Dashboard')
                    </div>
                    <div class="customer-header__subtitle">
                        @yield('page_subtitle', 'Browse the latest products')
                    </div>
                </div>

                <div class="customer-user">
                    @auth
                        @php
                            $user = auth()->user();
                        @endphp
                        <button class="customer-user__btn" type="button" id="customerUserBtn">
                            <span class="customer-user__avatar">
                                @if($user->profile_photo)
                                    <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="Avatar">
                                @else
                                    {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                @endif
                            </span>
                            <span class="customer-user__name">{{ $user->name }}</span>
                            <span class="customer-user__chev">v</span>
                        </button>

                        <div class="customer-user__menu" id="customerUserMenu">
                            <a class="customer-user__item" href="{{ route('profile.edit') }}">
                                Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="customer-user__item customer-user__logout">
                                    Log Out
                                </button>
                            </form>
                        </div>
                    @endauth
                </div>
            </header>

            <main class="customer-content">
                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.background_sound')

    <script>
        (function () {
            document.body.classList.add('has-js');

            const btn = document.getElementById('customerUserBtn');
            const menu = document.getElementById('customerUserMenu');
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
