<?php

use App\Services\OrderStateEngine;

test('derive refund payment status returns refund_pending when a refund is pending', function () {
    $derived = OrderStateEngine::deriveRefundPaymentStatus('paid', 10000, 0, true);
    expect($derived)->toBe('refund_pending');
});

test('derive refund payment status returns partial_refund when some refunds succeeded', function () {
    $derived = OrderStateEngine::deriveRefundPaymentStatus('refund_pending', 10000, 2500, true);
    expect($derived)->toBe('partial_refund');
});

test('derive refund payment status returns refunded when succeeded refunds meet or exceed refundable total', function () {
    $derived = OrderStateEngine::deriveRefundPaymentStatus('partial_refund', 10000, 10000, false);
    expect($derived)->toBe('refunded');

    $derived = OrderStateEngine::deriveRefundPaymentStatus('partial_refund', 10000, 15000, false);
    expect($derived)->toBe('refunded');
});

test('derive refund payment status returns paid when no refunds succeeded and none pending', function () {
    $derived = OrderStateEngine::deriveRefundPaymentStatus('refund_pending', 10000, 0, false);
    expect($derived)->toBe('paid');
});

test('derive refund payment status ignores non-refund-related current statuses', function () {
    $derived = OrderStateEngine::deriveRefundPaymentStatus('unpaid', 10000, 0, true);
    expect($derived)->toBeNull();
});

