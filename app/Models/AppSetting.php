<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getString(string $key, string $default = ''): string
    {
        $value = Cache::remember("app_settings:{$key}", 300, function () use ($key) {
            return static::query()->whereKey($key)->value('value');
        });

        $value = is_string($value) ? $value : null;

        return $value !== null ? $value : $default;
    }

    public static function getDecimal(string $key, float $default = 0.0): float
    {
        $raw = self::getString($key, (string) $default);
        $raw = trim($raw);

        if ($raw === '' || !is_numeric($raw)) {
            return $default;
        }

        return (float) $raw;
    }

    public static function putString(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_settings:{$key}");
    }
}

