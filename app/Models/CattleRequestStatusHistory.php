<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CattleRequestStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'cattle_request_id',
        'status',
        'note',
        'changed_by',
    ];

    public function cattleRequest(): BelongsTo
    {
        return $this->belongsTo(CattleRequest::class, 'cattle_request_id', 'id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'user_id');
    }
}

