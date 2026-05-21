@extends('layouts.storefront')

@section('title', 'Checkout')
@section('page_title', 'Checkout')
@section('page_subtitle', 'Confirm your shipping details')

@section('content')
    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-alert customer-alert--error" role="alert">
            <div class="customer-alert__title">Please check the highlighted fields.</div>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="customer-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <h3 style="margin:0;">Delivery Address</h3>
                <div style="margin-top:6px; color:#7b6a5b; font-size:12px;">
                    Choose where you want your order delivered.
                </div>
            </div>
            <a class="btn btn-outline" href="{{ route('customer.addresses.index') }}">Manage Addresses</a>
        </div>

        <form method="GET" action="{{ route('customer.checkout.index') }}" style="margin-top:12px;">
            <div class="customer-form__row">
                <div class="customer-field" style="flex:1 1 420px;">
                    <label for="address_id">Select Address</label>
                    <select id="address_id" name="address_id" onchange="this.form.submit()">
                        @foreach(($addresses ?? []) as $addr)
                            <option value="{{ $addr->id }}" {{ (string) ($selectedAddress->id ?? '') === (string) $addr->id ? 'selected' : '' }}>
                                {{ $addr->label ?: 'Address' }} — {{ $addr->recipient_name }}, {{ \App\Support\MalaysiaStates::label($addr->state_key) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        @if(isset($selectedAddress))
            <div style="margin-top:10px; line-height:1.6;">
                <div><strong>{{ $selectedAddress->recipient_name }}</strong> — {{ $selectedAddress->phone }}</div>
                <div>{{ $selectedAddress->address_line }}</div>
                <div>
                    {{ $selectedAddress->postcode }} {{ $selectedAddress->city }},
                    {{ \App\Support\MalaysiaStates::label($selectedAddress->state_key) }},
                    {{ $selectedAddress->country }}
                </div>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('customer.checkout.place') }}" class="customer-form">
        @csrf
        <input type="hidden" name="address_id" value="{{ $selectedAddress->id ?? '' }}">

        <div class="customer-card">
            <div class="customer-field">
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <label for="payment_method" style="margin:0;">Payment Method</label>
                    @if(isset($stripeConfigured) && !$stripeConfigured)
                        <span class="customer-badge">Card/FPX (Stripe) temporarily unavailable</span>
                    @endif
                </div>
                <select name="payment_method"
                        id="payment_method"
                        class="{{ $errors->has('payment_method') ? 'is-invalid' : '' }}"
                        aria-invalid="{{ $errors->has('payment_method') ? 'true' : 'false' }}"
                        required>
                    @foreach($paymentMethods as $value => $label)
                        <option value="{{ $value }}" {{ old('payment_method') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('payment_method')
                    <div class="customer-field__error" id="payment_method_error">{{ $message }}</div>
                @enderror

                @if(!isset($stripeConfigured) || $stripeConfigured)
                    <div class="customer-alert" id="stripeReservationNotice" style="margin-top:12px; display:none;">
                        <div>
                            For Card/FPX (Stripe) payments, your items are reserved for 5 minutes after you confirm checkout.
                            Please complete payment within that time to avoid the order being cancelled.
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="customer-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <h3 style="margin:0;">Discount</h3>
                <a class="btn btn-outline" href="{{ route('customer.discounts.index') }}">View Coupons</a>
            </div>

            <div class="customer-form__row" style="margin-top:12px;">
                <div class="customer-field" style="flex:1 1 320px;">
                    <label for="coupon_code">Coupon Code (optional)</label>
                    <select name="coupon_code"
                            id="coupon_code"
                            class="{{ $errors->has('coupon_code') ? 'is-invalid' : '' }}"
                            aria-invalid="{{ $errors->has('coupon_code') ? 'true' : 'false' }}">
                        <option value="">No coupon</option>
                        @foreach($claimedCoupons as $coupon)
                            <option value="{{ $coupon->code }}"
                                {{ old('coupon_code', $selectedCouponCode) === $coupon->code ? 'selected' : '' }}>
                                {{ $coupon->code }} - {{ $coupon->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('coupon_code')
                        <div class="customer-field__error" id="coupon_code_error">{{ $message }}</div>
                    @enderror
                    <div style="margin-top:8px; color:#7b6a5b; font-size:12px;">
                        Tip: Claim a coupon first, then select it here.
                    </div>
                </div>
            </div>
        </div>

        <div class="customer-card">
            <h3 style="margin-bottom: 12px;">Order Summary</h3>
            <table class="customer-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Year</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cart as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td>{{ $item['maintenance_year'] ?? '-' }}</td>
                            <td>{{ $item['quantity'] }}</td>
                            <td>RM {{ number_format((float) $item['price'], 2) }}</td>
                            <td>RM {{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="display:flex; justify-content:flex-end; margin-top:12px; gap:18px; flex-wrap:wrap;">
                <div><strong>Subtotal:</strong> RM {{ number_format((float) $subtotal, 2) }}</div>
                <div><strong>Shipping Fee:</strong> RM {{ number_format((float) $shippingFee, 2) }}</div>
                <div><strong>Discount:</strong> RM {{ number_format((float) ($discount ?? 0), 2) }}</div>
                <div>
                    <strong>Tax ({{ number_format(((float) ($taxRate ?? 0)) * 100, 2) }}%):</strong>
                    RM {{ number_format((float) ($tax ?? 0), 2) }}
                </div>
                <div><strong>Total:</strong> RM {{ number_format((float) $total, 2) }}</div>
            </div>

            @if(!empty($shippingPolicyText) || !empty($taxPolicyText))
                <div style="margin-top:14px; color:#7b6a5b; font-size:12px; line-height:1.55;">
                    @if(!empty($shippingPolicyText))
                        <div><strong>Shipping policy:</strong> {{ $shippingPolicyText }}</div>
                    @endif
                    @if(!empty($taxPolicyText))
                        <div style="margin-top:6px;"><strong>Tax policy:</strong> {{ $taxPolicyText }}</div>
                    @endif
                </div>
            @endif
        </div>

        <div class="customer-card" style="display:flex; justify-content:flex-end; gap:12px;">
            <a class="btn btn-outline" href="{{ route('customer.cart.index') }}">Back to Cart</a>
            <button type="submit" class="btn btn-primary">Confirm Checkout</button>
        </div>
    </form>

    <script>
        (function () {
            const select = document.getElementById('payment_method');
            const notice = document.getElementById('stripeReservationNotice');
            if (!select || !notice) return;

            const update = () => {
                const v = String(select.value || '');
                const isStripe = (v === 'stripe_card' || v === 'stripe_fpx');
                notice.style.display = isStripe ? 'block' : 'none';
            };

            select.addEventListener('change', update);
            update();
        })();

        (function () {
            const couponSelect = document.getElementById('coupon_code');
            if (!couponSelect) return;

            couponSelect.addEventListener('change', function () {
                const code = String(couponSelect.value || '');
                const url = new URL(window.location.href);
                if (code) {
                    url.searchParams.set('coupon_code', code);
                } else {
                    url.searchParams.delete('coupon_code');
                }
                window.location.href = url.toString();
            });
        })();
    </script>
@endsection
