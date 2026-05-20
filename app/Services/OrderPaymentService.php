<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderPaymentVerifiedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderPaymentService
{
    public function __construct(private readonly OrderStateEngine $orderStateEngine)
    {
    }

    public function verifyPayment(Order $order, ?string $verifiedByUserId = null, ?string $paymentReference = null, ?string $note = null): bool
    {
        $verified = (bool) DB::transaction(function () use ($order, $verifiedByUserId, $paymentReference, $note) {
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
                $maintenanceYear = $item->maintenance_year !== null ? (int) $item->maintenance_year : null;

                if ((int) $product->stock_quantity < $requestedQty) {
                    $insufficient[] = $product->name;
                    continue;
                }

                if ($usesReservation && (int) ($product->reserved_quantity ?? 0) < $requestedQty) {
                    $insufficient[] = $product->name;
                    continue;
                }

                if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                    $yearStock = $product->maintenanceStockForYear($maintenanceYear);
                    if ($yearStock < $requestedQty) {
                        $insufficient[] = $product->name . " (maintenance year {$maintenanceYear})";
                        continue;
                    }

                    if ($usesReservation && $product->reservedMaintenanceForYear($maintenanceYear) < $requestedQty) {
                        $insufficient[] = $product->name . " (maintenance year {$maintenanceYear})";
                        continue;
                    }
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
                $maintenanceYear = $item->maintenance_year !== null ? (int) $item->maintenance_year : null;

                if ($usesReservation) {
                    $product->reserved_quantity = (int) ($product->reserved_quantity ?? 0) - $requestedQty;
                }

                $product->stock_quantity = $newStock;

                if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                    $stocks = $product->maintenance_stocks ?? [];
                    $current = (int) ($stocks[$maintenanceYear] ?? $stocks[(string) $maintenanceYear] ?? 0);
                    $stocks[$maintenanceYear] = max(0, $current - $requestedQty);
                    $product->maintenance_stocks = $stocks;

                    if ($usesReservation) {
                        $reserved = $product->maintenance_reserved_quantities ?? [];
                        $currentReserved = (int) ($reserved[$maintenanceYear] ?? $reserved[(string) $maintenanceYear] ?? 0);
                        $reserved[$maintenanceYear] = max(0, $currentReserved - $requestedQty);
                        $product->maintenance_reserved_quantities = $reserved;
                    }
                }
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

        if ($verified) {
            try {
                $freshOrder = Order::query()
                    ->with('customer')
                    ->whereKey($order->getKey())
                    ->first();

                $customer = $freshOrder?->customer;
                if ($freshOrder && $customer) {
                    $isStripe = in_array((string) $freshOrder->payment_method, ['stripe_card', 'stripe_fpx'], true);
                    if ($isStripe) {
                        // For Stripe, only send the "order received" invoice after payment is verified.
                        $customer->notify(new OrderPlacedNotification($freshOrder));
                    } else {
                        $customer->notify(new OrderPaymentVerifiedNotification($freshOrder));
                    }
                }
            } catch (\Throwable $e) {
                // Notifications should never break the payment verification flow.
                Log::error('Order payment verified, but notification failed.', [
                    'order_id' => (string) $order->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $verified;
    }
}
