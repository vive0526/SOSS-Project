@extends('layouts.storefront')

@section('title', $product->name)
@section('hide_masthead', '1')
@section('page_title', $product->name)
@section('page_subtitle', $product->category?->name ?? 'Product Details')

@section('content')
    @php
        $availableStock = max(0, (int) $product->stock_quantity - (int) ($product->reserved_quantity ?? 0));
    @endphp

    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('warning'))
        <div class="customer-card">
            <p>{{ session('warning') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    @php
        $displayPrice = $product->price;
        $maintenancePrices = [];
        $defaultMaintenanceYear = null;

        if ($product->requires_maintenance && !empty($product->maintenance_prices)) {
            $maintenancePrices = $product->maintenance_prices ?? [];
            if (!empty($maintenancePrices)) {
                $lowestPrice = min($maintenancePrices);
                $lowestYears = array_keys(array_filter($maintenancePrices, fn ($value) => (float) $value === (float) $lowestPrice));
                sort($lowestYears);
                $defaultMaintenanceYear = (int) ($lowestYears[0] ?? 1);
                $displayPrice = $lowestPrice;
            }
        }

        $selectedMaintenanceYear = old('maintenance_year', $defaultMaintenanceYear);
        $isCattle = ($product->product_type ?? null) === 'cattle';
        $imagePaths = $product->imagePaths();
        $primaryPath = $product->primaryImagePath();
        $primaryUrl = $primaryPath ? asset('storage/' . $primaryPath) : null;
        $ratingLabel = $reviewCount > 0 ? number_format($averageRating, 1) : 'New';
        $productCode = $product->product_id ?? $product->getKey();
    @endphp

    <div class="customer-product-detail customer-product-detail--marketplace">
        <section class="customer-product-detail__media" aria-label="Product images">
            <div class="customer-product-detail__image-stage">
                @if($primaryUrl)
                    <img id="productMainImage" src="{{ $primaryUrl }}" alt="{{ $product->name }}">
                @else
                    <div class="customer-product__placeholder">No image</div>
                @endif
            </div>

            @if($imagePaths->count() > 1)
                <div class="customer-product-thumbs" aria-label="Product image thumbnails">
                    @foreach($imagePaths as $path)
                        @php $url = asset('storage/' . $path); @endphp
                        <button type="button"
                                class="customer-product-thumb {{ $path === $primaryPath ? 'is-active' : '' }}"
                                data-product-image="{{ $url }}"
                                aria-label="View product image">
                            <img src="{{ $url }}" alt="{{ $product->name }}">
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="customer-product-assurance" aria-label="Store assurances">
                <span>Cold-chain delivery</span>
                <span>Secure FPX</span>
                <span>Fast support</span>
            </div>
        </section>

        <aside class="customer-product-detail__info">
            <div class="customer-product-detail__summary">
                <div class="customer-product-detail__label-row">
                    <span class="customer-product-choice">Choice</span>
                    <span class="customer-product-detail__category">{{ $product->category?->name ?? 'Product' }}</span>
                </div>

                <h1 class="customer-product-detail__title">{{ $product->name }}</h1>

                <div class="customer-product-stats" aria-label="Product summary">
                    <span class="customer-product-stats__rating">{{ $ratingLabel }}</span>
                    <span>{{ $reviewCount }} {{ \Illuminate\Support\Str::plural('rating', $reviewCount) }}</span>
                    <span>{{ $availableStock > 0 ? $availableStock . ' available' : 'Currently unavailable' }}</span>
                    <span>Code {{ $productCode }}</span>
                </div>

                <div class="customer-product-pricebox">
                    <div class="customer-product-detail__price" data-price-display>
                        {{ $displayPrice !== null ? 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
                    </div>
                    <span class="customer-product-pricebox__note">
                        Price includes current product selection
                    </span>
                </div>

                <div class="customer-product-detail__meta">
                    <span class="customer-product-detail__stock {{ $availableStock > 0 ? 'is-in' : 'is-out' }}" data-available-stock>
                        {{ $availableStock > 0 ? $availableStock . ' in stock' : 'Out of Stock' }}
                    </span>
                    <span class="customer-product-detail__service">Verified stock before checkout</span>
                </div>

                <div class="customer-product-info-list">
                    <div class="customer-product-info-row">
                        <div class="customer-product-info-row__label">Shop Benefits</div>
                        <div class="customer-product-info-row__value">
                            <span>Fresh handling</span>
                            <span>Secure payment</span>
                            <span>Order support</span>
                        </div>
                    </div>
                    <div class="customer-product-info-row">
                        <div class="customer-product-info-row__label">Delivery</div>
                        <div class="customer-product-info-row__value">
                            Chilled and frozen delivery support for eligible orders.
                        </div>
                    </div>
                    <div class="customer-product-info-row">
                        <div class="customer-product-info-row__label">Guarantee</div>
                        <div class="customer-product-info-row__value">
                            Product availability is checked before checkout and fulfillment.
                        </div>
                    </div>
                    <div class="customer-product-info-row">
                        <div class="customer-product-info-row__label">Payment</div>
                        <div class="customer-product-info-row__value">
                            Secure checkout with online payment support.
                        </div>
                    </div>
                </div>

                @if(filled($product->description))
                    <div class="customer-product-detail__description">
                        <h2>Product Description</h2>
                        <p class="customer-product-detail__desc">{{ $product->description }}</p>
                    </div>
                @endif
            </div>

            @if($isCattle)
                <div class="customer-product-detail__panel">
                    <h4>Cattle Purchase</h4>
                    <p>
                        This is a request/booking product. Submit a purchase request and our staff will contact you.
                    </p>
                </div>

                <div class="customer-product-detail__actions">
                    @if($availableStock > 0)
                        <a class="btn btn-primary" href="{{ route('customer.cattle-requests.create', $product) }}">
                            Request Purchase
                        </a>
                    @else
                        <button type="button"
                                class="btn btn-primary"
                                disabled
                                aria-disabled="true"
                                title="Out of stock, cannot request">
                            Request Purchase
                        </button>
                        <div class="customer-product-detail__hint">
                            Out of stock, cannot request.
                        </div>
                    @endif
                    <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Back to Products</a>
                </div>
            @else
                <form method="POST" action="{{ route('customer.cart.add') }}" class="customer-product-detail__form">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->product_id }}">

                    @if($product->requires_maintenance && !empty($maintenancePrices))
                        <div class="customer-product-detail__panel">
                            <h4>Maintenance Options</h4>
                            <div class="customer-maintenance">
                                <div class="customer-maintenance__row">
                                    <label for="maintenance_year">Select year</label>
                                    <select id="maintenance_year" name="maintenance_year" data-maintenance-select required>
                                        @for($year = 1; $year <= 5; $year++)
                                            @php
                                                $yearPrice = $maintenancePrices[$year] ?? null;
                                                $yearAvailable = $yearPrice !== null ? $product->availableMaintenanceStock($year) : 0;
                                            @endphp
                                            <option value="{{ $year }}"
                                                    data-price="{{ $yearPrice !== null ? number_format((float) $yearPrice, 2, '.', '') : '' }}"
                                                    data-available="{{ (int) $yearAvailable }}"
                                                    {{ $year === (int) $selectedMaintenanceYear ? 'selected' : '' }}
                                                    {{ ($yearPrice === null || $yearAvailable <= 0) ? 'disabled' : '' }}>
                                                Year {{ $year }}{{ $yearPrice !== null ? ' (' . (int) $yearAvailable . ' left)' : '' }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="customer-maintenance__price">
                                    <span>Selected price</span>
                                    <strong data-maintenance-price>
                                        {{ $defaultMaintenanceYear ? 'RM ' . number_format((float) $maintenancePrices[$defaultMaintenanceYear], 2) : 'N/A' }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="customer-product-detail__actions">
                        <label class="customer-product-detail__qty">
                            <span>Qty</span>
                            <input type="number"
                                   name="quantity"
                                   min="1"
                                   max="{{ $availableStock }}"
                                   value="{{ old('quantity', 1) }}"
                                   data-qty-input
                                   {{ $availableStock > 0 ? '' : 'disabled' }}>
                        </label>
                        <button type="submit" class="btn btn-primary" data-add-to-cart-btn
                                {{ $availableStock > 0 ? '' : 'disabled' }}>
                            Add to Cart
                        </button>
                        <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Back to Products</a>
                    </div>
                </form>
            @endif
        </aside>
    </div>

    <section class="customer-card customer-reviews">
        <div class="customer-reviews__head">
            <div>
                <h2>Customer Reviews</h2>
                <div class="customer-reviews__summary">
                    @if($reviewCount > 0)
                        <strong>{{ number_format($averageRating, 1) }}/5</strong>
                        based on {{ $reviewCount }} {{ \Illuminate\Support\Str::plural('review', $reviewCount) }}
                    @else
                        No reviews yet.
                    @endif
                </div>
            </div>
        </div>

        <div class="customer-reviews__list">
            @forelse($reviews as $review)
                <article class="customer-review">
                    <div class="customer-review__head">
                        <div>
                            <strong>{{ $review->customer?->name ?? 'Customer' }}</strong>
                            <span class="customer-review__rating">Rating {{ (int) $review->rating }}/5</span>
                        </div>
                        <div class="customer-review__meta">
                            Verified purchase - {{ $review->created_at?->format('Y-m-d') }}
                        </div>
                    </div>
                    @if(filled($review->comment))
                        <div class="customer-review__comment">
                            {{ $review->comment }}
                        </div>
                    @endif
                </article>
            @empty
                <div class="customer-empty">Be the first verified customer to review this product.</div>
            @endforelse
        </div>
    </section>

    <script>
        (function () {
            const mainImage = document.getElementById('productMainImage');
            const thumbs = document.querySelectorAll('[data-product-image]');
            if (!mainImage || thumbs.length === 0) return;

            thumbs.forEach((thumb) => {
                thumb.addEventListener('click', () => {
                    const nextSrc = thumb.getAttribute('data-product-image');
                    if (!nextSrc) return;

                    mainImage.src = nextSrc;
                    thumbs.forEach((item) => item.classList.remove('is-active'));
                    thumb.classList.add('is-active');
                });
            });
        })();
    </script>

    @if(!$isCattle)
        <script>
            (function () {
                const stockEl = document.querySelector('[data-available-stock]');
                const qtyInput = document.querySelector('[data-qty-input]');
                const addBtn = document.querySelector('[data-add-to-cart-btn]');
                const baseUrl = "{{ route('customer.products.stock', $product) }}";
                const yearSelect = document.querySelector('[data-maintenance-select]');
                if (!stockEl || !qtyInput || !addBtn) return;

                const setUi = (available) => {
                    const n = Number(available);
                    const safe = Number.isFinite(n) ? Math.max(0, Math.floor(n)) : 0;

                    stockEl.textContent = safe > 0 ? (safe + ' in stock') : 'Out of Stock';
                    stockEl.classList.toggle('is-in', safe > 0);
                    stockEl.classList.toggle('is-out', safe <= 0);

                    qtyInput.max = String(safe);
                    if (safe <= 0) {
                        qtyInput.disabled = true;
                        addBtn.disabled = true;
                    } else {
                        qtyInput.disabled = false;
                        addBtn.disabled = false;
                        const current = parseInt(qtyInput.value || '1', 10);
                        if (!Number.isFinite(current) || current < 1) qtyInput.value = '1';
                        if (current > safe) qtyInput.value = String(safe);
                    }
                };

                const poll = async () => {
                    try {
                        let url = baseUrl;
                        if (yearSelect && yearSelect.value) {
                            const u = new URL(baseUrl, window.location.origin);
                            u.searchParams.set('maintenance_year', yearSelect.value);
                            url = u.toString();
                        }

                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (!data || data.ok !== true) return;
                        setUi(data.available_stock);
                    } catch (e) {
                        // ignore
                    }
                };

                poll();
                setInterval(poll, 7000);
            })();
        </script>
    @endif

    @if(!$isCattle && $product->requires_maintenance && !empty($maintenancePrices))
        <script>
            (function () {
                const select = document.querySelector('[data-maintenance-select]');
                const priceEl = document.querySelector('[data-maintenance-price]');
                const displayEl = document.querySelector('[data-price-display]');
                const stockEl = document.querySelector('[data-available-stock]');
                const qtyInput = document.querySelector('[data-qty-input]');
                const addBtn = document.querySelector('[data-add-to-cart-btn]');
                if (!select || !priceEl || !displayEl) return;

                const updatePrice = () => {
                    const option = select.selectedOptions[0];
                    const price = option ? option.dataset.price : '';
                    const formatted = price ? ('RM ' + Number(price).toFixed(2)) : 'N/A';
                    priceEl.textContent = formatted;
                    displayEl.textContent = formatted;

                    const yearAvailable = option ? parseInt(option.dataset.available || '0', 10) : 0;
                    if (stockEl && qtyInput && addBtn) {
                        const safe = Number.isFinite(yearAvailable) ? Math.max(0, Math.floor(yearAvailable)) : 0;
                        stockEl.textContent = safe > 0 ? (safe + ' in stock') : 'Out of Stock';
                        stockEl.classList.toggle('is-in', safe > 0);
                        stockEl.classList.toggle('is-out', safe <= 0);
                        qtyInput.max = String(safe);
                        if (safe <= 0) {
                            qtyInput.disabled = true;
                            addBtn.disabled = true;
                        } else {
                            qtyInput.disabled = false;
                            addBtn.disabled = false;
                            const current = parseInt(qtyInput.value || '1', 10);
                            if (!Number.isFinite(current) || current < 1) qtyInput.value = '1';
                            if (current > safe) qtyInput.value = String(safe);
                        }
                    }
                };

                select.addEventListener('change', updatePrice);
                updatePrice();
            })();
        </script>
    @endif
@endsection
