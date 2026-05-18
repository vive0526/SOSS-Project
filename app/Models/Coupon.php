<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_PERCENT = 'percent';
    public const TYPE_AMOUNT = 'amount';

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_subtotal',
        'starts_at',
        'ends_at',
        'max_total_claims',
        'max_claims_per_user',
        'max_total_redemptions',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_subtotal' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_total_claims' => 'integer',
        'max_claims_per_user' => 'integer',
        'max_total_redemptions' => 'integer',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(CouponClaim::class);
    }
}

