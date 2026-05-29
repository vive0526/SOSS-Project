@extends('layouts.customer')

@section('title', 'Order Details')
@section('page_title', 'Order Details')
@section('page_subtitle', 'Track your order progress')

@section('content')
    @php
        $itemsTotal = $order->items->sum('total_price');
        $statusClass = 'customer-status--' . $order->status;
        $shipmentClass = 'customer-status--' . $order->shipment_status;
    @endphp

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

    <div style="margin-bottom:16px;">
        <a class="btn btn-outline" href="{{ route('customer.orders.index') }}">Back to Orders</a>
    </div>

    <div class="customer-card" style="display:grid; gap:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <div style="font-size:12px; color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em;">Order</div>
                <div style="font-size:20px; font-weight:800; color:#4c2f1c;">
                    {{ $order->order_number }}
                </div>
                <div style="color:#7b6a5b; font-size:12px;">
                    Placed {{ $order->created_at?->format('Y-m-d H:i') }}
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <span class="customer-status {{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                <span class="customer-status {{ $shipmentClass }}">{{ ucfirst($order->shipment_status) }}</span>
            </div>
        </div>
        <div style="display:flex; gap:18px; flex-wrap:wrap; color:#5e4a3b;">
            <div><strong>Tracking:</strong> {{ $order->tracking_number ?? '-' }}</div>
            <div><strong>Payment Method:</strong> {{ $order->payment_method ?? '-' }}</div>
            <div><strong>Total:</strong> RM {{ $order->total_amount > 0 ? number_format((float) $order->total_amount, 2) : number_format((float) $itemsTotal, 2) }}</div>
        </div>
    </div>

    @if(in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true) && !$order->payment_verified_at && $order->status !== 'cancelled')
        <div class="customer-card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <strong>Payment required:</strong> Complete your Stripe payment to verify this order.
            </div>
            <a class="btn btn-primary" href="{{ route('customer.checkout.stripe.start', $order) }}">
                Pay Now (Stripe)
            </a>
        </div>
    @endif

    <div class="customer-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 8px;">Shipping Address</h3>
            <p><strong>Name:</strong> {{ $order->shipping_name ?? '-' }}</p>
            <p><strong>Phone:</strong> {{ $order->shipping_phone ?? '-' }}</p>
            <p><strong>Address:</strong> {{ $order->shipping_address ?? '-' }}</p>
            <p>
                <strong>City/State:</strong>
                {{ $order->shipping_city ?? '-' }} {{ $order->shipping_state ?? '' }}
            </p>
            <p><strong>Postcode:</strong> {{ $order->shipping_postcode ?? '-' }}</p>
            <p><strong>Country:</strong> {{ $order->shipping_country ?? '-' }}</p>
            @if(filled($order->delivery_notes))
                <p><strong>Delivery Notes:</strong> {{ $order->delivery_notes }}</p>
            @endif
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Order Summary</h3>
            <p><strong>Items:</strong> {{ $order->items->sum('quantity') }}</p>
            <p><strong>Payment:</strong> {{ $order->payment_verified_at ? 'Verified' : 'Unverified' }}</p>
            <p><strong>Shipment:</strong> {{ ucfirst($order->shipment_status) }}</p>
            <p><strong>Subtotal:</strong> RM {{ number_format((float) ($order->subtotal_amount ?? $itemsTotal), 2) }}</p>
            <p><strong>Shipping Fee:</strong> RM {{ number_format((float) ($order->shipping_fee ?? 0), 2) }}</p>
            <p><strong>Discount:</strong> RM {{ number_format((float) ($order->discount_amount ?? 0), 2) }}</p>
            <p><strong>Tax (6%):</strong> RM {{ number_format((float) ($order->tax_amount ?? 0), 2) }}</p>
            <p><strong>Grand Total:</strong> RM {{ number_format((float) ($order->total_amount ?? 0), 2) }}</p>
            @if($order->cancelled_at)
                <p><strong>Cancelled:</strong> {{ $order->cancelled_at->format('Y-m-d H:i') }}</p>
                <p><strong>Reason:</strong> {{ $order->cancelled_reason }}</p>
            @endif
        </div>
    </div>

    <div class="customer-card">
        <h3 style="margin-bottom: 12px;">Order Items</h3>
        <table class="customer-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Product</th>
                    <th>Maintenance Year</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->maintenance_year ?? '-' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>RM {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>RM {{ number_format((float) ($item->line_total ?? $item->total_price), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No items recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="customer-card">
        <h3 style="margin-bottom: 12px;">Status History</h3>
        @if($order->statusHistories->isEmpty())
            <p>No status updates yet.</p>
        @else
            <table class="customer-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->statusHistories as $index => $history)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ ucfirst($history->status) }}</td>
                            <td>{{ $history->note ?? '-' }}</td>
                            <td>{{ $history->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="customer-card" id="cancel">
        <h3 style="margin-bottom: 12px;">Cancel Order</h3>
        @if($order->status === 'pending' && $order->shipment_status === 'pending')
            <form method="POST" action="{{ route('customer.orders.cancel', $order) }}">
                @csrf
                @method('PATCH')
                <div class="customer-field">
                    <label for="cancel_reason">Reason</label>
                    <input type="text" name="cancel_reason" required>
                </div>
                <div style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Cancel this order?')">
                        Cancel Order
                    </button>
                </div>
            </form>
        @else
            <p>This order can no longer be cancelled.</p>
        @endif
    </div>
@endsection
