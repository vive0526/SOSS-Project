<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StripeReservationExpiryService
{
    public function __construct(private readonly OrderStateEngine $orderStateEngine)
    {
    }

    public function expireDueReservationsBestEffort(?Carbon $now = null): int
    {
        $now = $now ?: now();

        $cacheKey = 'stripe_reservations:expire_due:cooldown';
        if (!Cache::add($cacheKey, 1, 55)) {
            return 0;
        }

        return $this->expireDueReservations($now);
    }

    public function expireDueReservations(?Carbon $now = null): int
    {
        $now = $now ?: now();
        $expired = 0;

        $orderIds = Order::query()
            ->whereIn('payment_method', ['stripe_card', 'stripe_fpx'])
            ->whereNull('payment_verified_at')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', $now)
            ->orderBy('created_at')
            ->limit(200)
            ->pluck('order_id')
            ->all();

        foreach ($orderIds as $orderId) {
            $didExpire = DB::transaction(function () use ($orderId, $now) {
                /** @var \App\Models\Order|null $order */
                $order = Order::query()
                    ->where('order_id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    return false;
                }

                if ($order->status === 'cancelled' || $order->payment_verified_at) {
                    return false;
                }

                if (!$order->reservation_expires_at || $order->reservation_expires_at->greaterThan($now)) {
                    return false;
                }

                $order->load('items.product');

                $productIds = collect($order->items)->pluck('product_id')->filter()->unique()->values()->all();
                $products = Product::query()
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                if ($order->reserved_at) {
                    foreach ($order->items as $item) {
                        $productId = (string) ($item->product_id ?? '');
                        /** @var \App\Models\Product|null $product */
                        $product = $productId !== '' ? $products->get($productId) : null;

                        if (!$product && $item->product) {
                            $product = $item->product;
                        }

                        if (!$product) {
                            continue;
                        }

                        $qty = (int) $item->quantity;
                        $currentReserved = (int) ($product->reserved_quantity ?? 0);
                        $product->reserved_quantity = max(0, $currentReserved - $qty);

                        $maintenanceYear = $item->maintenance_year !== null ? (int) $item->maintenance_year : null;
                        if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                            $reservedMap = $product->maintenance_reserved_quantities ?? [];
                            $currentYearReserved = (int) ($reservedMap[$maintenanceYear] ?? $reservedMap[(string) $maintenanceYear] ?? 0);
                            $reservedMap[$maintenanceYear] = max(0, $currentYearReserved - $qty);
                            $product->maintenance_reserved_quantities = $reservedMap;
                        }
                        $product->save();
                    }
                }

                $this->orderStateEngine->transitionPaymentStatusLocked($order, 'unpaid', 'reservation_expired', true);
                $this->orderStateEngine->cancelOrderLocked(
                    $order,
                    'Reservation expired (5 minutes).',
                    'Reservation expired after 5 minutes; stock released.',
                    null
                );

                return true;
            });

            if ($didExpire) {
                $expired++;
            }
        }

        return $expired;
    }
}
