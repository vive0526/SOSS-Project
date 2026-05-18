<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending',
        'shipped',
        'delivered',
    ];

    /**
     * Allowed forward-only shipment transitions.
     *
     * @var array<string, array<int, string>>
     */
    public const STATUS_TRANSITIONS = [
        'pending' => ['shipped'],
        'shipped' => ['delivered'],
        'delivered' => [],
    ];

    protected $fillable = [
        'order_id',
        'status',
        'carrier',
        'tracking_number',
        'status_event_at',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'status_event_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function canTransitionStatusTo(string $nextStatus): bool
    {
        if (!in_array($nextStatus, self::STATUSES, true)) {
            return false;
        }

        $current = (string) ($this->status ?: 'pending');
        $allowed = self::STATUS_TRANSITIONS[$current] ?? [];

        return in_array($nextStatus, $allowed, true);
    }
}

