@extends('layouts.storefront')

@section('title', 'Home')
@section('hide_masthead', '1')

@push('modals')
    @php
        $user = auth()->user();
    @endphp
    @if($user && !$user->isCheckoutProfileComplete() && session('show_profile_completion_modal') && !session('profile_prompt_dismissed'))
        @include('customer.partials.profile_completion_modal')
    @endif
@endpush

@section('content')
    @php
        $heroProduct = $featuredProducts->firstWhere('image', '!=', null);
        $heroImageUrl = $heroProduct?->image ? asset('storage/' . $heroProduct->image) : null;
    @endphp

    <section class="sf-hero" @if($heroImageUrl) style="--sf-hero-image: url('{{ $heroImageUrl }}');" @endif>
        <div class="sf-hero__content">
            <div class="sf-kicker">Premium marketplace</div>
            <h1 class="sf-hero__title">Premium quality, delivered fresh.</h1>
            <p class="sf-hero__subtitle">
                Explore {{ $totalProducts }} products across {{ $categoryCount }} categories — packed with care and shipped fast.
            </p>

            <div class="sf-hero__actions">
                <a class="btn btn-primary" href="{{ route('customer.products.index') }}">Shop best sellers</a>
                <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Browse collections</a>
                <a class="btn btn-outline" href="{{ route('customer.addresses.index') }}">My Addresses</a>
            </div>

            <div class="sf-trust">
                <div class="sf-trust__item">Secure payment</div>
                <div class="sf-trust__item">Chilled packing</div>
                <div class="sf-trust__item">Fast support</div>
            </div>
        </div>

        <div class="sf-hero__visual" aria-hidden="true"></div>
    </section>

    <section class="sf-section">
        <div class="sf-section__head">
            <h2>Shop by Collection</h2>
            <a class="sf-link" href="{{ route('customer.products.index') }}">View all</a>
        </div>

        <div class="sf-collections">
            @foreach(($collections ?? collect()) as $collection)
                <a class="sf-collection" href="{{ route('customer.products.index', ['category_id' => $collection->id]) }}">
                    <div class="sf-collection__name">{{ $collection->name }}</div>
                    <div class="sf-collection__meta">{{ $collection->products_count }} items</div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="sf-section">
        <div class="sf-section__head">
            <h2>Featured Products</h2>
            <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Browse all</a>
        </div>

        <div class="customer-products sf-products">
            @forelse($featuredProducts as $product)
                @php
                    $displayPrice = $product->price;
                    $displayPricePrefix = '';
                    $availableStock = $product->availableStock();
                    if ($product->requires_maintenance && !empty($product->maintenance_prices)) {
                        $maintenancePrices = $product->maintenance_prices ?? [];
                        if (!empty($maintenancePrices)) {
                            $displayPrice = min($maintenancePrices);
                            $displayPricePrefix = 'From ';
                        }
                    }
                @endphp
                <article class="customer-product-card">
                    <a class="customer-product__media"
                       href="{{ route('customer.products.show', $product) }}">
                        @if($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
                        @else
                            <div class="customer-product__placeholder">No image</div>
                        @endif
                    </a>
                    <div class="customer-product__body">
                        <div class="customer-product__meta">
                            <span class="customer-badge">
                                {{ $product->category?->name ?? 'Uncategorized' }}
                            </span>
                            <span class="customer-stock {{ $availableStock > 0 ? 'is-in' : 'is-out' }}">
                                {{ $availableStock > 0 ? $availableStock . ' in stock' : 'Out of Stock' }}
                            </span>
                        </div>
                        <h3 class="customer-product__title">{{ $product->name }}</h3>
                        <div class="customer-product__price">
                            {{ $displayPrice !== null ? $displayPricePrefix . 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
                        </div>
                        <a class="btn btn-outline" href="{{ route('customer.products.show', $product) }}">
                            View
                        </a>
                    </div>
                </article>
            @empty
                <div class="customer-empty">No products available yet.</div>
            @endforelse
        </div>
    </section>

    <section class="sf-banner">
        <div class="sf-banner__content">
            <div class="sf-kicker">Limited drop</div>
            <div class="sf-banner__title">New arrivals this week</div>
            <div class="sf-banner__subtitle">Discover what’s fresh — limited stock, premium quality.</div>
        </div>
        <div class="sf-banner__actions">
            <a class="btn btn-primary" href="{{ route('customer.products.index', ['sort' => 'newest']) }}">Shop now</a>
            <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Explore all</a>
        </div>
    </section>

    <section class="sf-section">
        <div class="sf-section__head">
            <h2>Why shop here</h2>
        </div>

        <div class="sf-promise">
            <div class="sf-promise__card">
                <div class="sf-promise__title">Quality checked</div>
                <div class="sf-promise__text">Carefully curated products you can trust.</div>
            </div>
            <div class="sf-promise__card">
                <div class="sf-promise__title">Packed for freshness</div>
                <div class="sf-promise__text">Packaging designed to protect quality in transit.</div>
            </div>
            <div class="sf-promise__card">
                <div class="sf-promise__title">Fast support</div>
                <div class="sf-promise__text">A real team ready to help when you need it.</div>
            </div>
        </div>
    </section>
@endsection
