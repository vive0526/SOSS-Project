<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function requireMysqlForPrefixedKeyTests(): void
{
    // These rules depend on the prefixed-id schema (string primary keys + string foreign keys),
    // which is only migrated in MySQL. SQLite testing migrations intentionally skip that conversion.
    if (DB::getDriverName() !== 'mysql') {
        test()->markTestSkipped('Requires MySQL test database (prefixed-id migrations are skipped for SQLite).');
    }
}

function makeStaffUser(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'status' => 'active',
    ]);
}

function makeAdminUser(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
    ]);
}

function makeCustomerUser(): User
{
    return User::factory()->create([
        'role' => 'customer',
        'status' => 'active',
    ]);
}

function makeProduct(User $createdBy, array $overrides = []): Product
{
    return Product::create(array_merge([
        'name' => 'Test Product',
        'description' => 'Test description',
        'price' => 10.00,
        'stock_quantity' => 50,
        'user_id' => $createdBy->getKey(),
    ], $overrides));
}

function makeOrder(User $customer, array $overrides = []): Order
{
    return Order::create(array_merge([
        'user_id' => $customer->getKey(),
        'status' => 'processing',
        'shipment_status' => 'pending',
        'payment_method' => 'cash_on_delivery',
        'payment_status' => 'unpaid',
        'total_amount' => 10.00,
        'shipping_name' => 'Customer Name',
        'shipping_phone' => '0123456789',
        'shipping_address' => 'Address 1',
        'shipping_city' => 'City',
        'shipping_state' => 'State',
        'shipping_postcode' => '12345',
        'shipping_country' => 'MY',
    ], $overrides));
}

function makeOrderItem(Order $order, Product $product, array $overrides = []): OrderItem
{
    return OrderItem::create(array_merge([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'product_name' => $product->name,
        'maintenance_year' => null,
        'quantity' => 1,
        'unit_price' => 10.00,
        'total_price' => 10.00,
    ], $overrides));
}

it('prevents staff from changing shipment_status shipped -> pending', function () {
    requireMysqlForPrefixedKeyTests();

    $staff = makeStaffUser();
    $customer = makeCustomerUser();
    $creator = makeStaffUser();
    $product = makeProduct($creator);

    $order = makeOrder($customer, [
        'shipment_status' => 'shipped',
        'tracking_number' => 'TRACK-1',
    ]);
    makeOrderItem($order, $product);

    $response = $this->actingAs($staff)->patch(route('orders.update-shipment', $order), [
        'shipment_status' => 'pending',
    ]);

    $response->assertSessionHasErrors('shipment_status');
    expect($order->fresh()->shipment_status)->toBe('shipped');
});

it('prevents staff from changing shipment_status pending -> delivered (no jump)', function () {
    requireMysqlForPrefixedKeyTests();

    $staff = makeStaffUser();
    $customer = makeCustomerUser();
    $order = makeOrder($customer, [
        'shipment_status' => 'pending',
    ]);

    $response = $this->actingAs($staff)->patch(route('orders.update-shipment', $order), [
        'shipment_status' => 'delivered',
    ]);

    $response->assertSessionHasErrors('shipment_status');
    expect($order->fresh()->shipment_status)->toBe('pending');
});

it('allows staff to change shipment_status shipped -> delivered', function () {
    requireMysqlForPrefixedKeyTests();

    $staff = makeStaffUser();
    $customer = makeCustomerUser();
    $order = makeOrder($customer, [
        'shipment_status' => 'shipped',
    ]);

    $response = $this->actingAs($staff)->patch(route('orders.update-shipment', $order), [
        'shipment_status' => 'delivered',
    ]);

    $response->assertSessionHasNoErrors();
    expect($order->fresh()->shipment_status)->toBe('delivered');
});

it('prevents staff from changing tracking or shipping details after shipment starts', function () {
    requireMysqlForPrefixedKeyTests();

    $staff = makeStaffUser();
    $customer = makeCustomerUser();
    $order = makeOrder($customer, [
        'shipment_status' => 'shipped',
        'tracking_number' => 'TRACK-1',
    ]);

    $response = $this->actingAs($staff)->patch(route('orders.update-shipment', $order), [
        'shipment_status' => 'shipped',
        'tracking_number' => 'TRACK-NEW',
    ]);

    $response->assertSessionHasErrors('shipment_status');
    expect($order->fresh()->tracking_number)->toBe('TRACK-1');
});

it('allows admin to change tracking or shipping details after shipment starts', function () {
    requireMysqlForPrefixedKeyTests();

    $admin = makeAdminUser();
    $customer = makeCustomerUser();
    $order = makeOrder($customer, [
        'shipment_status' => 'shipped',
        'tracking_number' => 'TRACK-1',
    ]);

    $response = $this->actingAs($admin)->patch(route('orders.update-shipment', $order), [
        'shipment_status' => 'shipped',
        'tracking_number' => 'TRACK-NEW',
        'shipping_address' => 'New Address',
    ]);

    $response->assertSessionHasNoErrors();
    $fresh = $order->fresh();
    expect($fresh->tracking_number)->toBe('TRACK-NEW');
    expect($fresh->shipping_address)->toBe('New Address');
});

it('blocks cancellations after shipment starts for staff and admin', function (string $role) {
    requireMysqlForPrefixedKeyTests();

    $user = $role === 'admin' ? makeAdminUser() : makeStaffUser();
    $customer = makeCustomerUser();
    $creator = makeStaffUser();
    $product = makeProduct($creator);

    $order = makeOrder($customer, [
        'status' => 'processing',
        'shipment_status' => 'shipped',
    ]);
    makeOrderItem($order, $product);

    $response = $this->actingAs($user)->patch(route('orders.cancel', $order), [
        'cancel_reason' => 'Test cancel after shipped',
    ]);

    $response->assertSessionHasErrors('status');
    expect($order->fresh()->status)->not->toBe('cancelled');
})->with(['staff', 'admin']);
