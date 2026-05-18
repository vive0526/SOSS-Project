<?php

use App\Http\Controllers\CustomerCheckoutController;
use App\Models\Product;

test('checkout cart revalidation normalizes keys and removes invalid maintenance years', function () {
    $controller = new CustomerCheckoutController();

    $product = new Product([
        'name' => 'Maintenance Product',
        'requires_maintenance' => true,
        'maintenance_prices' => [
            1 => 99.00,
        ],
        'image' => null,
    ]);
    $product->product_id = 'P1';

    $products = collect([$product])->keyBy('product_id');

    $cart = [
        // Wrong key (0) but valid maintenance year (1) selected.
        'P1-0' => [
            'key' => 'P1-0',
            'product_id' => 'P1',
            'quantity' => 1,
            'maintenance_year' => 1,
        ],
        // Invalid maintenance year (2) should be removed.
        'P1-2' => [
            'key' => 'P1-2',
            'product_id' => 'P1',
            'quantity' => 1,
            'maintenance_year' => 2,
        ],
    ];

    $method = new ReflectionMethod(CustomerCheckoutController::class, 'priceCartFromProductMap');
    $method->setAccessible(true);
    $priced = $method->invoke($controller, $cart, $products);

    expect($priced['ok'])->toBeTrue();
    expect($priced['removedInvalidCount'])->toBe(1);
    expect($priced['normalizedCount'])->toBe(1);
    expect(array_keys($priced['cart']))->toBe(['P1-1']);
    expect($priced['cart']['P1-1']['maintenance_year'])->toBe(1);
    expect($priced['cart']['P1-1']['price'])->toBe(99.00);
});
