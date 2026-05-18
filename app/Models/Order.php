<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPrimaryKey;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasPrefixedPrimaryKey;

    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const PREFIXED_PRIMARY_KEY_COUNTER = 'orders';

    public const STATUSES = [
        'pending',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
    ];

    /**
     * Allowed forward-only fulfillment transitions for staff/admin updates.
     *
     * Cancellation and reopening are handled via dedicated actions.
     *
     * @var array<string, array<int, string>>
     */
    public const STATUS_TRANSITIONS = [
        'pending' => ['processing'],
        'processing' => ['shipped'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public const SHIPMENT_STATUSES = [
        'pending',
        'shipped',
        'delivered',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid',
        'pending',
        'paid',
        'refund_pending',
        'refunded',
        'partial_refund',
    ];

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'shipment_status',
        'tracking_number',
        'shipping_confirmed_at',
        'subtotal_amount',
        'discount_amount',
        'coupon_id',
        'coupon_code',
        'order_discount_type',
        'order_discount_value',
        'tax_amount',
        'tax_rate',
        'total_amount',
        'shipping_fee',
        'payment_method',
        'payment_reference',
        'payment_status',
        'payment_last_failed_at',
        'payment_last_failure_reason',
        'payment_verified_at',
        'reserved_at',
        'reservation_expires_at',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'assigned_to',
        'assigned_to_user_id',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected $casts = [
        'payment_verified_at' => 'datetime',
        'payment_last_failed_at' => 'datetime',
        'reserved_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
        'shipping_confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'order_discount_value' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'total_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function refundableTotalCents(): int
    {
        return (int) round(((float) ($this->total_amount ?? 0)) * 100);
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (!empty($order->order_number)) {
                return;
            }

            do {
                $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
            } while (self::where('order_number', $orderNumber)->exists());

            $order->order_number = $orderNumber;
        });

        static::created(function (Order $order) {
            if (!Schema::hasTable('shipments')) {
                return;
            }

            if ($order->shipments()->exists()) {
                return;
            }

            $status = (string) ($order->shipment_status ?: 'pending');
            if (!in_array($status, Shipment::STATUSES, true)) {
                $status = 'pending';
            }

            $statusEventAt = $order->shipping_confirmed_at;
            $shippedAt = null;
            $deliveredAt = null;

            if (in_array($status, ['shipped', 'delivered'], true)) {
                $shippedAt = $order->shipping_confirmed_at ?: now();
                $statusEventAt = $statusEventAt ?: $shippedAt;
            }

            if ($status === 'delivered') {
                $deliveredAt = $statusEventAt ?: now();
            }

            $order->shipments()->create([
                'status' => $status,
                'tracking_number' => $order->tracking_number,
                'status_event_at' => $statusEventAt,
                'shipped_at' => $shippedAt,
                'delivered_at' => $deliveredAt,
            ]);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id', 'order_id')->orderBy('id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id', 'order_id')->orderBy('created_at');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class, 'order_id', 'order_id')->orderBy('created_at');
    }

    public function isPaymentVerified(): bool
    {
        return $this->payment_verified_at !== null;
    }

    public function isPaymentAcceptableForFulfillment(): bool
    {
        if ($this->payment_method === 'cash_on_delivery') {
            return true;
        }

        return $this->payment_status === 'paid';
    }

    public function canTransitionStatusTo(string $nextStatus): bool
    {
        if (!in_array($nextStatus, self::STATUSES, true)) {
            return false;
        }

        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];

        return in_array($nextStatus, $allowed, true);
    }

    public function getFulfillmentStatusAttribute(): ?string
    {
        return $this->status;
    }

    public function setFulfillmentStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = $value;
    }
}
