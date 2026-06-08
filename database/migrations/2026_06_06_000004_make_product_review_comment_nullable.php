<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_reviews')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `product_reviews` MODIFY `comment` TEXT NULL');
            return;
        }

        Schema::table('product_reviews', function ($table) {
            $table->text('comment')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_reviews')) {
            return;
        }

        ProductReviewCommentBackfill::run();

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `product_reviews` MODIFY `comment` TEXT NOT NULL');
            return;
        }

        Schema::table('product_reviews', function ($table) {
            $table->text('comment')->nullable(false)->change();
        });
    }
};

class ProductReviewCommentBackfill
{
    public static function run(): void
    {
        DB::table('product_reviews')
            ->whereNull('comment')
            ->update(['comment' => '']);
    }
}
