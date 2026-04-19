<?php

namespace App\Models\Concerns;

use App\Services\PrefixedIdService;
use RuntimeException;

trait HasPrefixedPrimaryKey
{
    public static function bootHasPrefixedPrimaryKey(): void
    {
        static::creating(function ($model): void {
            $keyName = $model->getKeyName();
            if (!empty($model->{$keyName})) {
                return;
            }

            $counterKey = $model->prefixedPrimaryKeyCounterKey();
            $model->{$keyName} = app(PrefixedIdService::class)->next($counterKey);
        });
    }

    protected function prefixedPrimaryKeyCounterKey(): string
    {
        if (!defined('static::PREFIXED_PRIMARY_KEY_COUNTER')) {
            throw new RuntimeException(static::class . ' must define PREFIXED_PRIMARY_KEY_COUNTER to use HasPrefixedPrimaryKey.');
        }

        /** @var string $value */
        $value = constant('static::PREFIXED_PRIMARY_KEY_COUNTER');

        return $value;
    }
}

