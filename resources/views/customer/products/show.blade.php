@extends('layouts.storefront')

@section('title', $product->name)
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
    @endphp

    <div class="customer-product-detail">
        <div class="customer-product-detail__media">
            @php
                $imagePaths = $product->imagePaths();
                $primaryPath = $product->primaryImagePath();
                $primaryUrl = $primaryPath ? asset('storage/' . $primaryPath) : null;
            @endphp

            @if($primaryUrl)
                <img id="productMainImage" src="{{ $primaryUrl }}" alt="{{ $product->name }}">
            @else
                <div class="customer-product__placeholder">No image</div>
            @endif

            @if($imagePaths->count() > 1)
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                    @foreach($imagePaths as $path)
                        @php $url = asset('storage/' . $path); @endphp
                        <button type="button"
                                class="btn btn-outline"
                                style="padding:0; border-radius:10px; overflow:hidden; width:72px; height:72px;"
                                onclick="(function(){var img=document.getElementById('productMainImage'); if(img){ img.src='{{ $url }}'; }})();"
                                aria-label="View image">
                            <img src="{{ $url }}" alt="{{ $product->name }}" style="width:72px; height:72px; object-fit:cover; display:block;">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="customer-product-detail__info">
            <div class="customer-product-detail__price" data-price-display>
                {{ $displayPrice !== null ? 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
            </div>
            <div class="customer-product-detail__stock {{ $availableStock > 0 ? 'is-in' : 'is-out' }}" data-available-stock>
                {{ $availableStock > 0 ? $availableStock . ' in stock' : 'Out of Stock' }}
            </div>
            <p class="customer-product-detail__desc">{{ $product->description }}</p>

            @if($isCattle)
                <div class="customer-product-detail__panel">
                    <h4>Cattle Purchase</h4>
                    <p style="margin:0; color:#bfbfbf;">
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
                        <div style="margin-top:6px; color:#bfbfbf; font-size:0.95rem;">
                            Out of stock, cannot request.
                        </div>
                    @endif
                    <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Back to Products</a>
                </div>
            @else
                    <form method="POST" action="{{ route('customer.cart.add') }}">
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
                        <input type="number"
                               name="quantity"
                               min="1"
                               max="{{ $availableStock }}"
                               value="{{ old('quantity', 1) }}"
                               style="width:90px;"
                               data-qty-input
                               {{ $availableStock > 0 ? '' : 'disabled' }}>
                        <button type="submit" class="btn btn-primary" data-add-to-cart-btn
                                {{ $availableStock > 0 ? '' : 'disabled' }}>
                            Add to Cart
                        </button>
                        <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Back to Products</a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="customer-card" style="margin-top:18px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div>
                <h3 style="margin-bottom: 6px;">Customer Reviews</h3>
                <div style="color:#7b6a5b;">
                    @if($reviewCount > 0)
                        <strong style="color:#4c2f1c;">{{ number_format($averageRating, 1) }}/5</strong>
                        <span style="color:#d59f16;">{{ str_repeat('★', (int) round($averageRating)) }}</span>
                        based on {{ $reviewCount }} {{ \Illuminate\Support\Str::plural('review', $reviewCount) }}
                    @else
                        No reviews yet.
                    @endif
                </div>
            </div>
        </div>

        <div style="display:grid; gap:12px; margin-top:14px;">
            @forelse($reviews as $review)
                <div style="border:1px solid rgba(17,24,39,.08); border-radius:16px; padding:14px; background:rgba(255,255,255,.62);">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $review->customer?->name ?? 'Customer' }}</strong>
                            <span style="margin-left:8px; color:#d59f16;">{{ str_repeat('★', (int) $review->rating) }}</span>
                        </div>
                        <div style="color:#7b6a5b; font-size:12px;">
                            Verified purchase · {{ $review->created_at?->format('Y-m-d') }}
                        </div>
                    </div>
                    @if(filled($review->comment))
                        <div style="margin-top:8px; color:#5e4a3b; line-height:1.6;">
                            {{ $review->comment }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="customer-empty">Be the first verified customer to review this product.</div>
            @endforelse
        </div>
    </div>

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
