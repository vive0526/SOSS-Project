<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table) {
                $table->id();
                $table->string('product_id', 16);
                $table->string('path');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->index(['product_id', 'sort_order']);
                $table->index(['product_id', 'is_primary']);
                $table->foreign('product_id')
                    ->references('product_id')
                    ->on('products')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });
        } else {
            Schema::table('product_images', function (Blueprint $table) {
                if (!Schema::hasColumn('product_images', 'product_id')) {
                    $table->string('product_id', 16)->after('id');
                }
                if (!Schema::hasColumn('product_images', 'path')) {
                    $table->string('path')->after('product_id');
                }
                if (!Schema::hasColumn('product_images', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('path');
                }
                if (!Schema::hasColumn('product_images', 'is_primary')) {
                    $table->boolean('is_primary')->default(false)->after('sort_order');
                }
            });

            if (Schema::hasColumn('product_images', 'image_path')) {
                Schema::table('product_images', function (Blueprint $table) {
                    $table->dropColumn('image_path');
                });
            }
        }

        $this->backfillPrimaryImagesFromLegacyColumn();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }

    private function backfillPrimaryImagesFromLegacyColumn(): void
    {
        if (!Schema::hasColumn('products', 'image')) {
            return;
        }

        $keyColumn = Schema::hasColumn('products', 'id') ? 'id' : 'product_id';

        DB::table('products')
            ->select([$keyColumn, 'product_id', 'image'])
            ->whereNotNull('image')
            ->orderBy($keyColumn)
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $path = (string) ($row->image ?? '');
                    if ($path === '') {
                        continue;
                    }

                    $exists = DB::table('product_images')
                        ->where('product_id', $row->product_id)
                        ->where('path', $path)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('product_images')->insert([
                        'product_id' => $row->product_id,
                        'path' => $path,
                        'sort_order' => 0,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
