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
        'maintenance_stocks',
        'stock_quantity',
        'maintenance_reserved_quantities',
        'reorder_level',
        'image',
        'category_id',
        'user_id',
    ];

    protected $casts = [
        'requires_maintenance' => 'boolean',
        'maintenance_years' => 'integer',
        'maintenance_prices' => 'array',
        'maintenance_stocks' => 'array',
        'maintenance_reserved_quantities' => 'array',
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

    public function maintenanceStockForYear(int $year): int
    {
        $stocks = $this->maintenance_stocks ?? [];
        $value = $stocks[$year] ?? $stocks[(string) $year] ?? 0;

        return max(0, (int) $value);
    }

    public function reservedMaintenanceForYear(int $year): int
    {
        $reserved = $this->maintenance_reserved_quantities ?? [];
        $value = $reserved[$year] ?? $reserved[(string) $year] ?? 0;

        return max(0, (int) $value);
    }

    public function availableMaintenanceStock(int $year): int
    {
        $stock = $this->maintenanceStockForYear($year);
        $reserved = $this->reservedMaintenanceForYear($year);

        return max(0, $stock - $reserved);
    }
}
