<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, OrderPaymentService $orderPaymentService)
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
                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'status' => $order->status,
                    'note' => 'Stripe async payment failed; customer should try again.',
                    'changed_by' => null,
                ]);
                return response('ok');
            }

            if ($event->type === 'checkout.session.expired') {
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
