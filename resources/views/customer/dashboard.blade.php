@extends('layouts.customer')

@section('title', 'Customer Dashboard')
@section('page_title', 'Welcome back, ' . (auth()->user()->name ?? 'Customer'))
@section('page_subtitle', 'Discover something new today')

@section('content')
    <section class="customer-hero">
        <div class="customer-hero__content">
            <h1>Find your next favorite item</h1>
            <p>Explore {{ $totalProducts }} products across {{ $categoryCount }} categories.</p>
            <form class="customer-search" method="GET" action="{{ route('customer.products.index') }}">
                <input type="text" name="search" placeholder="Search products">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>
        <div class="customer-hero__panel">
            <div class="customer-hero__stat">
                <span class="customer-hero__label">In Stock</span>
                <span class="customer-hero__value">{{ $inStockCount }}</span>
            </div>
            <div class="customer-hero__stat">
                <span class="customer-hero__label">Categories</span>
                <span class="customer-hero__value">{{ $categoryCount }}</span>
            </div>
            <div class="customer-hero__stat">
                <span class="customer-hero__label">New Arrivals</span>
                <span class="customer-hero__value">{{ $featuredProducts->count() }}</span>
            </div>
        </div>
    </section>

    <section class="customer-kpis">
        <div class="customer-card">
            <div class="customer-kpi__label">Total Products</div>
            <div class="customer-kpi__value">{{ $totalProducts }}</div>
            <div class="customer-kpi__note">Catalog size</div>
        </div>
        <div class="customer-card">
            <div class="customer-kpi__label">Available Now</div>
            <div class="customer-kpi__value">{{ $inStockCount }}</div>
            <div class="customer-kpi__note">Ready to ship</div>
        </div>
        <div class="customer-card">
            <div class="customer-kpi__label">Categories</div>
            <div class="customer-kpi__value">{{ $categoryCount }}</div>
            <div class="customer-kpi__note">Browse by type</div>
        </div>
    </section>

    <section class="customer-section">
        <div class="customer-section__head">
            <h2>Featured Products</h2>
            <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Browse All</a>
        </div>

        <div class="customer-products">
            @forelse($featuredProducts as $product)
                @php
                    $displayPrice = $product->price;
                    if ((string) $product->category_id === '3' && !empty($product->maintenance_prices)) {
                        $maintenancePrices = $product->maintenance_prices ?? [];
                        if (!empty($maintenancePrices)) {
                            ksort($maintenancePrices);
                            $displayPrice = $maintenancePrices[array_key_first($maintenancePrices)];
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
                            <span class="customer-stock {{ $product->stock_quantity > 0 ? 'is-in' : 'is-out' }}">
                                {{ $product->stock_quantity > 0 ? $product->stock_quantity . ' in stock' : 'Out of Stock' }}
                            </span>
                        </div>
                        <h3 class="customer-product__title">{{ $product->name }}</h3>
                        <div class="customer-product__price">
                            {{ $displayPrice !== null ? 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
                        </div>
                        <a class="btn btn-outline" href="{{ route('customer.products.show', $product) }}">
                            View Details
                        </a>
                    </div>
                </article>
            @empty
                <div class="customer-empty">No products available yet.</div>
            @endforelse
        </div>
    </section>
@endsection
