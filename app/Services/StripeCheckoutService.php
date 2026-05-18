<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function createCheckoutSession(Order $order, string $paymentMethodType, string $successUrl, string $cancelUrl): Session
    {
        $secretKey = (string) config('services.stripe.secret');
        if ($secretKey === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $order->loadMissing('items');

        $currency = 'myr';
        $expectedTotal = $this->toStripeAmount($order->total_amount);

        $lineItems = $this->buildLineItems($order, $currency);
        $computedTotal = array_sum(array_map(
            fn (array $item) => (int) Arr::get($item, 'price_data.unit_amount', 0) * (int) Arr::get($item, 'quantity', 1),
            $lineItems
        ));

        if ($computedTotal !== $expectedTotal) {
            Log::warning('Stripe line item total mismatch; falling back to single line item.', [
                'order_id' => $order->getKey(),
                'expected_total' => $expectedTotal,
                'computed_total' => $computedTotal,
            ]);

            $lineItems = [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $expectedTotal,
                    'product_data' => [
                        'name' => 'Order ' . $order->order_number,
                    ],
                ],
            ]];
        }

        $stripe = new StripeClient($secretKey);

        try {
            return $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => [$paymentMethodType],
                'line_items' => $lineItems,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $order->getKey(),
                'metadata' => [
                    'order_id' => $order->getKey(),
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'order_id' => $order->getKey(),
                        'order_number' => $order->order_number,
                        'user_id' => $order->user_id,
                    ],
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Failed creating Stripe Checkout Session.', [
                'order_id' => $order->getKey(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(Order $order, string $currency): array
    {
        $lineItems = [];

        foreach ($order->items as $item) {
            $unitAmount = $this->toStripeAmount($item->unit_price);
            if ($unitAmount <= 0) {
                continue;
            }

            $lineItems[] = [
                'quantity' => (int) $item->quantity,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => (string) $item->product_name,
                    ],
                ],
            ];
        }

        $shippingFee = $this->toStripeAmount($order->shipping_fee ?? 0);
        if ($shippingFee > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $shippingFee,
                    'product_data' => [
                        'name' => 'Shipping Fee',
                    ],
                ],
            ];
        }

        $taxAmount = $this->toStripeAmount($order->tax_amount ?? 0);
        if ($taxAmount > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $taxAmount,
                    'product_data' => [
                        'name' => 'Tax (6%)',
                    ],
                ],
            ];
        }

        return $lineItems;
    }

    private function toStripeAmount($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
