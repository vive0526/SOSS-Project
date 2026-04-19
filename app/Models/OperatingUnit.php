<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatingUnit extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'address',
        'manager',
        'region_id',
        'status',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
