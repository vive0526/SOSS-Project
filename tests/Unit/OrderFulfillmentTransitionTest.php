<?php

use App\Models\Order;

test('order status transitions are forward only', function () {
    $order = new Order([
        'status' => 'pending',
        'payment_method' => 'cash_on_delivery',
        'payment_status' => 'unpaid',
    ]);

    expect($order->canTransitionStatusTo('processing'))->toBeTrue();
    expect($order->canTransitionStatusTo('shipped'))->toBeFalse();
    expect($order->canTransitionStatusTo('delivered'))->toBeFalse();
    expect($order->canTransitionStatusTo('cancelled'))->toBeFalse(); // handled by cancel action

    $order->status = 'processing';
    expect($order->canTransitionStatusTo('shipped'))->toBeTrue();
    expect($order->canTransitionStatusTo('pending'))->toBeFalse();

    $order->status = 'shipped';
    expect($order->canTransitionStatusTo('delivered'))->toBeTrue();
    expect($order->canTransitionStatusTo('processing'))->toBeFalse();

    $order->status = 'delivered';
    expect($order->canTransitionStatusTo('processing'))->toBeFalse();
});

test('payment acceptability for fulfillment depends on payment method', function () {
    $cod = new Order([
        'status' => 'pending',
        'payment_method' => 'cash_on_delivery',
        'payment_status' => 'unpaid',
    ]);
    expect($cod->isPaymentAcceptableForFulfillment())->toBeTrue();

    $stripeUnpaid = new Order([
        'status' => 'pending',
        'payment_method' => 'stripe_card',
        'payment_status' => 'unpaid',
    ]);
    expect($stripeUnpaid->isPaymentAcceptableForFulfillment())->toBeFalse();

    $stripePaid = new Order([
        'status' => 'pending',
        'payment_method' => 'stripe_card',
        'payment_status' => 'paid',
    ]);
    expect($stripePaid->isPaymentAcceptableForFulfillment())->toBeTrue();
});

