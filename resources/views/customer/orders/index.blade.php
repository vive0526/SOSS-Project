@extends('layouts.customer')

@section('title', 'My Orders')
@section('page_title', 'My Orders')
@section('page_subtitle', 'Track your recent purchases')

@section('content')
    @php
        $totalOrders = $statusCounts->sum();
    @endphp

    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-card">
            <p>There were some issues with your request.</p>
        </div>
    @endif

    <section class="customer-kpis">
        <div class="customer-card">
            <div class="customer-kpi__label">Total Orders</div>
            <div class="customer-kpi__value">{{ $totalOrders }}</div>
            <div class="customer-kpi__note">All time</div>
        </div>
        @foreach($statuses as $status)
            <div class="customer-card">
                <div class="customer-kpi__label">{{ ucfirst($status) }}</div>
                <div class="customer-kpi__value">{{ $statusCounts[$status] ?? 0 }}</div>
                <div class="customer-kpi__note">Orders</div>
            </div>
        @endforeach
    </section>

    <section class="customer-toolbar">
        <form class="customer-filter" method="GET" action="{{ route('customer.orders.index') }}">
            <div class="customer-field">
                <label for="search">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Order number">
            </div>
            <div class="customer-field">
                <label for="status">Order Status</label>
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
            <div class="customer-field">
                <label for="shipment_status">Shipment Status</label>
                <select name="shipment_status">
                    <option value="">All shipments</option>
                    @foreach($shipmentStatuses as $shipmentStatus)
                        <option value="{{ $shipmentStatus }}"
                            {{ ($filters['shipment_status'] ?? '') === $shipmentStatus ? 'selected' : '' }}>
                            {{ ucfirst($shipmentStatus) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="customer-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('customer.orders.index') }}" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </section>

    <section class="customer-section">
        <div class="customer-section__head">
            <h2>Order History</h2>
        </div>

        @if($orders->isEmpty())
            <div class="customer-empty">You have not placed any orders yet.</div>
        @else
            <div style="display:grid; gap:16px;">
                @foreach($orders as $order)
                    <div class="customer-card" style="display:grid; gap:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                            <div>
                                <div style="font-size:12px; color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em;">Order</div>
                                <div style="font-size:18px; font-weight:800; color:#4c2f1c;">
                                    {{ $order->order_number }}
                                </div>
                                <div style="color:#7b6a5b; font-size:12px;">
                                    Placed {{ $order->created_at?->format('Y-m-d') }}
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <span class="customer-status customer-status--{{ $order->status }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                                <span class="customer-status customer-status--{{ $order->shipment_status }}">
                                    {{ ucfirst($order->shipment_status) }}
                                </span>
                            </div>
                        </div>
                        <div style="display:flex; gap:18px; flex-wrap:wrap; color:#5e4a3b;">
                            <div><strong>Tracking:</strong> {{ $order->tracking_number ?? '-' }}</div>
                            <div><strong>Payment:</strong> {{ $order->payment_method ?? '-' }}</div>
                            <div>
                                <strong>Total:</strong>
                                {{ $order->total_amount > 0 ? 'RM ' . number_format((float) $order->total_amount, 2) : '-' }}
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a class="btn btn-outline" href="{{ route('customer.orders.show', $order) }}">View Details</a>
                            @if($order->status === 'pending' && $order->shipment_status === 'pending')
                                <a class="btn btn-primary" href="{{ route('customer.orders.show', $order) }}#cancel">
                                    Cancel Order
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
