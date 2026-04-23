<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CustomerStripeCheckoutController extends Controller
{
    public function start(Order $order, Request $request, StripeCheckoutService $checkoutService)
    {
        if ($order->user_id !== $request->user()->getKey()) {
            abort(403);
        }

        if ($order->status === 'cancelled') {
            return redirect()->route('customer.orders.show', $order)
                ->withErrors(['payment' => 'This order was cancelled.']);
        }

        if ($order->payment_verified_at) {
            return redirect()->route('customer.orders.show', $order)
                ->with('success', 'Payment is already verified.');
        }

        $paymentMethodType = match ($order->payment_method) {
            'stripe_card' => 'card',
            'stripe_fpx' => 'fpx',
            default => null,
        };

        if (!$paymentMethodType) {
            return redirect()->route('customer.orders.show', $order)
                ->withErrors(['payment' => 'This order is not configured for Stripe payment.']);
        }

        $successUrl = route('customer.checkout.stripe.success', [], true) . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('customer.checkout.stripe.cancel', $order, true);

        try {
            $session = $checkoutService->createCheckoutSession($order, $paymentMethodType, $successUrl, $cancelUrl);
        } catch (ApiErrorException|\RuntimeException $e) {
            Log::error('Unable to start Stripe checkout.', [
                'order_id' => $order->getKey(),
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('customer.orders.show', $order)
                ->withErrors(['payment' => 'Unable to start Stripe checkout. Please try again later.']);
        }

        $order->payment_reference = $session->id;
        $order->save();

        return redirect()->away($session->url);
    }

    public function success(Request $request, OrderPaymentService $orderPaymentService)
    {
        $sessionId = (string) $request->query('session_id');
        if ($sessionId === '') {
            return redirect()->route('customer.orders.index')
                ->withErrors(['payment' => 'Missing Stripe session id.']);
        }

        $secretKey = (string) config('services.stripe.secret');
        if ($secretKey === '') {
            return redirect()->route('customer.orders.index')
                ->withErrors(['payment' => 'Stripe is not configured.']);
        }

        $stripe = new StripeClient($secretKey);

        try {
            $session = $stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent'],
            ]);
        } catch (ApiErrorException $e) {
            return redirect()->route('customer.orders.index')
                ->withErrors(['payment' => 'Unable to verify Stripe session.']);
        }

        $orderId = $session->client_reference_id ?? ($session->metadata['order_id'] ?? null);
        if (!$orderId) {
            Log::warning('Stripe session missing order reference.', ['session_id' => $sessionId]);
            return redirect()->route('customer.orders.index')
                ->withErrors(['payment' => 'Unable to match payment to an order.']);
        }

        $order = Order::where('order_id', $orderId)->first();
        if (!$order || $order->user_id !== $request->user()->getKey()) {
            abort(403);
        }

        if ($session->payment_status === 'paid') {
            $paymentIntentId = is_string($session->payment_intent) ? $session->payment_intent : ($session->payment_intent?->id ?? null);
            $reference = $paymentIntentId ?: $session->id;
            $orderPaymentService->verifyPayment($order, null, $reference, 'Payment verified via Stripe (customer return).');

            return redirect()->route('customer.orders.show', $order)
                ->with('success', 'Payment successful. Your order is now verified.');
        }

        return redirect()->route('customer.orders.show', $order)
            ->with('success', 'Payment pending. We will verify it shortly.');
    }

    public function cancel(Order $order, Request $request)
    {
        if ($order->user_id !== $request->user()->getKey()) {
            abort(403);
        }

        return redirect()->route('customer.orders.show', $order)
            ->withErrors(['payment' => 'Stripe checkout was cancelled.']);
    }
}
