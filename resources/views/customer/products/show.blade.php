@extends('layouts.storefront')

@section('title', $product->name)
@section('page_title', $product->name)
@section('page_subtitle', $product->category?->name ?? 'Product Details')

@section('content')
    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
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

        if ((string) $product->category_id === '3' && !empty($product->maintenance_prices)) {
            $maintenancePrices = $product->maintenance_prices ?? [];
            if (!empty($maintenancePrices)) {
                ksort($maintenancePrices);
                $defaultMaintenanceYear = (int) array_key_first($maintenancePrices);
                $displayPrice = $maintenancePrices[$defaultMaintenanceYear] ?? $displayPrice;
            }
        }

        $selectedMaintenanceYear = old('maintenance_year', $defaultMaintenanceYear);
        $isCattle = ($product->product_type ?? null) === 'cattle';
    @endphp

    <div class="customer-product-detail">
        <div class="customer-product-detail__media">
            @if($product->image)
                <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
            @else
                <div class="customer-product__placeholder">No image</div>
            @endif
        </div>

        <div class="customer-product-detail__info">
            <div class="customer-product-detail__price" data-price-display>
                {{ $displayPrice !== null ? 'RM ' . number_format((float) $displayPrice, 2) : 'N/A' }}
            </div>
            <div class="customer-product-detail__stock {{ $product->stock_quantity > 0 ? 'is-in' : 'is-out' }}">
                {{ $product->stock_quantity > 0 ? $product->stock_quantity . ' in stock' : 'Out of Stock' }}
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
                    <a class="btn btn-primary"
                       href="{{ route('customer.cattle-requests.create', $product) }}"
                       {{ $product->stock_quantity > 0 ? '' : 'aria-disabled=true' }}>
                        Request Purchase
                    </a>
                    <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Back to Products</a>
                </div>
            @else
                <form method="POST" action="{{ route('customer.cart.add') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->product_id }}">

                    @if((string) $product->category_id === '3' && !empty($maintenancePrices))
                        <div class="customer-product-detail__panel">
                            <h4>Maintenance Options</h4>
                            <div class="customer-maintenance">
                                <div class="customer-maintenance__row">
                                    <label for="maintenance_year">Select year</label>
                                    <select id="maintenance_year" name="maintenance_year" data-maintenance-select required>
                                        @for($year = 1; $year <= 5; $year++)
                                            @php
                                                $yearPrice = $maintenancePrices[$year] ?? null;
                                            @endphp
                                        <option value="{{ $year }}"
                                                data-price="{{ $yearPrice !== null ? number_format((float) $yearPrice, 2, '.', '') : '' }}"
                                                {{ $year === (int) $selectedMaintenanceYear ? 'selected' : '' }}
                                                {{ $yearPrice === null ? 'disabled' : '' }}>
                                            Year {{ $year }}
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
                               max="{{ $product->stock_quantity }}"
                               value="{{ old('quantity', 1) }}"
                               style="width:90px;"
                               {{ $product->stock_quantity > 0 ? '' : 'disabled' }}>
                        <button type="submit" class="btn btn-primary"
                                {{ $product->stock_quantity > 0 ? '' : 'disabled' }}>
                            Add to Cart
                        </button>
                        <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Back to Products</a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @if(!$isCattle && (string) $product->category_id === '3' && !empty($maintenancePrices))
        <script>
            (function () {
                const select = document.querySelector('[data-maintenance-select]');
                const priceEl = document.querySelector('[data-maintenance-price]');
                const displayEl = document.querySelector('[data-price-display]');
                if (!select || !priceEl || !displayEl) return;

                const updatePrice = () => {
                    const option = select.selectedOptions[0];
                    const price = option ? option.dataset.price : '';
                    const formatted = price ? ('RM ' + Number(price).toFixed(2)) : 'N/A';
                    priceEl.textContent = formatted;
                    displayEl.textContent = formatted;
                };

                select.addEventListener('change', updatePrice);
                updatePrice();
            })();
        </script>
    @endif
@endsection
