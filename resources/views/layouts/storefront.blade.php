<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Store')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    @vite(['resources/css/app.css', 'resources/css/customer.css', 'resources/js/app.js'])
</head>
<body class="storefront-body @yield('body_class')">
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
            @if(session('warning'))
                <div class="customer-alert" id="sfFlashWarning" style="margin-bottom:16px;">
                    <div class="customer-alert__title">Please note</div>
                    <div>{{ session('warning') }}</div>
                </div>
                <script>
                    (function () {
                        const el = document.getElementById('sfFlashWarning');
                        if (!el) return;
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        setTimeout(() => {
                            el.style.transition = 'opacity 250ms ease';
                            el.style.opacity = '0';
                        }, 6500);
                    })();
                </script>
            @endif
            @yield('content')
        </div>
    </main>

    <footer class="sf-footer">
        <div class="sf-container sf-footer__simple">
            <div class="sf-footer__fineprint">© 2026 SOSS Marketplace. All rights reserved.</div>
        </div>
    </footer>

    @stack('modals')

    @include('partials.background_sound')

    <script>
        (function () {
            document.body.classList.add('has-js');

            const userBtn = document.getElementById('customerUserBtn');
            const userMenu = document.getElementById('customerUserMenu');
            if (userBtn && userMenu) {
                userBtn.addEventListener('click', () => {
                    userMenu.classList.toggle('is-open');
                });

                document.addEventListener('click', (e) => {
                    if (!userMenu.classList.contains('is-open')) return;
                    if (userBtn.contains(e.target) || userMenu.contains(e.target)) return;
                    userMenu.classList.remove('is-open');
                });
            }

            const notifBtn = document.getElementById('sfNotifBtn');
            const notifMenu = document.getElementById('sfNotifMenu');
            if (notifBtn && notifMenu) {
                notifBtn.addEventListener('click', () => {
                    notifMenu.classList.toggle('is-open');
                });

                document.addEventListener('click', (e) => {
                    if (!notifMenu.classList.contains('is-open')) return;
                    if (notifBtn.contains(e.target) || notifMenu.contains(e.target)) return;
                    notifMenu.classList.remove('is-open');
                });
            }
        })();
    </script>
</body>
</html>
