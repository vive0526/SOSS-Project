<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_id', 16)->nullable()->unique()->after('id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('product_id', 16)->nullable()->unique()->after('id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_id', 16)->nullable()->unique()->after('id');
        });

        $this->backfillPrefixedIds('users', 'user_id', 'USR', 1001);
        $this->backfillPrefixedIds('products', 'product_id', 'PRD', 1001);
        $this->backfillPrefixedIds('orders', 'order_id', 'ORD', 1001);

        $this->syncCounterFromTable('users', 'user_id', 'USR');
        $this->syncCounterFromTable('products', 'product_id', 'PRD');
        $this->syncCounterFromTable('orders', 'order_id', 'ORD');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropColumn('order_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    private function backfillPrefixedIds(string $tableName, string $column, string $prefix, int $startNumber): void
    {
        $nextNumber = $startNumber;

        DB::table($tableName)
            ->select('id')
            ->whereNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($tableName, $column, $prefix, &$nextNumber) {
                foreach ($rows as $row) {
                    DB::table($tableName)->where('id', $row->id)->update([
                        $column => $prefix . $nextNumber,
                    ]);
                    $nextNumber++;
                }
            });
    }

    private function syncCounterFromTable(string $tableName, string $column, string $prefix): void
    {
        $driver = DB::getDriverName();
        $startPosition = strlen($prefix) + 1;

        $castType = match ($driver) {
            'mysql' => 'UNSIGNED',
            'sqlite' => 'INTEGER',
            default => null,
        };

        if ($castType === null) {
            throw new RuntimeException("Unsupported database driver [{$driver}] for prefixed-id counter sync.");
        }

        $maxNumber = DB::table($tableName)
            ->whereNotNull($column)
            ->selectRaw('MAX(CAST(SUBSTR(' . $column . ', ' . $startPosition . ') AS ' . $castType . ')) as max_num')
            ->value('max_num');

        $nextNumber = ((int) $maxNumber) > 0 ? ((int) $maxNumber) + 1 : 1001;

        DB::table('id_counters')->upsert([[
            'key' => $tableName,
            'prefix' => $prefix,
            'next_number' => $nextNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['key'], ['prefix', 'next_number', 'updated_at']);
    }
};
