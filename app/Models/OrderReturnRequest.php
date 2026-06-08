<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturnRequest extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending',
        'approved',
        'rejected',
        'return_received',
        'refunded',
        'cancelled',
    ];

    public const REASONS = [
        'wrong_item' => 'Wrong item received',
        'damaged' => 'Item damaged',
        'quality_issue' => 'Quality issue',
        'other' => 'Other',
    ];

    public const EVIDENCE_REQUIRED_REASONS = [
        'wrong_item',
        'damaged',
        'quality_issue',
    ];

    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'reason',
        'customer_note',
        'requested_amount_cents',
        'currency',
        'staff_note',
        'rejection_reason',
        'handled_by',
        'handled_at',
        'return_received_at',
        'stock_returned_at',
        'refunded_at',
    ];

    protected $casts = [
        'requested_amount_cents' => 'integer',
        'handled_at' => 'datetime',
        'return_received_at' => 'datetime',
        'stock_returned_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by', 'user_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderReturnRequestStatusHistory::class)->orderBy('created_at');
    }

    public function evidenceImages(): HasMany
    {
        return $this->hasMany(OrderReturnRequestImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function amountRm(): float
    {
        return ((int) $this->requested_amount_cents) / 100;
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucwords(str_replace('_', ' ', (string) $this->reason));
    }
}
