<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $afterColumn = Schema::hasColumn('products', 'product_type') ? 'product_type' : null;

        Schema::table('products', function (Blueprint $table) use ($afterColumn) {
            $column = $table->boolean('requires_maintenance')->default(false);
            if ($afterColumn) {
                $column->after($afterColumn);
            }
        });

        DB::table('products')
            ->where('category_id', 3)
            ->update(['requires_maintenance' => 1]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('requires_maintenance');
        });
    }
};
