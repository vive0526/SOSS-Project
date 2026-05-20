<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('maintenance_stocks')->nullable()->after('maintenance_prices');
            $table->json('maintenance_reserved_quantities')->nullable()->after('reserved_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['maintenance_stocks', 'maintenance_reserved_quantities']);
        });
    }
};

