<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory;

    public const STATUSES = [
        'approved',
        'hidden',
    ];

    protected $fillable = [
        'product_id',
        'order_id',
        'order_item_id',
        'user_id',
        'rating',
        'comment',
        'status',
        'is_dummy',
        'moderated_at',
        'moderated_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_dummy' => 'boolean',
        'moderated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by', 'user_id');
    }
}
