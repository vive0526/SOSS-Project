@extends('layouts.admin')

@section('title', 'Orders')
@section('page_title', 'Orders')
@section('page_subtitle', 'View and manage customer orders')

@section('content')
    @if(session('success'))
        <div class="admin-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="admin-card">
            <p>There were some issues with your request.</p>
        </div>
    @endif

    <div class="admin-card">
        <form method="GET" action="{{ route('orders.index') }}"
              style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label for="search">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Order number or customer">
            </div>
            <div>
                <label for="status">Status</label>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}"
                            {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="shipment_status">Shipment</label>
                <select name="shipment_status">
                    <option value="">All shipment statuses</option>
                    @foreach($shipmentStatuses as $shipmentStatus)
                        <option value="{{ $shipmentStatus }}"
                            {{ ($filters['shipment_status'] ?? '') === $shipmentStatus ? 'selected' : '' }}>
                            {{ ucfirst($shipmentStatus) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="payment">Payment</label>
                <select name="payment">
                    <option value="">All payments</option>
                    <option value="paid" {{ in_array(($filters['payment'] ?? ''), ['paid', 'verified'], true) ? 'selected' : '' }}>Paid</option>
                    <option value="pending" {{ ($filters['payment'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="unpaid" {{ in_array(($filters['payment'] ?? ''), ['unpaid', 'unverified'], true) ? 'selected' : '' }}>Unpaid</option>
                    <option value="refund_pending" {{ ($filters['payment'] ?? '') === 'refund_pending' ? 'selected' : '' }}>Refund pending</option>
                    <option value="partial_refund" {{ ($filters['payment'] ?? '') === 'partial_refund' ? 'selected' : '' }}>Partial refund</option>
                    <option value="refunded" {{ ($filters['payment'] ?? '') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                </select>
            </div>
            <div>
                <label for="date_from">From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('orders.index') }}" class="btn">Reset</a>
        </form>
    </div>

    <div class="admin-card">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px;">
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Total Orders</div>
                <div style="font-size:24px; font-weight:700;">{{ $totalOrders }}</div>
            </div>
            @foreach($statuses as $status)
                <div>
                    <div style="color:#bfbfbf; font-size:12px;">{{ ucfirst($status) }}</div>
                    <div style="font-size:24px; font-weight:700;">
                        {{ $statusCounts[$status] ?? 0 }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="admin-card">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Shipment</th>
                    <th>Payment</th>
                    <th>Tracking</th>
                    <th>Total</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $index => $order)
                    @php
                        $statusClass = match ($order->status) {
                            'pending' => 'status-pending',
                            'processing' => 'status-processing',
                            'shipped' => 'status-shipped',
                            'delivered' => 'status-delivered',
                            'cancelled' => 'status-cancelled',
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
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->customer?->name ?? '-' }}</td>
                        <td><span class="{{ $statusClass }}">{{ ucfirst($order->status) }}</span></td>
                        <td>
                            <span class="status-{{ $order->shipment_status }}">
                                {{ ucfirst($order->shipment_status) }}
                            </span>
                        </td>
                        <td>
                            <span class="{{ $paymentClass }}">
                                {{ ucwords(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}
                            </span>
                        </td>
                        <td>{{ $order->tracking_number ?? '-' }}</td>
                        <td>
                            @if((float) ($order->total_amount ?? 0) > 0)
                                <div>RM {{ number_format((float) $order->total_amount, 2) }}</div>
                                <div style="color:#bfbfbf; font-size:12px;">
                                    Sub: RM {{ number_format((float) ($order->subtotal_amount ?? 0), 2) }} |
                                    Ship: RM {{ number_format((float) ($order->shipping_fee ?? 0), 2) }}
                                </div>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $order->created_at?->format('Y-m-d') }}</td>
                        <td>
                            <a class="btn-admin btn-edit" href="{{ route('orders.show', $order) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
