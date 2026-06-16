<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\OrderStateEngine;
use App\Services\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CustomerStripeCheckoutController extends Controller
{
    private const EXPECTED_CURRENCY = 'myr';

    public function start(Order $order, Request $request, StripeCheckoutService $checkoutService, OrderStateEngine $orderStateEngine)
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
                'payment_method' => $order->payment_method,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('customer.orders.show', $order)
                ->withErrors(['payment' => 'Unable to start Stripe checkout. Please try again later.']);
        }

        $order->payment_reference = $session->id;
        $order->save();

        try {
            $orderStateEngine->transitionPaymentStatus($order, 'pending');
        } catch (\DomainException $e) {
            Log::warning('Unable to set order payment status to pending.', [
                'order_id' => $order->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->away($session->url);
    }

    public function success(Request $request, OrderPaymentService $orderPaymentService, OrderStateEngine $orderStateEngine)
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
            if (isset($session->currency) && $session->currency !== null) {
                $receivedCurrency = strtolower((string) $session->currency);
                if ($receivedCurrency !== self::EXPECTED_CURRENCY) {
                    Log::error('Stripe currency mismatch on customer return; not verifying order.', [
                        'order_id' => $order->getKey(),
                        'expected' => self::EXPECTED_CURRENCY,
                        'received' => $receivedCurrency,
                        'session_id' => $sessionId,
                    ]);

                    return redirect()->route('customer.orders.show', $order)
                        ->withErrors(['payment' => 'Payment received but currency mismatch detected. We will review it shortly.']);
                }
            }

            if (isset($session->amount_total) && $session->amount_total !== null) {
                $expected = (int) round(((float) $order->total_amount) * 100);
                if ((int) $session->amount_total !== $expected) {
                    Log::error('Stripe amount mismatch on customer return; not verifying order.', [
                        'order_id' => $order->getKey(),
                        'expected' => $expected,
                        'received' => (int) $session->amount_total,
                        'session_id' => $sessionId,
                    ]);

                    return redirect()->route('customer.orders.show', $order)
                        ->withErrors(['payment' => 'Payment received but amount mismatch detected. We will review it shortly.']);
                }
            }

            $paymentIntentId = is_string($session->payment_intent) ? $session->payment_intent : ($session->payment_intent?->id ?? null);
            $reference = $paymentIntentId ?: $session->id;
            $orderPaymentService->verifyPayment($order, null, $reference, 'Payment verified via Stripe (customer return).');

            $request->session()->flash('success', 'Payment successful. Your order is now verified.');

            return view('customer.checkout.stripe_success', [
                'order' => $order,
                'redirectUrl' => route('customer.orders.show', $order),
                'delayMs' => 2500,
            ]);
        }

        if ($order->payment_status !== 'pending') {
            try {
                $orderStateEngine->transitionPaymentStatus($order, 'pending');
            } catch (\DomainException $e) {
                // If the transition is not allowed, do not block the customer flow.
                Log::warning('Unable to set order payment status to pending on customer return.', [
                    'order_id' => $order->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('customer.orders.show', $order)
            ->with('success', 'Payment pending. We will verify it shortly.');
    }

    public function cancel(Order $order, Request $request, OrderStateEngine $orderStateEngine)
    {
        if ($order->user_id !== $request->user()->getKey()) {
            abort(403);
        }

        if ($order->payment_status !== 'unpaid') {
            try {
                $orderStateEngine->transitionPaymentStatus($order, 'unpaid', 'customer_cancelled', true);
            } catch (\DomainException $e) {
                Log::warning('Unable to mark order as unpaid after customer cancelled Stripe checkout.', [
                    'order_id' => $order->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('customer.orders.show', $order)
            ->withErrors(['payment' => 'Stripe checkout was cancelled.']);
    }
}
