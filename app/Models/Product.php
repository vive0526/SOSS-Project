<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, HasPrefixedPrimaryKey;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const PREFIXED_PRIMARY_KEY_COUNTER = 'products';

    protected $fillable = [
        'name',
        'description',
        'price',
        'product_type',
        'requires_maintenance',
        'maintenance_years',
        'maintenance_prices',
        'stock_quantity',
        'reorder_level',
        'image',
        'category_id',
        'user_id',
    ];

    protected $casts = [
        'requires_maintenance' => 'boolean',
        'maintenance_years' => 'integer',
        'maintenance_prices' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id', 'product_id');
    }

    public function availableStock(): int
    {
        $stock = (int) ($this->stock_quantity ?? 0);
        $reserved = (int) ($this->reserved_quantity ?? 0);

        return max(0, $stock - $reserved);
    }
}
