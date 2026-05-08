<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefund extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending',
        'requires_action',
        'succeeded',
        'failed',
        'canceled',
    ];

    protected $fillable = [
        'order_id',
        'provider',
        'provider_refund_id',
        'provider_payment_intent_id',
        'amount_cents',
        'currency',
        'reason',
        'status',
        'requested_by',
        'processed_at',
        'provider_payload',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'processed_at' => 'datetime',
        'provider_payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }
}

