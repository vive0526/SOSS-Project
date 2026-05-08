<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderRefund;
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

        $refundableTotalCents = (int) round((((float) ($order->total_amount ?? 0)) + ((float) ($order->shipping_fee ?? 0))) * 100);
        $alreadyRefundedCents = (int) OrderRefund::query()
            ->where('order_id', $order->getKey())
            ->where('status', 'succeeded')
            ->sum('amount_cents');
        $remainingCents = max(0, $refundableTotalCents - $alreadyRefundedCents);

        if ($refundableTotalCents > 0 && $alreadyRefundedCents >= $refundableTotalCents) {
            return back()->withErrors(['refund' => 'This order is already fully refunded.']);
        }

        if (!empty($data['amount'])) {
            $requestedCents = (int) round(((float) $data['amount']) * 100);
            if ($remainingCents > 0 && $requestedCents > $remainingCents) {
                return back()->withErrors(['refund' => 'Refund amount exceeds remaining refundable amount.']);
            }
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

        $amount = (int) ($refund->amount ?? 0);
        $refundStatus = (string) ($refund->status ?? '');

        $refundId = (string) ($refund->id ?? '');
        if ($refundId !== '') {
            OrderRefund::query()->updateOrCreate(
                [
                    'provider' => 'stripe',
                    'provider_refund_id' => $refundId,
                ],
                [
                    'order_id' => $order->getKey(),
                    'provider_payment_intent_id' => (string) ($refund->payment_intent ?? ($paymentIntentId ?? '')) ?: null,
                    'amount_cents' => $amount,
                    'currency' => (string) ($refund->currency ?? 'myr') ?: 'myr',
                    'reason' => (string) ($refund->reason ?? ($data['reason'] ?? '')) ?: null,
                    'status' => $refundStatus !== '' ? $refundStatus : 'pending',
                    'requested_by' => $request->user()?->getKey(),
                    'processed_at' => $refundStatus === 'succeeded' ? now() : null,
                    'provider_payload' => [
                        'id' => $refundId,
                        'status' => $refundStatus,
                        'amount' => $amount,
                        'currency' => (string) ($refund->currency ?? 'myr'),
                        'payment_intent' => $refund->payment_intent ?? $paymentIntentId,
                        'charge' => $refund->charge ?? $chargeId,
                    ],
                ]
            );
        }

        try {
            $orderStateEngine->recalculateRefundPaymentStatus($order);
        } catch (\DomainException $e) {
            // Keep a history note for manual review if the local status couldn't be updated.
            OrderStatusHistory::create([
                'order_id' => $order->getKey(),
                'status' => $order->status,
                'note' => 'Stripe refund created but local refund status update failed: ' . $e->getMessage(),
                'changed_by' => $request->user()?->getKey(),
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'status' => $order->status,
            'note' => 'Stripe refund created: ' . ($refund->id ?? '-') . ' (status: ' . ($refund->status ?? '-') . ').',
            'changed_by' => $request->user()?->getKey(),
        ]);

        return back()->with('success', 'Refund requested in Stripe: ' . $refund->id);
    }
}
