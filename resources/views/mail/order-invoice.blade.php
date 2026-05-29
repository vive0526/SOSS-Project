@php
    $orderNumber = $orderNumber ?? ($order->order_number ?: $order->getKey());
    $customerName = $notifiable->name ?? 'Customer';
    $logoUrl = $logoUrl ?? asset('images/sawit-kinabalu-logo.png');

    $formatMoney = function ($value) {
        return 'RM ' . number_format((float) ($value ?? 0), 2);
    };

    $items = $order->items ?? collect();
    $subtotal = (float) ($order->subtotal_amount ?? 0);
    $discount = (float) ($order->discount_amount ?? 0);
    $tax = (float) ($order->tax_amount ?? 0);
    $shippingFee = (float) ($order->shipping_fee ?? 0);
    $total = (float) ($order->total_amount ?? 0);

    $paymentMethod = (string) ($order->payment_method ?? '');
    $paymentMethodLabel = match ($paymentMethod) {
        'cash_on_delivery' => 'Cash on Delivery',
        'stripe_card' => 'Card (Stripe)',
        'stripe_fpx' => 'FPX (Stripe)',
        default => $paymentMethod !== '' ? $paymentMethod : 'N/A',
    };
@endphp

@component('mail::message')
@if($logoUrl)
<div style="text-align:center; margin-bottom: 14px;">
    <img src="{{ $logoUrl }}" alt="Sawit Kinabalu" style="height:64px; width:auto; display:inline-block;">
</div>
@endif

# Order Confirmation

Hi {{ $customerName }},  
We’ve received your order and will start processing it soon.

@component('mail::panel')
**Invoice summary**  
Order number: **{{ $orderNumber }}**  
Order date: **{{ optional($order->created_at)->format('Y-m-d H:i') }}**  
Payment method: **{{ $paymentMethodLabel }}**
@endcomponent

@component('mail::table')
| Item | Qty | Unit | Line |
|:--|--:|--:|--:|
@foreach($items as $item)
| {{ $item->product_name }} @if(!empty($item->maintenance_year)) (Maintenance: {{ $item->maintenance_year }}y) @endif | {{ (int) $item->quantity }} | {{ $formatMoney($item->unit_price) }} | {{ $formatMoney($item->line_total ?? ((float) $item->unit_price * (int) $item->quantity)) }} |
@endforeach
@endcomponent

@component('mail::table')
|  |  |
|:--|--:|
| Subtotal | {{ $formatMoney($subtotal) }} |
| Discount | -{{ $formatMoney($discount) }} |
| Tax | {{ $formatMoney($tax) }} |
| Shipping | {{ $formatMoney($shippingFee) }} |
| **Total** | **{{ $formatMoney($total) }}** |
@endcomponent

@component('mail::panel')
**Shipping details**  
Name: {{ $order->shipping_name ?? '—' }}  
Phone: {{ $order->shipping_phone ?? '—' }}  
Address: {{ $order->shipping_address ?? '—' }}, {{ $order->shipping_city ?? '' }} {{ $order->shipping_postcode ?? '' }}, {{ $order->shipping_state ?? '' }}, {{ $order->shipping_country ?? '' }}
@endcomponent

@if(filled($order->delivery_notes))
@component('mail::panel')
**Delivery notes**  
{{ $order->delivery_notes }}
@endcomponent
@endif

@component('mail::button', ['url' => route('customer.orders.show', $order)])
View your order
@endcomponent

Thanks,  
{{ config('app.name') }}
@endcomponent
