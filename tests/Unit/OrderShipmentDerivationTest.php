<?php

use App\Services\OrderStateEngine;

test('deriveOrderShipmentStatus aggregates multiple shipments', function () {
    expect(OrderStateEngine::deriveOrderShipmentStatus([]))->toBe('pending');
    expect(OrderStateEngine::deriveOrderShipmentStatus(['pending', 'pending']))->toBe('pending');
    expect(OrderStateEngine::deriveOrderShipmentStatus(['pending', 'shipped']))->toBe('shipped');
    expect(OrderStateEngine::deriveOrderShipmentStatus(['delivered', 'delivered']))->toBe('delivered');
    expect(OrderStateEngine::deriveOrderShipmentStatus(['delivered', 'pending']))->toBe('shipped');
});

