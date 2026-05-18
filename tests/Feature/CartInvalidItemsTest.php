<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('removes only invalid cart lines instead of wiping the whole cart', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('Feature tests currently fail on sqlite due to a migration using GREATEST(). Run this test on MySQL or fix the sqlite migration compatibility first.');
    }

    $user = User::factory()->create([
        'role' => 'customer',
    ]);

    Product::query()->create([
        'product_id' => 'P1',
        'name' => 'Test Product',
        'description' => 'Test',
        'price' => 10.00,
        'product_type' => 'normal',
        'requires_maintenance' => false,
        'maintenance_years' => null,
        'maintenance_prices' => null,
        'stock_quantity' => 10,
        'reorder_level' => 0,
        'image' => null,
        'category_id' => null,
        'user_id' => $user->id,
    ]);

    $validKey = 'P1-0';
    $invalidKey = 'P999-0';

    $response = $this->actingAs($user)
        ->withSession([
            'cart' => [
                $validKey => [
                    'key' => $validKey,
                    'product_id' => 'P1',
                    'quantity' => 2,
                    'maintenance_year' => null,
                ],
                $invalidKey => [
                    'key' => $invalidKey,
                    'product_id' => 'P999',
                    'quantity' => 1,
                    'maintenance_year' => null,
                ],
            ],
        ])
        ->get('/customer/cart');

    $response->assertOk();
    $response->assertSessionHas('warning');
    $response->assertSessionHas('cart', [
        $validKey => [
            'key' => $validKey,
            'product_id' => 'P1',
            'quantity' => 2,
            'maintenance_year' => null,
        ],
    ]);
});
