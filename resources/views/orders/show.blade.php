@extends('layouts.admin')

@section('title', 'Order Details')
@section('page_title', 'Order Details')
@section('page_subtitle', 'Review and manage this order')

@section('content')
    @if(session('success'))
        <div class="admin-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="admin-card">
            <p>There were some issues with your request:</p>
            <ul style="margin:8px 0 0; padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $statusClass = match ($order->status) {
            'pending' => 'status-pending',
            'processing' => 'status-processing',
            'shipped' => 'status-shipped',
            'delivered' => 'status-delivered',
            'cancelled' => 'status-cancelled',
            default => 'status-low',
        };
        $shipmentClass = match ($order->shipment_status) {
            'pending' => 'status-pending',
            'shipped' => 'status-shipped',
            'delivered' => 'status-delivered',
            default => 'status-low',
        };
        $paymentClass = match ($order->payment_status) {
            'paid' => 'status-paid',
            'pending' => 'status-pending',
            'refund_pending' => 'status-pending',
            'partial_refund' => 'status-paid',
            'refunded' => 'status-paid',
            default => 'status-unpaid',
        };
        $totalItems = $order->items->sum('quantity');
        $itemsTotal = $order->items->sum('total_price');
        $displaySubtotal = (float) ($order->subtotal_amount ?? 0);
        if ($displaySubtotal <= 0) {
            $displaySubtotal = (float) $itemsTotal;
        }
        $isAdmin = auth()->user()->role === 'admin';
        $isStaff = auth()->user()->role === 'staff';
        // Shipping starts once shipment_status reaches "shipped" (and includes "delivered").
        $shipmentStarted = in_array($order->shipment_status, ['shipped', 'delivered'], true);
        $lockShippingDetails = $isStaff && $shipmentStarted;
        $allowedShipmentStatuses = match ($order->shipment_status) {
            'pending' => ['pending', 'shipped'],
            'shipped' => ['shipped', 'delivered'],
            'delivered' => ['delivered'],
            default => $shipmentStatuses,
        };
        $canCancelOrder = in_array($order->status, ['pending', 'processing'], true) && $order->shipment_status === 'pending';
    @endphp

    <div class="admin-card" style="display:flex; gap:20px; flex-wrap:wrap; align-items:center;">
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Order Number</div>
            <div style="font-size:20px; font-weight:700;">{{ $order->order_number }}</div>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Status</div>
            <span class="{{ $statusClass }}">{{ ucfirst($order->status) }}</span>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Payment</div>
            <span class="{{ $paymentClass }}">{{ ucwords(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}</span>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Shipment</div>
            <span class="{{ $shipmentClass }}">{{ ucfirst($order->shipment_status) }}</span>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Items</div>
            <div style="font-size:20px; font-weight:700;">{{ $totalItems }}</div>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Subtotal</div>
            <div style="font-size:20px; font-weight:700;">
                RM {{ number_format((float) $displaySubtotal, 2) }}
            </div>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Shipping Fee</div>
            <div style="font-size:20px; font-weight:700;">
                RM {{ number_format((float) ($order->shipping_fee ?? 0), 2) }}
            </div>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Discount</div>
            <div style="font-size:20px; font-weight:700;">
                RM {{ number_format((float) ($order->discount_amount ?? 0), 2) }}
            </div>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Tax</div>
            <div style="font-size:20px; font-weight:700;">
                RM {{ number_format((float) ($order->tax_amount ?? 0), 2) }}
            </div>
        </div>
        <div>
            <div style="color:#bfbfbf; font-size:12px;">Grand Total</div>
            <div style="font-size:20px; font-weight:700;">
                RM {{ number_format((float) ($order->total_amount ?? 0), 2) }}
            </div>
        </div>
    </div>

    <div class="admin-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 8px;">Customer Info</h3>
            <p><strong>Name:</strong> {{ $order->customer?->name ?? '-' }}</p>
            <p><strong>Email:</strong> {{ $order->customer?->email ?? '-' }}</p>
            <p><strong>Phone:</strong> {{ $order->customer?->phone ?? '-' }}</p>
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Shipping Info</h3>
            <p><strong>Name:</strong> {{ $order->shipping_name ?? '-' }}</p>
            <p><strong>Phone:</strong> {{ $order->shipping_phone ?? '-' }}</p>
            <p><strong>Address:</strong> {{ $order->shipping_address ?? '-' }}</p>
            <p>
                <strong>City/State:</strong>
                {{ $order->shipping_city ?? '-' }} {{ $order->shipping_state ?? '' }}
            </p>
            <p><strong>Postcode:</strong> {{ $order->shipping_postcode ?? '-' }}</p>
            <p><strong>Country:</strong> {{ $order->shipping_country ?? '-' }}</p>
            <p><strong>Tracking:</strong> {{ $order->tracking_number ?? '-' }}</p>
            <p><strong>Address Confirmed:</strong> {{ $order->shipping_confirmed_at?->format('Y-m-d H:i') ?? 'No' }}</p>
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Payment Info</h3>
            <p><strong>Method:</strong> {{ $order->payment_method ?? '-' }}</p>
            <p><strong>Reference:</strong> {{ $order->payment_reference ?? '-' }}</p>
            <p><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}</p>
            <p><strong>Paid At:</strong> {{ $order->payment_verified_at?->format('Y-m-d H:i') ?? 'No' }}</p>
            @if($order->payment_last_failed_at)
                <p><strong>Last Failed:</strong> {{ $order->payment_last_failed_at->format('Y-m-d H:i') }}</p>
                <p><strong>Reason:</strong> {{ $order->payment_last_failure_reason ?? '-' }}</p>
            @endif
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Order Items</h3>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Product</th>
                    <th>Maintenance Year</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Tax</th>
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
                        <td>RM {{ number_format((float) ($item->line_subtotal ?? $item->total_price), 2) }}</td>
                        <td>RM {{ number_format((float) ($item->line_discount ?? 0), 2) }}</td>
                        <td>RM {{ number_format((float) ($item->line_tax ?? 0), 2) }}</td>
                        <td>RM {{ number_format((float) ($item->line_total ?? $item->total_price), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">No items recorded for this order.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 12px;">Update Status</h3>
            @if($order->status === 'cancelled')
                <p>Reopen the order to update status.</p>
            @else
                <form method="POST" action="{{ route('orders.update-status', $order) }}">
                    @csrf
                    @method('PATCH')
                    <label for="status">Status</label>
                    <select name="status" required>
                        @foreach($statuses as $status)
                            @if(!in_array($status, ['cancelled', 'shipped', 'delivered'], true) && ($order->status === $status || $order->canTransitionStatusTo($status)))
                                <option value="{{ $status }}" {{ $order->status === $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <label for="note">Note (optional)</label>
                    <input type="text" name="note" value="">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            @endif
        </div>
        @if($isAdmin)
            <div>
                <h3 style="margin-bottom: 12px;">Assign Staff/Courier</h3>
                <form method="POST" action="{{ route('orders.assign', $order) }}">
                    @csrf
                    @method('PATCH')
                    <label for="assigned_to_user_id">Assigned Staff</label>
                    <select name="assigned_to_user_id" id="assigned_to_user_id">
                        <option value="">- Unassigned -</option>
                        @foreach(($staffUsers ?? []) as $staffUser)
                            <option value="{{ $staffUser->user_id }}"
                                {{ ($order->assigned_to_user_id ?? null) === $staffUser->user_id ? 'selected' : '' }}>
                                {{ $staffUser->name }} ({{ $staffUser->email }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline">Save Assignment</button>
                    @if(!empty($order->assigned_to) && empty($order->assigned_to_user_id))
                        <p style="margin-top:10px; color:#bfbfbf; font-size:12px;">
                            Legacy assignment text: {{ $order->assigned_to }}
                        </p>
                    @endif
                </form>
            </div>
        @endif
        <div>
            <h3 style="margin-bottom: 12px;">Payment Confirmation</h3>
            <form method="POST" action="{{ route('orders.verify-payment', $order) }}">
                @csrf
                @method('PATCH')
                <button type="submit"
                        class="btn btn-primary"
                        {{ in_array($order->payment_status, ['paid', 'refund_pending', 'partial_refund', 'refunded'], true) || $order->status === 'cancelled' ? 'disabled' : '' }}>
                    @if($order->status === 'cancelled')
                        Payment Cancelled
                    @else
                        {{ $order->payment_status === 'paid' ? 'Payment Confirmed' : 'Confirm Payment' }}
                    @endif
                </button>
            </form>
        </div>
        @if($isAdmin && in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true))
            <div>
                <h3 style="margin-bottom: 12px;">Refund (Stripe)</h3>
                <form method="POST" action="{{ route('orders.refund.stripe', $order) }}">
                    @csrf
                    <label for="amount">Amount (RM, optional)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="">
                    <label for="reason">Reason (optional)</label>
                    <select name="reason">
                        <option value="">-</option>
                        <option value="requested_by_customer">Requested by customer</option>
                        <option value="duplicate">Duplicate</option>
                        <option value="fraudulent">Fraudulent</option>
                    </select>
                    <button type="submit" class="btn btn-outline" {{ !$order->payment_reference ? 'disabled' : '' }}>
                        Initiate Refund
                    </button>
                </form>
                @if($order->payment_method === 'stripe_fpx')
                    <p style="margin-top:10px; color:#bfbfbf; font-size:12px;">
                        FPX refunds are asynchronous and can take several days to complete.
                    </p>
                @endif
            </div>
        @endif
        <div>
            <h3 style="margin-bottom: 12px;">Shipment Process</h3>
            @if($order->status === 'cancelled')
                <p>Shipment updates are disabled for cancelled orders.</p>
            @else
                <form method="POST" action="{{ route('orders.update-shipment', $order) }}">
                    @csrf
                    @method('PATCH')
                    <label for="shipment_status">Fulfillment Status</label>
                    <select name="shipment_status" required>
                        @foreach($shipmentStatuses as $shipmentStatus)
                            @continue(!in_array($shipmentStatus, $allowedShipmentStatuses, true))
                            <option value="{{ $shipmentStatus }}"
                                {{ $order->shipment_status === $shipmentStatus ? 'selected' : '' }}>
                                {{ ucfirst($shipmentStatus) }}
                            </option>
                        @endforeach
                    </select>
                    @if($lockShippingDetails)
                        <p style="margin:6px 0 0; color:#bfbfbf; font-size:12px;">
                            Shipping details are locked after shipment starts (admin only).
                        </p>
                    @endif
                    <label for="tracking_number">Tracking Number</label>
                    <input type="text" name="tracking_number" value="{{ $order->tracking_number }}" {{ $lockShippingDetails ? 'readonly' : '' }}>
                    <label for="shipping_name">Shipping Name</label>
                    <input type="text" name="shipping_name" value="{{ $order->shipping_name }}" {{ $lockShippingDetails ? 'readonly' : '' }}>
                    <label for="shipping_phone">Shipping Phone</label>
                    <input type="text" name="shipping_phone" value="{{ $order->shipping_phone }}" {{ $lockShippingDetails ? 'readonly' : '' }}>
                    <label for="shipping_address">Shipping Address</label>
                    <textarea name="shipping_address" {{ $lockShippingDetails ? 'readonly' : '' }}>{{ $order->shipping_address }}</textarea>
                    <label for="shipping_city">City</label>
                    <input type="text" name="shipping_city" value="{{ $order->shipping_city }}" {{ $lockShippingDetails ? 'readonly' : '' }}>
                    <label for="shipping_state">State</label>
                    <input type="text" name="shipping_state" value="{{ $order->shipping_state }}" {{ $lockShippingDetails ? 'readonly' : '' }}>
                    <label for="shipping_postcode">Postcode</label>
                    <input type="text" name="shipping_postcode" value="{{ $order->shipping_postcode }}" {{ $lockShippingDetails ? 'readonly' : '' }}>
                    <label for="shipping_country">Country</label>
                    <input type="text" name="shipping_country" value="{{ $order->shipping_country }}" {{ $lockShippingDetails ? 'readonly' : '' }}>
                    @if(!$order->shipping_confirmed_at && !$lockShippingDetails)
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="confirm_shipping" value="1">
                            Confirm shipping address
                        </label>
                    @elseif(!$order->shipping_confirmed_at && $lockShippingDetails)
                        <p style="margin-top:10px; color:#bfbfbf; font-size:12px;">
                            Address confirmation is locked after shipment starts (admin only).
                        </p>
                    @else
                        <p>Address confirmed on {{ $order->shipping_confirmed_at->format('Y-m-d H:i') }}.</p>
                    @endif
                    <button type="submit" class="btn btn-outline">Save Shipment</button>
                </form>
            @endif
        </div>
        <div>
            <h3 style="margin-bottom: 12px;">Cancel Order</h3>
            @if($order->status === 'cancelled')
                <p>This order is already cancelled.</p>
            @elseif(!$canCancelOrder)
                <p>This order can no longer be cancelled (shipping already started or order completed).</p>
            @else
                <form method="POST" action="{{ route('orders.cancel', $order) }}">
                    @csrf
                    @method('PATCH')
                    <label for="cancel_reason">Reason</label>
                    <textarea name="cancel_reason" required></textarea>
                    <button type="submit" class="btn btn-outline">Cancel Order</button>
                </form>
            @endif
        </div>
        <div>
            <h3 style="margin-bottom: 12px;">Reopen Order</h3>
            @if($order->status === 'cancelled')
                <form method="POST" action="{{ route('orders.reopen', $order) }}">
                    @csrf
                    @method('PATCH')
                    <label for="note">Reason (optional)</label>
                    <input type="text" name="note" value="">
                    <button type="submit" class="btn btn-outline">Reopen</button>
                </form>
            @else
                <p>Only cancelled orders can be reopened.</p>
            @endif
        </div>
    </div>

    @if($isAdmin && in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true))
        <div class="admin-card">
            <h3 style="margin-bottom: 12px;">Refund History</h3>
            @php
                $refundedTotalCents = $order->refunds->where('status', 'succeeded')->sum('amount_cents');
            @endphp
            <p style="margin-bottom: 12px;">
                <strong>Total Refunded (succeeded):</strong>
                RM {{ number_format(((int) $refundedTotalCents) / 100, 2) }}
            </p>

            @if($order->refunds->isEmpty())
                <p>No refunds recorded.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Provider</th>
                            <th>Refund ID</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Requested By</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->refunds as $index => $refund)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $refund->provider }}</td>
                                <td>{{ $refund->provider_refund_id }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $refund->status)) }}</td>
                                <td>RM {{ number_format(((int) $refund->amount_cents) / 100, 2) }}</td>
                                <td>{{ $refund->reason ?? '-' }}</td>
                                <td>{{ $refund->requestedBy?->name ?? '-' }}</td>
                                <td>{{ $refund->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Status History</h3>
        @if($order->statusHistories->isEmpty())
            <p>No status history available.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Changed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->statusHistories as $index => $history)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ ucfirst($history->status) }}</td>
                            <td>{{ $history->note ?? '-' }}</td>
                            <td>{{ $history->changedBy?->name ?? '-' }}</td>
                            <td>{{ $history->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
