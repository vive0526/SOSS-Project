<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Store')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront-body">
    <div class="sf-topbar">
        <div class="sf-container sf-topbar__inner">
            <div class="sf-topbar__message">
                Chilled &amp; Frozen Delivery • Secure FPX Payments • Fast Support
            </div>
            <div class="sf-topbar__links">
                <a href="{{ route('welcome') }}">Help</a>
                <span class="sf-topbar__dot">•</span>
                <a href="{{ route('welcome') }}">Contact</a>
            </div>
        </div>
    </div>

    <header class="sf-header">
        <div class="sf-container sf-header__inner">
            <a class="sf-brand" href="{{ url('/customer/dashboard') }}">
                <span class="sf-brand__name">SOSS</span>
                <span class="sf-brand__sub">Marketplace</span>
            </a>

            <form class="sf-search" method="GET" action="{{ route('customer.products.index') }}">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search products, categories…"
                    aria-label="Search products"
                >
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <nav class="sf-actions" aria-label="Store actions">
                <div class="sf-notifications">
                    <button class="sf-action" type="button" id="sfNotifBtn" aria-haspopup="true">
                        Notifications
                        @if(!empty($storefrontUnreadNotificationsCount))
                            <span class="sf-badge" aria-label="Unread notifications">{{ $storefrontUnreadNotificationsCount }}</span>
                        @endif
                    </button>
                    <div class="sf-notif-menu" id="sfNotifMenu">
                        <div class="sf-notif-menu__head">
                            <div class="sf-notif-menu__title">Notifications</div>
                            <a class="sf-notif-menu__link" href="{{ route('customer.notifications.index') }}">View all</a>
                        </div>

                        @php
                            $preview = $storefrontNotificationsPreview ?? collect();
                        @endphp
                        @if($preview->isEmpty())
                            <div class="sf-notif-menu__empty">No notifications yet.</div>
                        @else
                            <div class="sf-notif-menu__list">
                                @foreach($preview as $notification)
                                    @php
                                        $data = $notification->data ?? [];
                                        $title = $data['title'] ?? 'Notification';
                                        $message = $data['message'] ?? '';
                                        $actionUrl = $data['action_url'] ?? null;
                                        $unread = $notification->read_at === null;
                                    @endphp
                                    <a class="sf-notif-item {{ $unread ? 'is-unread' : '' }}"
                                       href="{{ $actionUrl ?: route('customer.notifications.index') }}">
                                        <div class="sf-notif-item__title">{{ $title }}</div>
                                        @if($message)
                                            <div class="sf-notif-item__msg">{{ $message }}</div>
                                        @endif
                                        <div class="sf-notif-item__meta">
                                            {{ $notification->created_at?->diffForHumans() }}
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <a class="sf-action" href="{{ route('customer.orders.index') }}">
                    Account
                </a>
                <a class="sf-action sf-action--cart" href="{{ route('customer.cart.index') }}">
                    Cart
                    @if(!empty($storefrontCartCount))
                        <span class="sf-badge" aria-label="Cart items">{{ $storefrontCartCount }}</span>
                    @endif
                </a>

                <div class="customer-user">
                    @auth
                        @php
                            $user = auth()->user();
                        @endphp
                        <button class="customer-user__btn" type="button" id="customerUserBtn" aria-haspopup="true">
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
            </nav>
        </div>

        <div class="sf-nav">
            <div class="sf-container sf-nav__inner">
                <a class="sf-nav__link {{ request()->is('customer/products') ? 'is-active' : '' }}"
                   href="{{ route('customer.products.index') }}">
                    All
                </a>
                @foreach(($storefrontNavCategories ?? collect()) as $category)
                    <a class="sf-nav__link {{ (string) request('category_id') === (string) $category->id ? 'is-active' : '' }}"
                       href="{{ route('customer.products.index', ['category_id' => $category->id]) }}">
                        {{ $category->name }}
                    </a>
                @endforeach
                <a class="sf-nav__link sf-nav__deals" href="{{ route('customer.products.index', ['sort' => 'newest']) }}">
                    Deals
                </a>
            </div>
        </div>
    </header>

    <main class="sf-main">
        @if(!trim($__env->yieldContent('hide_masthead')))
            <div class="sf-container sf-masthead">
                <div class="sf-masthead__title">@yield('page_title')</div>
                <div class="sf-masthead__subtitle">@yield('page_subtitle')</div>
            </div>
        @endif

        <div class="sf-container sf-content">
            @yield('content')
        </div>
    </main>

    <footer class="sf-footer">
        <div class="sf-container sf-footer__grid">
            <div>
                <div class="sf-footer__title">Shop</div>
                <a class="sf-footer__link" href="{{ route('customer.products.index') }}">All Products</a>
                <a class="sf-footer__link" href="{{ route('customer.products.index', ['sort' => 'newest']) }}">New Arrivals</a>
                <a class="sf-footer__link" href="{{ route('customer.products.index') }}">Collections</a>
            </div>
            <div>
                <div class="sf-footer__title">Support</div>
                <a class="sf-footer__link" href="{{ route('profile.edit') }}">Help Center</a>
                <a class="sf-footer__link" href="{{ route('profile.edit') }}">Shipping</a>
                <a class="sf-footer__link" href="{{ route('profile.edit') }}">Returns</a>
            </div>
            <div>
                <div class="sf-footer__title">Company</div>
                <a class="sf-footer__link" href="{{ url('/') }}">About</a>
                <a class="sf-footer__link" href="{{ url('/') }}">Contact</a>
                <a class="sf-footer__link" href="{{ url('/') }}">Policies</a>
            </div>
            <div>
                <div class="sf-footer__title">Account</div>
                <a class="sf-footer__link" href="{{ route('customer.orders.index') }}">Orders</a>
                <a class="sf-footer__link" href="{{ route('profile.edit') }}">Profile</a>
                <a class="sf-footer__link" href="{{ route('customer.cart.index') }}">Cart</a>
            </div>
        </div>
        <div class="sf-container sf-footer__bottom">
            <div class="sf-footer__fineprint">
                © {{ date('Y') }} SOSS Marketplace. All rights reserved.
            </div>
        </div>
    </footer>

    @stack('modals')

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

            const notifBtn = document.getElementById('sfNotifBtn');
            const notifMenu = document.getElementById('sfNotifMenu');
            if (!notifBtn || !notifMenu) return;

            notifBtn.addEventListener('click', () => {
                notifMenu.classList.toggle('is-open');
            });

            document.addEventListener('click', (e) => {
                if (!notifMenu.classList.contains('is-open')) return;
                if (notifBtn.contains(e.target) || notifMenu.contains(e.target)) return;
                notifMenu.classList.remove('is-open');
            });
        })();
    </script>
</body>
</html>
