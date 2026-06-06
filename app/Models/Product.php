<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, HasPrefixedPrimaryKey;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const PREFIXED_PRIMARY_KEY_COUNTER = 'products';

    protected $fillable = [
        'name',
        'slug',
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
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'requires_maintenance' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'maintenance_years' => 'integer',
        'maintenance_prices' => 'array',
        'maintenance_stocks' => 'array',
        'maintenance_reserved_quantities' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if (!empty($product->slug)) {
                return;
            }

            $base = Str::slug((string) ($product->name ?? 'product'));
            $base = $base !== '' ? $base : 'product';

            $candidate = $base;
            $query = self::query()->where('slug', $candidate);
            if ($product->exists) {
                $query->where('product_id', '!=', (string) $product->getKey());
            }

            if ($query->exists()) {
                $suffix = $product->getKey() ?: uniqid();
                $candidate = $base . '-' . Str::slug((string) $suffix);
            }

            $product->slug = $candidate;
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id', 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id', 'product_id')->latest();
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'product_id')
            ->orderBy('sort_order')
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true)
            ?? $this->images->first();
    }

    public function imagePaths(): Collection
    {
        $paths = $this->relationLoaded('images')
            ? $this->images->pluck('path')
            : $this->images()->pluck('path');

        if (!empty($this->image)) {
            $paths->prepend($this->image);
        }

        return $paths->filter(fn ($path) => is_string($path) && $path !== '')->unique()->values();
    }

    public function primaryImagePath(): ?string
    {
        $primary = $this->relationLoaded('images') ? $this->primaryImage()?->path : $this->images()->where('is_primary', true)->value('path');

        return $primary ?: ($this->image ?: null);
    }

    public function primaryImageUrl(): ?string
    {
        $path = $this->primaryImagePath();
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path);
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
