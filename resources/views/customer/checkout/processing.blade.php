@extends('layouts.storefront')

@section('title', 'Payment Processing')
@section('page_title', 'Payment Processing')
@section('page_subtitle', 'Your order has been placed')

@section('content')
    @if($errors->any())
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <div class="customer-card" style="text-align:center;">
        <h2 style="margin-bottom:10px;">Thank you for your order!</h2>
        <p>Your order <strong>{{ $order->order_number }}</strong> has been placed successfully.</p>
        <p>
            Payment method:
            <strong>{{ $paymentMethods[$order->payment_method] ?? ucfirst(str_replace('_', ' ', $order->payment_method)) }}</strong>
        </p>

        @if(in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true) && !$order->payment_verified_at)
            <div class="customer-alert" id="stripeExpiryAlert" style="margin-top:14px; text-align:left;">
                <div class="customer-alert__title">Complete payment within 5 minutes</div>
                <div>
                    Your items are reserved temporarily. Please complete Stripe payment before the timer ends to avoid automatic cancellation.
                </div>
                @if($order->reservation_expires_at)
                    <div style="margin-top:8px;">
                        Time left: <strong id="stripeTimeLeft">--:--</strong>
                    </div>
                    <script>
                        (function () {
                            const el = document.getElementById('stripeTimeLeft');
                            if (!el) return;

                            const expiresAt = new Date("{{ $order->reservation_expires_at->toIso8601String() }}");
                            const pad = (n) => String(n).padStart(2, '0');

                            const tick = () => {
                                const ms = expiresAt.getTime() - Date.now();
                                const total = Math.max(0, Math.floor(ms / 1000));
                                const m = Math.floor(total / 60);
                                const s = total % 60;
                                el.textContent = pad(m) + ':' + pad(s);
                            };

                            tick();
                            setInterval(tick, 1000);
                        })();
                    </script>
                @endif
            </div>
        @endif

        <p>Please proceed with payment. We will verify and process your order soon.</p>
        @if(in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true) && !$order->payment_verified_at)
            <div style="margin-top:16px;">
                <a class="btn btn-primary" href="{{ route('customer.checkout.stripe.start', $order) }}">
                    Pay Now (Stripe)
                </a>
            </div>
        @endif
        <div style="margin-top:16px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            <a class="btn btn-outline" href="{{ route('customer.orders.show', $order) }}">View Order</a>
            <a class="btn btn-primary" href="{{ route('customer.products.index') }}">Continue Shopping</a>
        </div>
    </div>
@endsection
