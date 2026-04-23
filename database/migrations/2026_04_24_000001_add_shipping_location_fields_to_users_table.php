<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('shipping_city', 120)->nullable()->after('shipping_address');
            $table->string('shipping_state', 120)->nullable()->after('shipping_city');
            $table->string('shipping_postcode', 30)->nullable()->after('shipping_state');
            $table->string('shipping_country', 120)->nullable()->after('shipping_postcode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_city',
                'shipping_state',
                'shipping_postcode',
                'shipping_country',
            ]);
        });
    }
};

