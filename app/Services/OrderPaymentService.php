<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class OrderPaymentService
{
    public function verifyPayment(Order $order, ?string $verifiedByUserId = null, ?string $paymentReference = null, ?string $note = null): bool
    {
        if ($order->payment_verified_at) {
            return false;
        }

        $order->loadMissing('items.product');

        $insufficient = [];
        foreach ($order->items as $item) {
            if (!$item->product) {
                $insufficient[] = $item->product_name . ' (missing product)';
                continue;
            }

            if ($item->product->stock_quantity < $item->quantity) {
                $insufficient[] = $item->product->name;
            }
        }

        if (!empty($insufficient)) {
            if ($paymentReference) {
                $order->payment_reference = $paymentReference;
                $order->save();
            }

            OrderStatusHistory::create([
                'order_id' => $order->getKey(),
                'status' => $order->status,
                'note' => 'Payment received but stock is insufficient: ' . implode(', ', $insufficient) . '.',
                'changed_by' => $verifiedByUserId,
            ]);

            return false;
        }

        DB::transaction(function () use ($order, $verifiedByUserId, $paymentReference, $note) {
            $order->payment_verified_at = now();

            if ($paymentReference) {
                $order->payment_reference = $paymentReference;
            }

            $order->save();

            $movementUserId = $verifiedByUserId ?: $order->user_id;

            foreach ($order->items as $item) {
                if (!$item->product) {
                    continue;
                }

                $product = $item->product;
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock - $item->quantity;

                $product->stock_quantity = $newStock;
                $product->save();

                InventoryMovement::create([
                    'product_id' => $product->getKey(),
                    'user_id' => $movementUserId,
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => 'Order ' . $order->order_number . ' payment verified.',
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->getKey(),
                'status' => $order->status,
                'note' => $note ?: 'Payment verified.',
                'changed_by' => $verifiedByUserId,
            ]);
        });

        return true;
    }
}
