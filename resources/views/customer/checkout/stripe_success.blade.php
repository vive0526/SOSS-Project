@extends('layouts.storefront')

@section('title', 'Payment Successful')
@section('page_title', 'Payment Successful')
@section('page_subtitle', 'Redirecting you to your order details')

@section('content')
    <div class="customer-card" style="text-align:center;">
        <h2 style="margin-bottom:10px;">Payment successful</h2>
        <p>
            Your payment for order <strong>{{ $order->order_number }}</strong> was received.
        </p>
        <p style="color:#7b6a5b; font-size:12px; margin-top:10px;">
            Redirecting in {{ (int) ceil(((int) $delayMs) / 1000) }}s…
        </p>
        <div style="margin-top:16px;">
            <a class="btn btn-primary" href="{{ $redirectUrl }}">Go to Order Details</a>
        </div>
    </div>

    <script>
        window.setTimeout(function () {
            window.location.href = @json($redirectUrl);
        }, @json((int) $delayMs));
    </script>
@endsection

