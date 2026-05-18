<?php

use App\Models\Order;

test('order refundable total cents uses stored grand total only', function () {
    $order = new Order([
        'total_amount' => 110.00,
        'shipping_fee' => 10.00,
    ]);

    expect($order->refundableTotalCents())->toBe(11000);
});

