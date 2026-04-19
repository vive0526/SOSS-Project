<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CattleRequest extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending',
        'approved',
        'rejected',
        'completed',
    ];

    protected $fillable = [
        'product_id',
        'user_id',
        'phone',
        'quantity',
        'purpose',
        'preferred_date',
        'status',
        'customer_note',
        'staff_note',
        'rejection_reason',
        'handled_by',
        'handled_at',
        'completed_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'completed_at' => 'datetime',
        'preferred_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
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
        return $this->hasMany(CattleRequestStatusHistory::class)->orderBy('created_at');
    }
}
