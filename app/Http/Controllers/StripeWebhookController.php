<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderStatusHistory;
use App\Services\OrderPaymentService;
use App\Services\OrderStateEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    private const EXPECTED_CURRENCY = 'myr';

    public function handle(Request $request, OrderPaymentService $orderPaymentService, OrderStateEngine $orderStateEngine)
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured.');
            return response('Webhook not configured', 500);
        }

        try {
            /** @var Event $event */
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        }

        if (str_starts_with($event->type, 'checkout.session.')) {
            $session = $event->data->object;
            $orderId = $session->client_reference_id ?? ($session->metadata->order_id ?? null);

            if (!$orderId) {
                Log::warning('Stripe webhook missing order reference.', [
                    'type' => $event->type,
                    'session_id' => $session->id ?? null,
                ]);

                return response('ok');
            }

            $order = Order::where('order_id', $orderId)->first();
            if (!$order) {
                Log::warning('Stripe webhook order not found.', [
                    'type' => $event->type,
                    'order_id' => $orderId,
                ]);

                return response('ok');
            }

            if (isset($session->currency) && $session->currency !== null) {
                $receivedCurrency = strtolower((string) $session->currency);
                if ($receivedCurrency !== self::EXPECTED_CURRENCY) {
                    Log::error('Stripe currency mismatch; not verifying order.', [
                        'order_id' => $orderId,
                        'expected' => self::EXPECTED_CURRENCY,
                        'received' => $receivedCurrency,
                        'event_type' => $event->type,
                    ]);

                    OrderStatusHistory::create([
                        'order_id' => $order->getKey(),
                        'status' => $order->status,
                        'note' => 'Stripe payment currency mismatch; manual review required.',
                        'changed_by' => null,
                    ]);

                    return response('ok');
                }
            }

            if (isset($session->amount_total) && $session->amount_total !== null) {
                $expected = (int) round(((float) $order->total_amount) * 100);
                if ((int) $session->amount_total !== $expected) {
                    Log::error('Stripe amount mismatch; not verifying order.', [
                        'order_id' => $orderId,
                        'expected' => $expected,
                        'received' => (int) $session->amount_total,
                        'event_type' => $event->type,
                    ]);

                    OrderStatusHistory::create([
                        'order_id' => $order->getKey(),
                        'status' => $order->status,
                        'note' => 'Stripe payment amount mismatch; manual review required.',
                        'changed_by' => null,
                    ]);

                    return response('ok');
                }
            }

            $paymentIntentId = $session->payment_intent ?? null;
            $sessionId = $session->id ?? null;
            $reference = is_string($paymentIntentId) && $paymentIntentId !== '' ? $paymentIntentId : $sessionId;

            if ($event->type === 'checkout.session.completed') {
                if (($session->payment_status ?? null) === 'paid') {
                    $orderPaymentService->verifyPayment($order, null, $reference, 'Payment verified via Stripe webhook (completed).');
                } else {
                    try {
                        $orderStateEngine->transitionPaymentStatus($order, 'pending');
                    } catch (\DomainException $e) {
                        Log::warning('Stripe checkout completed but local payment status transition failed.', [
                            'order_id' => $order->getKey(),
                            'error' => $e->getMessage(),
                        ]);
                    }

                    OrderStatusHistory::create([
                        'order_id' => $order->getKey(),
                        'status' => $order->status,
                        'note' => 'Stripe checkout completed; payment pending confirmation.',
                        'changed_by' => null,
                    ]);
                }

                return response('ok');
            }

            if ($event->type === 'checkout.session.async_payment_succeeded') {
                $orderPaymentService->verifyPayment($order, null, $reference, 'Payment verified via Stripe webhook (async succeeded).');
                return response('ok');
            }

            if ($event->type === 'checkout.session.async_payment_failed') {
                try {
                    $orderStateEngine->transitionPaymentStatus($order, 'unpaid', 'async_payment_failed', true);
                } catch (\DomainException $e) {
                    Log::warning('Stripe async payment failed but local payment status transition failed.', [
                        'order_id' => $order->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }

                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'status' => $order->status,
                    'note' => 'Stripe async payment failed; customer should try again.',
                    'changed_by' => null,
                ]);
                return response('ok');
            }

            if ($event->type === 'checkout.session.expired') {
                try {
                    $orderStateEngine->transitionPaymentStatus($order, 'unpaid', 'checkout_session_expired', true);
                } catch (\DomainException $e) {
                    Log::warning('Stripe checkout expired but local payment status transition failed.', [
                        'order_id' => $order->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }

                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'status' => $order->status,
                    'note' => 'Stripe checkout session expired; customer should try again.',
                    'changed_by' => null,
                ]);
                return response('ok');
            }
        }

        if (str_starts_with($event->type, 'refund.')) {
            $refund = $event->data->object;
            $paymentIntentId = $refund->payment_intent ?? null;

            if ($paymentIntentId) {
                $order = Order::where('payment_reference', $paymentIntentId)->first();
                if ($order) {
                    $amount = (int) ($refund->amount ?? 0);
                    $refundStatus = (string) ($refund->status ?? '');

                    try {
                        $refundId = (string) ($refund->id ?? '');
                        if ($refundId !== '') {
                            OrderRefund::query()->updateOrCreate(
                                [
                                    'provider' => 'stripe',
                                    'provider_refund_id' => $refundId,
                                ],
                                [
                                    'order_id' => $order->getKey(),
                                    'provider_payment_intent_id' => (string) ($refund->payment_intent ?? '') ?: null,
                                    'amount_cents' => $amount,
                                    'currency' => (string) ($refund->currency ?? 'myr') ?: 'myr',
                                    'reason' => (string) ($refund->reason ?? '') ?: null,
                                    'status' => $refundStatus !== '' ? $refundStatus : 'pending',
                                    'requested_by' => null,
                                    'processed_at' => $refundStatus === 'succeeded' ? now() : null,
                                    'provider_payload' => [
                                        'id' => $refundId,
                                        'status' => $refundStatus,
                                        'amount' => $amount,
                                        'currency' => (string) ($refund->currency ?? 'myr'),
                                        'payment_intent' => $refund->payment_intent ?? null,
                                        'charge' => $refund->charge ?? null,
                                    ],
                                ]
                            );
                        }

                        $orderStateEngine->recalculateRefundPaymentStatus($order);
                    } catch (\DomainException $e) {
                        Log::warning('Stripe refund webhook received but local payment status transition failed.', [
                            'order_id' => $order->getKey(),
                            'refund_status' => $refundStatus,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    OrderStatusHistory::create([
                        'order_id' => $order->getKey(),
                        'status' => $order->status,
                        'note' => 'Stripe refund update: ' . $event->type . ' (refund ' . ($refund->id ?? '-') . ').',
                        'changed_by' => null,
                    ]);
                }
            }

            return response('ok');
        }

        return response('ok');
    }
}
