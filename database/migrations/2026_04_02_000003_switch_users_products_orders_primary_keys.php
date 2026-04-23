<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            if ($driver === 'sqlite' && app()->environment('testing')) {
                return;
            }

            throw new RuntimeException('This migration currently supports MySQL only.');
        }

        DB::statement('ALTER TABLE `users` DROP PRIMARY KEY, DROP COLUMN `id`, ADD PRIMARY KEY (`user_id`)');
        DB::statement('ALTER TABLE `products` DROP PRIMARY KEY, DROP COLUMN `id`, ADD PRIMARY KEY (`product_id`)');
        DB::statement('ALTER TABLE `orders` DROP PRIMARY KEY, DROP COLUMN `id`, ADD PRIMARY KEY (`order_id`)');
    }

    public function down(): void
    {
        throw new RuntimeException('Down migration is not supported for this primary key conversion. Restore from backup if needed.');
    }
};
