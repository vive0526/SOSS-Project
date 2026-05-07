<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderStateEngine;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeRefundController extends Controller
{
    public function store(Request $request, Order $order, OrderStateEngine $orderStateEngine)
    {
        $secretKey = (string) config('services.stripe.secret');
        if ($secretKey === '') {
            return back()->withErrors(['refund' => 'Stripe is not configured.']);
        }

        if (!in_array($order->payment_status, ['paid', 'partial_refund'], true)) {
            return back()->withErrors(['refund' => 'This order is not eligible for refund (payment status: ' . ($order->payment_status ?? '-') . ').']);
        }

        if (!$order->payment_reference) {
            return back()->withErrors(['refund' => 'No payment reference found for this order.']);
        }

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|in:requested_by_customer,duplicate,fraudulent',
        ]);

        $stripe = new StripeClient($secretKey);

        $paymentIntentId = null;
        $chargeId = null;

        if (str_starts_with($order->payment_reference, 'pi_')) {
            $paymentIntentId = $order->payment_reference;
        } elseif (str_starts_with($order->payment_reference, 'ch_')) {
            $chargeId = $order->payment_reference;
        } elseif (str_starts_with($order->payment_reference, 'cs_')) {
            try {
                $session = $stripe->checkout->sessions->retrieve($order->payment_reference, [
                    'expand' => ['payment_intent'],
                ]);
                $paymentIntentId = is_string($session->payment_intent)
                    ? $session->payment_intent
                    : ($session->payment_intent?->id ?? null);
            } catch (ApiErrorException $e) {
                return back()->withErrors(['refund' => 'Unable to retrieve Stripe session for refund.']);
            }
        }

        if (!$paymentIntentId && !$chargeId) {
            return back()->withErrors(['refund' => 'Unsupported payment reference format.']);
        }

        $params = [];
        if ($paymentIntentId) {
            $params['payment_intent'] = $paymentIntentId;
        }
        if ($chargeId) {
            $params['charge'] = $chargeId;
        }

        if (!empty($data['amount'])) {
            $params['amount'] = (int) round(((float) $data['amount']) * 100);
        }
        if (!empty($data['reason'])) {
            $params['reason'] = $data['reason'];
        }

        try {
            $refund = $stripe->refunds->create($params);
        } catch (ApiErrorException $e) {
            return back()->withErrors(['refund' => 'Stripe refund failed: ' . $e->getMessage()]);
        }

        $expected = (int) round(((float) $order->total_amount) * 100);
        $amount = (int) ($refund->amount ?? 0);
        $refundStatus = (string) ($refund->status ?? '');

        $nextPaymentStatus = 'refund_pending';
        if ($refundStatus === 'succeeded') {
            $nextPaymentStatus = ($expected > 0 && $amount < $expected) ? 'partial_refund' : 'refunded';
        }

        try {
            $orderStateEngine->transitionPaymentStatus($order, $nextPaymentStatus);
        } catch (\DomainException $e) {
            // If the refund succeeded in Stripe but our state machine rejected the transition,
            // keep the Stripe refund record in history for manual review.
            OrderStatusHistory::create([
                'order_id' => $order->getKey(),
                'status' => $order->status,
                'note' => 'Stripe refund created but local status update failed: ' . $e->getMessage(),
                'changed_by' => $request->user()?->getKey(),
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'status' => $order->status,
            'note' => 'Stripe refund created: ' . $refund->id . ' (status: ' . ($refund->status ?? '-') . ').',
            'changed_by' => $request->user()?->getKey(),
        ]);

        return back()->with('success', 'Refund requested in Stripe: ' . $refund->id);
    }
}
