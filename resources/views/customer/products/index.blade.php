@extends('layouts.storefront')

@section('title', 'Products')
@section('page_title', 'Browse Products')
@section('page_subtitle', 'Search, filter, and discover what you need')

@section('content')
    <div class="customer-toolbar">
        <form class="customer-filter" method="GET" action="{{ route('customer.products.index') }}">
            <div class="customer-field">
                <label for="search">Search</label>
                <input type="text"
                       id="search"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Product name or description">
            </div>

            <div class="customer-field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}"
                            {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="customer-field">
                <label for="min_price">Min price</label>
                <input type="number"
                       step="0.01"
                       id="min_price"
                       name="min_price"
                       value="{{ request('min_price') }}"
                       placeholder="0.00">
            </div>

            <div class="customer-field">
                <label for="max_price">Max price</label>
                <input type="number"
                       step="0.01"
                       id="max_price"
                       name="max_price"
                       value="{{ request('max_price') }}"
                       placeholder="0.00">
            </div>

            <div class="customer-field">
                <label for="sort">Sort</label>
                <select id="sort" name="sort">
                    <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>
                        Newest
                    </option>
                    <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>
                        Price: Low to High
                    </option>
                    <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>
                        Price: High to Low
                    </option>
                    <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>
                        Name
                    </option>
                </select>
            </div>

            <div class="customer-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="customer-products">
        @forelse($products as $product)
            @php
                $displayPrice = $product->price;
                $availableStock = $product->availableStock();
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
                        <span class="customer-stock {{ $availableStock > 0 ? 'is-in' : 'is-out' }}">
                            {{ $availableStock > 0 ? $availableStock . ' in stock' : 'Out of Stock' }}
                        </span>
                    </div>
                    <h3 class="customer-product__title">{{ $product->name }}</h3>
                    <p class="customer-product__desc">
                        {{ \Illuminate\Support\Str::limit($product->description, 90) }}
                    </p>
                    <div class="customer-product__price">
                        {{ $displayPrice !== null ? 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
                    </div>
                    <a class="btn btn-outline" href="{{ route('customer.products.show', $product) }}">
                        View Details
                    </a>
                </div>
            </article>
        @empty
            <div class="customer-empty">No products match your filters.</div>
        @endforelse
    </div>
@endsection
