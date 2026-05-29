<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('user_id');
            }
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('products', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });

        if (Schema::hasColumn('products', 'slug')) {
            $driver = DB::getDriverName();
            $hasIndex = false;

            if ($driver === 'mysql') {
                $indexes = DB::select("SHOW INDEX FROM `products` WHERE Column_name = 'slug'");
                $hasIndex = !empty($indexes);
            } elseif ($driver === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list('products')");
                foreach ($indexes as $index) {
                    if (!isset($index->name)) {
                        continue;
                    }
                    $cols = DB::select("PRAGMA index_info('{$index->name}')");
                    foreach ($cols as $col) {
                        if (($col->name ?? null) === 'slug') {
                            $hasIndex = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$hasIndex) {
                Schema::table('products', function (Blueprint $table) {
                    $table->unique('slug');
                });
            }
        }

        $this->backfillProductSlugs();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'is_featured', 'is_active']);
        });
    }

    private function backfillProductSlugs(): void
    {
        if (!Schema::hasColumn('products', 'slug')) {
            return;
        }

        $keyColumn = Schema::hasColumn('products', 'id') ? 'id' : 'product_id';

        DB::table('products')
            ->select([$keyColumn, 'product_id', 'name', 'slug'])
            ->whereNull('slug')
            ->orderBy($keyColumn)
            ->chunk(200, function ($rows) use ($keyColumn) {
                foreach ($rows as $row) {
                    $base = Str::slug((string) ($row->name ?? 'product'));
                    $base = $base !== '' ? $base : 'product';

                    $candidate = $base;
                    if (DB::table('products')->where('slug', $candidate)->exists()) {
                        $suffix = $row->product_id ?: $row->{$keyColumn};
                        $candidate = $base . '-' . Str::slug((string) $suffix);
                    }

                    DB::table('products')->where($keyColumn, $row->{$keyColumn})->update([
                        'slug' => $candidate,
                    ]);
                }
            });
    }
};
