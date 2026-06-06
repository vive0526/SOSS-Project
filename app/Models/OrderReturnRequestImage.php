<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderReturnRequestImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_return_request_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sort_order',
    ];

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(OrderReturnRequest::class, 'order_return_request_id', 'id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
