@extends('layouts.customer')

@section('title', 'Order Details')
@section('page_title', 'Order Details')
@section('page_subtitle', 'Track your order progress')

@section('content')
    @php
        $itemsTotal = $order->items->sum('total_price');
        $statusClass = 'customer-status--' . $order->status;
        $shipmentClass = 'customer-status--' . $order->shipment_status;
        $paymentClass = 'customer-status--' . ($order->payment_status ?? 'unpaid');
        $paymentLabel = match ($order->payment_status) {
            'refunded' => 'Full Refunded',
            'partial_refund' => 'Partial Refund',
            'refund_pending' => 'Refund Pending',
            default => ucwords(str_replace('_', ' ', $order->payment_status ?? 'unpaid')),
        };
        $activeReturnRequest = $order->returnRequests->first(fn ($returnRequest) => in_array($returnRequest->status, ['pending', 'approved', 'return_received'], true));
        $remainingRefundableCents = $order->remainingRefundableCents();
        $canRequestReturn = $order->status === 'delivered'
            && $order->shipment_status === 'delivered'
            && in_array($order->payment_status, ['paid', 'partial_refund'], true)
            && $remainingRefundableCents > 0
            && !$activeReturnRequest;
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
                @if(in_array($order->payment_status, ['refund_pending', 'partial_refund', 'refunded'], true))
                    <span class="customer-status {{ $paymentClass }}">{{ $paymentLabel }}</span>
                @endif
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
            <p><strong>Payment:</strong> {{ $paymentLabel }}</p>
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

    @if($order->status === 'delivered' && $order->shipment_status === 'delivered')
        <div class="customer-card">
            <h3 style="margin-bottom: 12px;">Rate Your Products</h3>
            <div style="display:grid; gap:14px;">
                @foreach($order->items as $item)
                    <div style="border:1px solid rgba(17,24,39,.08); border-radius:16px; padding:14px; background:rgba(255,255,255,.62);">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <div>
                                <strong>{{ $item->product_name }}</strong>
                                <div style="color:#7b6a5b; font-size:12px;">
                                    Verified purchase · Qty {{ $item->quantity }}
                                </div>
                            </div>
                            @if($item->review)
                                <span class="customer-status customer-status--delivered">
                                    Reviewed {{ str_repeat('★', (int) $item->review->rating) }}
                                </span>
                            @endif
                        </div>

                        @if($item->review)
                            @if(filled($item->review->comment))
                                <div style="margin-top:10px; color:#5e4a3b; line-height:1.6;">
                                    {{ $item->review->comment }}
                                </div>
                            @endif
                        @else
                            <form method="POST" action="{{ route('customer.order-items.reviews.store', $item) }}" style="margin-top:12px; display:grid; gap:10px;">
                                @csrf
                                <div class="customer-field">
                                    <label for="rating_{{ $item->id }}">Rating</label>
                                    <select id="rating_{{ $item->id }}" name="rating" required>
                                        <option value="">Select rating</option>
                                        <option value="5">★★★★★ Excellent</option>
                                        <option value="4">★★★★ Good</option>
                                        <option value="3">★★★ Average</option>
                                        <option value="2">★★ Poor</option>
                                        <option value="1">★ Very Poor</option>
                                    </select>
                                </div>
                                <div class="customer-field">
                                    <label for="comment_{{ $item->id }}">Comment</label>
                                    <textarea id="comment_{{ $item->id }}" name="comment" rows="3" placeholder="Optional: share your experience with this product."></textarea>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary">Submit Review</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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

    <div class="customer-card" id="return-refund">
        <h3 style="margin-bottom: 12px;">Return & Refund</h3>
        @if($activeReturnRequest)
            <p>
                This order has an active return/refund request:
                <strong>{{ ucwords(str_replace('_', ' ', $activeReturnRequest->status)) }}</strong>.
            </p>
            <div style="margin-top:12px;">
                <a class="btn btn-outline" href="{{ route('customer.return-requests.show', $activeReturnRequest) }}">
                    View Request
                </a>
            </div>
        @elseif($canRequestReturn)
            <form method="POST" action="{{ route('customer.orders.return-requests.store', $order) }}" enctype="multipart/form-data">
                @csrf
                <div class="customer-field">
                    <label for="reason">Reason</label>
                    <select name="reason" id="reason" required>
                        @foreach(\App\Models\OrderReturnRequest::REASONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="customer-field">
                    <label for="requested_amount">Refund Amount (RM, optional)</label>
                    <input type="number"
                           id="requested_amount"
                           name="requested_amount"
                           min="0.01"
                           max="{{ number_format($remainingRefundableCents / 100, 2, '.', '') }}"
                           step="0.01"
                           placeholder="{{ number_format($remainingRefundableCents / 100, 2, '.', '') }}">
                    <p style="color:#7b6a5b; font-size:12px;">
                        Leave blank to request the full remaining refundable balance.
                    </p>
                </div>
                <div class="customer-field">
                    <label for="customer_note">Details</label>
                    <textarea id="customer_note" name="customer_note" rows="4" required>{{ old('customer_note') }}</textarea>
                </div>
                <div class="customer-field">
                    <label for="evidence_photos">Proof Photos</label>
                    <input type="file"
                           id="evidence_photos"
                           name="evidence_photos[]"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           multiple>
                    <p style="color:#7b6a5b; font-size:12px;">
                        Required for wrong item, damaged item, and quality issue. Upload up to 5 photos, 4MB each.
                    </p>
                </div>
                <div style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Submit this return/refund request?')">
                        Submit Return/Refund Request
                    </button>
                </div>
            </form>
        @else
            <p>
                Return/refund requests are available only after a delivered paid order has refundable balance.
            </p>
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
