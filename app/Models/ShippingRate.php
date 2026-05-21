<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $fillable = [
        'state_key',
        'shipping_fee',
        'active',
    ];

    protected $casts = [
        'shipping_fee' => 'decimal:2',
        'active' => 'boolean',
    ];
}

