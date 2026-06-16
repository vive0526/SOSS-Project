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
        $secretKey = trim((string) config('services.stripe.secret'));
        if ($secretKey === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        if (!str_starts_with($secretKey, 'sk_')) {
            throw new \RuntimeException('Stripe secret key is invalid; configure STRIPE_SECRET_KEY with an sk_ key.');
        }

        if (!in_array($paymentMethodType, ['card', 'fpx'], true)) {
            throw new \RuntimeException('Unsupported Stripe payment method.');
        }

        $order->loadMissing('items');

        $currency = 'myr';
        $expectedTotal = $this->toStripeAmount($order->total_amount);
        if ($expectedTotal <= 0) {
            throw new \RuntimeException('Order total must be greater than zero for Stripe checkout.');
        }

        $lineItems = $this->buildLineItems($order, $currency);
        $computedTotal = array_sum(array_map(
            fn (array $item) => (int) Arr::get($item, 'price_data.unit_amount', 0) * (int) Arr::get($item, 'quantity', 1),
            $lineItems
        ));

        if ($lineItems === [] || $computedTotal !== $expectedTotal) {
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
        $metadata = $this->buildMetadata($order);

        try {
            return $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => [$paymentMethodType],
                'line_items' => $lineItems,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $order->getKey(),
                'metadata' => $metadata,
                'payment_intent_data' => [
                    'metadata' => $metadata,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Failed creating Stripe Checkout Session.', [
                'order_id' => $order->getKey(),
                'payment_method_type' => $paymentMethodType,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
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
            $quantity = (int) $item->quantity;
            if ($unitAmount <= 0 || $quantity <= 0) {
                continue;
            }

            $lineItems[] = [
                'quantity' => $quantity,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => trim((string) $item->product_name) !== ''
                            ? (string) $item->product_name
                            : 'Order Item',
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

    /**
     * Stripe metadata values must be strings.
     *
     * @return array<string, string>
     */
    private function buildMetadata(Order $order): array
    {
        return [
            'order_id' => (string) $order->getKey(),
            'order_number' => (string) $order->order_number,
            'user_id' => (string) $order->user_id,
        ];
    }

    private function toStripeAmount($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
