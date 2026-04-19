<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdCounter extends Model
{
    protected $fillable = [
        'key',
        'prefix',
        'next_number',
    ];

    protected $casts = [
        'next_number' => 'integer',
    ];
}

