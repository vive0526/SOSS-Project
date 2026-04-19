<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public const SHIPMENT_STATUSES = [
        'pending',
        'shipped',
        'delivered',
    ];

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'shipment_status',
        'tracking_number',
        'shipping_confirmed_at',
        'total_amount',
        'shipping_fee',
        'payment_method',
        'payment_reference',
        'payment_verified_at',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'assigned_to',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected $casts = [
        'payment_verified_at' => 'datetime',
        'shipping_confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
    ];

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
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id', 'order_id')->orderBy('created_at');
    }

    public function isPaymentVerified(): bool
    {
        return $this->payment_verified_at !== null;
    }
}
