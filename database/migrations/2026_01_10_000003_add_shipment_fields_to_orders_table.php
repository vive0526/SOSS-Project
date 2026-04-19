<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipment_status')->default('pending')->after('status');
            $table->string('tracking_number')->nullable()->after('shipment_status');
            $table->timestamp('shipping_confirmed_at')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipment_status', 'tracking_number', 'shipping_confirmed_at']);
        });
    }
};
