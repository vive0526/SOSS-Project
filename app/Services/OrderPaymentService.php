<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderPaymentService
{
    public function __construct(private readonly OrderStateEngine $orderStateEngine)
    {
    }

    public function verifyPayment(Order $order, ?string $verifiedByUserId = null, ?string $paymentReference = null, ?string $note = null): bool
    {
        return (bool) DB::transaction(function () use ($order, $verifiedByUserId, $paymentReference, $note) {
            /** @var \App\Models\Order|null $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedOrder || $lockedOrder->payment_verified_at) {
                return false;
            }

            $lockedOrder->load(['items.product']);

            $isStripe = in_array($lockedOrder->payment_method, ['stripe_card', 'stripe_fpx'], true);
            if ($isStripe && $lockedOrder->reservation_expires_at && now()->greaterThan($lockedOrder->reservation_expires_at)) {
                if ($paymentReference) {
                    $lockedOrder->payment_reference = $paymentReference;
                    $lockedOrder->save();
                }

                OrderStatusHistory::create([
                    'order_id' => $lockedOrder->getKey(),
                    'status' => $lockedOrder->status,
                    'note' => 'Payment received but reservation expired (5 minutes). Manual review/refund may be required.',
                    'changed_by' => $verifiedByUserId,
                ]);

                return false;
            }

            $productIds = collect($lockedOrder->items)->pluck('product_id')->filter()->unique()->values()->all();
            $products = Product::query()
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $usesReservation = $lockedOrder->reserved_at !== null;
            $insufficient = [];

            foreach ($lockedOrder->items as $item) {
                $productId = (string) ($item->product_id ?? '');
                /** @var \App\Models\Product|null $product */
                $product = $productId !== '' ? $products->get($productId) : null;

                if (!$product) {
                    if ($item->product) {
                        $product = $item->product;
                    }
                }

                if (!$product) {
                    $insufficient[] = $item->product_name . ' (missing product)';
                    continue;
                }

                $requestedQty = (int) $item->quantity;

                if ((int) $product->stock_quantity < $requestedQty) {
                    $insufficient[] = $product->name;
                    continue;
                }

                if ($usesReservation && (int) ($product->reserved_quantity ?? 0) < $requestedQty) {
                    $insufficient[] = $product->name;
                    continue;
                }
            }

            if (!empty($insufficient)) {
                if ($paymentReference) {
                    $lockedOrder->payment_reference = $paymentReference;
                    $lockedOrder->save();
                }

                OrderStatusHistory::create([
                    'order_id' => $lockedOrder->getKey(),
                    'status' => $lockedOrder->status,
                    'note' => 'Payment received but stock is insufficient: ' . implode(', ', $insufficient) . '.',
                    'changed_by' => $verifiedByUserId,
                ]);

                return false;
            }

            if ($paymentReference) {
                $lockedOrder->payment_reference = $paymentReference;
            }

            $this->orderStateEngine->transitionPaymentStatusLocked($lockedOrder, 'paid', null, true);

            $movementUserId = $verifiedByUserId ?: $lockedOrder->user_id;

            foreach ($lockedOrder->items as $item) {
                $productId = (string) ($item->product_id ?? '');
                /** @var \App\Models\Product|null $product */
                $product = $productId !== '' ? $products->get($productId) : null;

                if (!$product && $item->product) {
                    $product = $item->product;
                }

                if (!$product) {
                    continue;
                }

                $previousStock = (int) $product->stock_quantity;
                $requestedQty = (int) $item->quantity;
                $newStock = $previousStock - $requestedQty;

                if ($usesReservation) {
                    $product->reserved_quantity = (int) ($product->reserved_quantity ?? 0) - $requestedQty;
                }

                $product->stock_quantity = $newStock;
                $product->save();

                InventoryMovement::create([
                    'product_id' => $product->getKey(),
                    'user_id' => $movementUserId,
                    'type' => 'out',
                    'quantity' => $requestedQty,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => 'Order ' . $lockedOrder->order_number . ' payment verified.',
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->getKey(),
                'status' => $lockedOrder->status,
                'note' => $note ?: 'Payment verified.',
                'changed_by' => $verifiedByUserId,
            ]);

            return true;
        });
    }
}
