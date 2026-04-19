@extends('layouts.customer')

@section('title', 'Payment Processing')
@section('page_title', 'Payment Processing')
@section('page_subtitle', 'Your order has been placed')

@section('content')
    <div class="customer-card" style="text-align:center;">
        <h2 style="margin-bottom:10px;">Thank you for your order!</h2>
        <p>Your order <strong>{{ $order->order_number }}</strong> has been placed successfully.</p>
        <p>
            Payment method:
            <strong>{{ $paymentMethods[$order->payment_method] ?? ucfirst(str_replace('_', ' ', $order->payment_method)) }}</strong>
        </p>
        <p>Please proceed with payment. We will verify and process your order soon.</p>
        <div style="margin-top:16px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            <a class="btn btn-outline" href="{{ route('customer.orders.show', $order) }}">View Order</a>
            <a class="btn btn-primary" href="{{ route('customer.products.index') }}">Continue Shopping</a>
        </div>
    </div>
@endsection
