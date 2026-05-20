<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sawit Online Sales System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="welcome-page">
@php
    $shopNowUrl = route('login');
    $primaryCtaUrl = auth()->check() ? route('dashboard') : $shopNowUrl;
    $primaryCtaLabel = auth()->check() ? 'Go to Dashboard' : 'Shop Now';
@endphp

<header class="welcome-header">
    <a class="welcome-brand" href="{{ route('welcome') }}">
        <span class="welcome-brand__mark">SOSS</span>
        <span class="welcome-brand__sub">Sawit Online Sales System</span>
    </a>

    <nav class="welcome-nav">
        <a href="#products">Products</a>
        <a href="#about">About Us</a>
        <a href="#contact">Contact</a>
        @auth
            <a href="{{ route('dashboard') }}" class="welcome-nav__pill">Dashboard</a>
        @endauth
    </nav>

    <div class="welcome-actions">
        <a href="{{ $primaryCtaUrl }}" class="btn btn-outline welcome-shop">
            {{ $primaryCtaLabel }}
        </a>
    </div>
</header>

<main>
    <section class="welcome-hero">
        <div class="welcome-hero__content">
            <div class="welcome-kicker">Fresh stock • Fast checkout • Secure access</div>
            <h1 class="welcome-title">Everything you need to run your sales.</h1>
            <p class="welcome-lead">
                Browse products, track stock, place orders, and manage operations in one modern system.
                Pick a product, then click <strong>Shop Now</strong> to login and purchase.
            </p>

            <div class="welcome-cta-row">
                <a href="{{ $primaryCtaUrl }}" class="btn btn-primary welcome-cta-btn">
                    {{ $primaryCtaLabel }}
                </a>
            </div>

            <div class="welcome-metrics">
                <div class="welcome-metric">
                    <div class="welcome-metric__value">Live</div>
                    <div class="welcome-metric__label">Stock visibility</div>
                </div>
                <div class="welcome-metric">
                    <div class="welcome-metric__value">Role</div>
                    <div class="welcome-metric__label">Based security</div>
                </div>
                <div class="welcome-metric">
                    <div class="welcome-metric__value">Fast</div>
                    <div class="welcome-metric__label">Order workflow</div>
                </div>
            </div>
        </div>

        <aside class="welcome-hero__showcase" aria-label="Featured product preview">
            <div class="welcome-showcase">
                <div class="welcome-showcase__bg"></div>

                <div class="welcome-showcase__image">
                    @if(!empty($heroProduct?->image))
                        <img src="{{ asset('storage/' . $heroProduct->image) }}" alt="{{ $heroProduct->name }}">
                    @else
                        <div class="welcome-showcase__placeholder">Your product image will appear here</div>
                    @endif
                </div>

                <div class="welcome-showcase__panel">
                    <div class="welcome-panel__title">In-stock picks</div>
                    <div class="welcome-panel__subtitle">
                        Showing {{ $featuredProducts->count() }} available product{{ $featuredProducts->count() === 1 ? '' : 's' }}.
                    </div>

                    <div class="welcome-panel__list">
                        @forelse($featuredProducts as $product)
                            <div class="welcome-panel__item">
                                @php
                                    $displayPrice = $product->price;
                                    $displayPricePrefix = '';
                                    if ($product->requires_maintenance && !empty($product->maintenance_prices)) {
                                        $maintenancePrices = $product->maintenance_prices ?? [];
                                        if (!empty($maintenancePrices)) {
                                            $displayPrice = min($maintenancePrices);
                                            $displayPricePrefix = 'From ';
                                        }
                                    }
                                @endphp
                                <div class="welcome-panel__thumb">
                                    @if($product->image)
                                        <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
                                    @else
                                        <span class="welcome-panel__thumbText">No image</span>
                                    @endif
                                </div>
                                <div class="welcome-panel__info">
                                    <div class="welcome-panel__name">{{ $product->name }}</div>
                                    <div class="welcome-panel__desc">
                                        {{ \Illuminate\Support\Str::limit($product->description, 52) }}
                                    </div>
                                </div>
                                <div class="welcome-panel__price">
                                    {{ $displayPrice !== null ? $displayPricePrefix . 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
                                </div>
                            </div>
                        @empty
                            <div class="welcome-empty">
                                No products are currently in stock.
                            </div>
                        @endforelse
                    </div>

                    @guest
                        <a href="{{ route('login') }}" class="btn btn-outline welcome-panel__btn">
                            Shop Now
                        </a>
                    @else
                        <a href="{{ route('dashboard') }}" class="btn btn-outline welcome-panel__btn">
                            Continue
                        </a>
                    @endguest
                </div>
            </div>
        </aside>
    </section>

    <section class="welcome-about" id="about" aria-labelledby="about-title">
        <div class="welcome-section-head">
            <h2 id="about-title">About Us</h2>
            <p>Learn a little more about our company and how we serve you.</p>
        </div>

        <div class="welcome-about-card">
            <div class="welcome-about-card__logo">
                <img src="{{ asset('images/sawit-kinabalu-logo.png') }}" alt="Sawit Kinabalu logo" loading="lazy">
            </div>

            <div class="welcome-about-card__content">
                <div class="welcome-about-card__name">SAWIT KINABALU</div>
                <div class="welcome-about-card__desc">
                    We believe development and nature can coexist. Through strong partnerships with government agencies, NGOs, researchers, and local communities, we work to protect and restore Sabah’s forests while creating lasting environmental value. 
                    Together, we can cultivate a greener tomorrow.
                </div>

                <ul class="welcome-about-points">
                    <li>Quality-first products and consistent availability</li>
                    <li>Fast, organized order processing</li>
                    <li>Secure access for customers and staff</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="welcome-products" id="products">
        <div class="welcome-section-head">
            <h2>Featured Products</h2>
            <p>Always-in-stock items from your product list.</p>
        </div>

        <div class="welcome-product-grid">
            @forelse($featuredProducts as $product)
                @php
                    $availableStock = $product->availableStock();
                @endphp
                <article class="welcome-product-card">
                    <div class="welcome-product-card__media">
                        @if($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
                        @else
                            <div class="welcome-product-card__placeholder">No image</div>
                        @endif
                    </div>
                    <div class="welcome-product-card__body">
                        <div class="welcome-product-card__meta">
                            <span class="welcome-badge">
                                {{ $product->category?->name ?? 'Product' }}
                            </span>
                            <span class="welcome-stock">
                                {{ $availableStock }} in stock
                            </span>
                        </div>
                        <h3 class="welcome-product-card__title">{{ $product->name }}</h3>
                        <p class="welcome-product-card__desc">
                            {{ \Illuminate\Support\Str::limit($product->description, 120) }}
                        </p>
                        <div class="welcome-product-card__footer">
                            <div class="welcome-product-card__price">
                                @php
                                    $displayPrice = $product->price;
                                    $displayPricePrefix = '';
                                    if ($product->requires_maintenance && !empty($product->maintenance_prices)) {
                                        $maintenancePrices = $product->maintenance_prices ?? [];
                                        if (!empty($maintenancePrices)) {
                                            $displayPrice = min($maintenancePrices);
                                            $displayPricePrefix = 'From ';
                                        }
                                    }
                                @endphp
                                {{ $displayPrice !== null ? $displayPricePrefix . 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
                            </div>
                            <a href="{{ route('login') }}" class="btn btn-primary">
                                Shop Now
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="welcome-empty">Add products with stock &gt; 0 to show them here.</div>
            @endforelse
        </div>
    </section>

    <footer class="welcome-footer" id="contact">
        <div class="welcome-footer__inner">
            <div>
                <div class="welcome-footer__brand">SOSS</div>
                <div class="welcome-footer__muted">
                    Sawit Online Sales System • {{ now()->format('Y') }}
                </div>
            </div>
        </div>
    </footer>
</main>

</body>
</html>
