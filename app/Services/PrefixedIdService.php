<?php

namespace App\Services;

use App\Models\IdCounter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrefixedIdService
{
    public function next(string $key): string
    {
        return DB::transaction(function () use ($key) {
            $counter = IdCounter::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                throw new RuntimeException("Missing id counter row for key: {$key}");
            }

            $number = (int) $counter->next_number;
            $counter->next_number = $number + 1;
            $counter->save();

            return (string) $counter->prefix . $number;
        });
    }
}

