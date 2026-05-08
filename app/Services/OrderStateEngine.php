<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class OrderStateEngine
{
    /**
     * Payment status transitions. This keeps `payment_status` as the source of truth
     * while still allowing retries (e.g. pending -> unpaid).
     *
     * @var array<string, array<int, string>>
     */
    private const PAYMENT_TRANSITIONS = [
        'unpaid' => ['pending', 'paid'],
        'pending' => ['unpaid', 'paid'],
        'paid' => ['refund_pending', 'refunded', 'partial_refund'],
        'refund_pending' => ['paid', 'refunded', 'partial_refund'],
        'partial_refund' => ['refund_pending', 'refunded'],
        'refunded' => [],
    ];

    private const REFUND_PENDING_STATUSES = [
        'pending',
        'requires_action',
    ];

    private const REFUND_SUCCESS_STATUS = 'succeeded';

    /**
     * Transition fulfillment status (orders.status) with validation + history logging.
     */
    public function transitionOrderStatus(Order $order, string $nextStatus, ?string $note = null, ?string $changedByUserId = null): void
    {
        DB::transaction(function () use ($order, $nextStatus, $note, $changedByUserId) {
            /** @var \App\Models\Order|null $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException('Order not found.');
            }

            $this->transitionOrderStatusLocked($locked, $nextStatus, $note, $changedByUserId);
        });
    }

    public function transitionOrderStatusLocked(Order $lockedOrder, string $nextStatus, ?string $note = null, ?string $changedByUserId = null): void
    {
        if ($nextStatus === 'cancelled') {
            throw new \DomainException('Use cancelOrder for cancellations.');
        }

        if ($lockedOrder->status === $nextStatus) {
            return;
        }

        if (!$lockedOrder->canTransitionStatusTo($nextStatus)) {
            throw new \DomainException('Invalid order status transition: ' . $lockedOrder->status . ' → ' . $nextStatus . '.');
        }

        $requiresPayment = in_array($nextStatus, ['processing', 'shipped', 'delivered'], true);
        if ($requiresPayment && !$lockedOrder->isPaymentAcceptableForFulfillment()) {
            throw new \DomainException('Payment must be paid before moving this order to ' . $nextStatus . ' (COD orders are allowed).');
        }

        $lockedOrder->status = $nextStatus;
        $lockedOrder->cancelled_at = null;
        $lockedOrder->cancelled_reason = null;
        $lockedOrder->save();

        OrderStatusHistory::create([
            'order_id' => $lockedOrder->getKey(),
            'status' => $nextStatus,
            'note' => $note,
            'changed_by' => $changedByUserId,
        ]);
    }

    /**
     * Cancel order with validation + history logging.
     * Rule: only pending/processing, and only before shipment starts.
     */
    public function cancelOrder(Order $order, string $cancelReason, ?string $historyNote = null, ?string $changedByUserId = null): void
    {
        DB::transaction(function () use ($order, $cancelReason, $historyNote, $changedByUserId) {
            /** @var \App\Models\Order|null $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException('Order not found.');
            }

            $this->cancelOrderLocked($locked, $cancelReason, $historyNote, $changedByUserId);
        });
    }

    public function cancelOrderLocked(Order $lockedOrder, string $cancelReason, ?string $historyNote = null, ?string $changedByUserId = null): void
    {
        if ($lockedOrder->status === 'cancelled') {
            return;
        }

        if (!in_array($lockedOrder->status, ['pending', 'processing'], true)) {
            throw new \DomainException('Only pending/processing orders can be cancelled.');
        }

        if ($lockedOrder->shipment_status !== 'pending') {
            throw new \DomainException('Cannot cancel after shipment has started.');
        }

        $lockedOrder->status = 'cancelled';
        $lockedOrder->cancelled_at = now();
        $lockedOrder->cancelled_reason = $cancelReason;
        $lockedOrder->save();

        OrderStatusHistory::create([
            'order_id' => $lockedOrder->getKey(),
            'status' => 'cancelled',
            'note' => $historyNote ?: ('Cancelled: ' . $cancelReason),
            'changed_by' => $changedByUserId,
        ]);
    }

    /**
     * Reopen a cancelled order back to pending (before shipment starts).
     */
    public function reopenOrder(Order $order, ?string $historyNote = null, ?string $changedByUserId = null): void
    {
        DB::transaction(function () use ($order, $historyNote, $changedByUserId) {
            /** @var \App\Models\Order|null $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException('Order not found.');
            }

            $this->reopenOrderLocked($locked, $historyNote, $changedByUserId);
        });
    }

    public function reopenOrderLocked(Order $lockedOrder, ?string $historyNote = null, ?string $changedByUserId = null): void
    {
        if ($lockedOrder->status !== 'cancelled') {
            throw new \DomainException('Only cancelled orders can be reopened.');
        }

        if ($lockedOrder->shipment_status !== 'pending') {
            throw new \DomainException('Cannot reopen after shipment has started.');
        }

        $lockedOrder->status = 'pending';
        $lockedOrder->cancelled_at = null;
        $lockedOrder->cancelled_reason = null;
        $lockedOrder->shipment_status = 'pending';
        $lockedOrder->save();

        OrderStatusHistory::create([
            'order_id' => $lockedOrder->getKey(),
            'status' => 'pending',
            'note' => $historyNote ?: 'Order reopened.',
            'changed_by' => $changedByUserId,
        ]);
    }

    /**
     * Payment state transition with validation.
     */
    public function transitionPaymentStatus(Order $order, string $nextStatus, ?string $failureReason = null, bool $clearFailure = true): void
    {
        DB::transaction(function () use ($order, $nextStatus, $failureReason, $clearFailure) {
            /** @var \App\Models\Order|null $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException('Order not found.');
            }

            $this->transitionPaymentStatusLocked($locked, $nextStatus, $failureReason, $clearFailure);
        });
    }

    /**
     * Recalculate payment_status for refund-related states by summing refund records.
     * This enables multiple partial refunds to correctly reach refunded.
     */
    public function recalculateRefundPaymentStatus(Order $order): void
    {
        DB::transaction(function () use ($order) {
            /** @var \App\Models\Order|null $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException('Order not found.');
            }

            $this->recalculateRefundPaymentStatusLocked($locked);
        });
    }

    public function recalculateRefundPaymentStatusLocked(Order $lockedOrder): void
    {
        $current = (string) ($lockedOrder->payment_status ?: 'unpaid');
        if (!in_array($current, ['paid', 'refund_pending', 'partial_refund', 'refunded'], true)) {
            return;
        }

        $refundableTotalCents = $this->getRefundableTotalCents($lockedOrder);

        $refundedSucceededCents = (int) OrderRefund::query()
            ->where('order_id', $lockedOrder->getKey())
            ->where('status', self::REFUND_SUCCESS_STATUS)
            ->sum('amount_cents');

        $hasPendingRefund = OrderRefund::query()
            ->where('order_id', $lockedOrder->getKey())
            ->whereIn('status', self::REFUND_PENDING_STATUSES)
            ->exists();

        $derived = self::deriveRefundPaymentStatus($current, $refundableTotalCents, $refundedSucceededCents, $hasPendingRefund);
        if ($derived === null || $derived === $current) {
            return;
        }

        $this->transitionPaymentStatusLocked($lockedOrder, $derived, null, true);
    }

    public static function deriveRefundPaymentStatus(string $currentStatus, int $refundableTotalCents, int $refundedSucceededCents, bool $hasPendingRefund): ?string
    {
        if (!in_array($currentStatus, ['paid', 'refund_pending', 'partial_refund', 'refunded'], true)) {
            return null;
        }

        if ($refundedSucceededCents > 0) {
            if ($refundableTotalCents > 0 && $refundedSucceededCents >= $refundableTotalCents) {
                return 'refunded';
            }

            return 'partial_refund';
        }

        if ($hasPendingRefund) {
            return 'refund_pending';
        }

        return 'paid';
    }

    private function getRefundableTotalCents(Order $order): int
    {
        $total = (float) ($order->total_amount ?? 0);
        $shippingFee = (float) ($order->shipping_fee ?? 0);

        return (int) round(($total + $shippingFee) * 100);
    }

    public function transitionPaymentStatusLocked(Order $lockedOrder, string $nextStatus, ?string $failureReason = null, bool $clearFailure = true): void
    {
        if (!in_array($nextStatus, Order::PAYMENT_STATUSES, true)) {
            throw new \DomainException('Invalid payment status: ' . $nextStatus . '.');
        }

        if ($lockedOrder->payment_status === $nextStatus) {
            $didChangeMeta = false;

            if ($clearFailure) {
                $lockedOrder->payment_last_failed_at = null;
                $lockedOrder->payment_last_failure_reason = null;
                $didChangeMeta = true;
            }

            if ($failureReason !== null && $failureReason !== '') {
                $lockedOrder->payment_last_failed_at = now();
                $lockedOrder->payment_last_failure_reason = $failureReason;
                $didChangeMeta = true;
            }

            if ($didChangeMeta) {
                $lockedOrder->save();
            }

            return;
        }

        $current = (string) ($lockedOrder->payment_status ?: 'unpaid');
        $allowed = self::PAYMENT_TRANSITIONS[$current] ?? [];

        if (!in_array($nextStatus, $allowed, true)) {
            throw new \DomainException('Invalid payment status transition: ' . $current . ' → ' . $nextStatus . '.');
        }

        $lockedOrder->payment_status = $nextStatus;

        if ($nextStatus === 'paid' && !$lockedOrder->payment_verified_at) {
            $lockedOrder->payment_verified_at = now();
        }

        if ($clearFailure) {
            $lockedOrder->payment_last_failed_at = null;
            $lockedOrder->payment_last_failure_reason = null;
        }

        if ($failureReason !== null && $failureReason !== '') {
            $lockedOrder->payment_last_failed_at = now();
            $lockedOrder->payment_last_failure_reason = $failureReason;
        }

        $lockedOrder->save();
    }
}
