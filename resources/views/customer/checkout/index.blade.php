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
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('customer.checkout.place') }}" class="customer-form">
        @csrf
        <div class="customer-card">
            <h3 style="margin-bottom: 12px;">Shipping & Contact</h3>
            <div class="customer-form__row">
                <div class="customer-field">
                    <label for="shipping_name">Full Name</label>
                    <input type="text" name="shipping_name" value="{{ old('shipping_name', $customer->name) }}" required>
                </div>
                <div class="customer-field">
                    <label for="shipping_phone">Phone</label>
                    <input type="text" name="shipping_phone" value="{{ old('shipping_phone', $customer->phone) }}" required>
                </div>
            </div>
            <div class="customer-field">
                <label for="shipping_address">Address</label>
                <textarea name="shipping_address" required>{{ old('shipping_address', $customer->shipping_address) }}</textarea>
            </div>
                <div class="customer-form__row">
                <div class="customer-field">
                    <label for="shipping_city">City</label>
                    <input type="text" name="shipping_city" value="{{ old('shipping_city', $customer->shipping_city) }}" required>
                </div>
                <div class="customer-field">
                    <label for="shipping_state">State</label>
                    <input type="text" name="shipping_state" value="{{ old('shipping_state', $customer->shipping_state) }}" required>
                </div>
            </div>
            <div class="customer-form__row">
                <div class="customer-field">
                    <label for="shipping_postcode">Postcode</label>
                    <input type="text" name="shipping_postcode" value="{{ old('shipping_postcode', $customer->shipping_postcode) }}" required>
                </div>
                <div class="customer-field">
                    <label for="shipping_country">Country</label>
                    <input type="text" name="shipping_country" value="{{ old('shipping_country', $customer->shipping_country ?? 'Malaysia') }}" required>
                </div>
            </div>
            <div class="customer-field">
                <label for="payment_method">Payment Method</label>
                <select name="payment_method" required>
                    @foreach($paymentMethods as $value => $label)
                        <option value="{{ $value }}" {{ old('payment_method') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
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
                <div><strong>Total:</strong> RM {{ number_format((float) $total, 2) }}</div>
            </div>
        </div>

        <div class="customer-card" style="display:flex; justify-content:flex-end; gap:12px;">
            <a class="btn btn-outline" href="{{ route('customer.cart.index') }}">Back to Cart</a>
            <button type="submit" class="btn btn-primary">Confirm Checkout</button>
        </div>
    </form>
@endsection
