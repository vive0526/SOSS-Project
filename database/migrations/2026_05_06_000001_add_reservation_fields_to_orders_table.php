<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reserved_at')->nullable()->after('payment_verified_at');
            $table->timestamp('reservation_expires_at')->nullable()->after('reserved_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['reserved_at', 'reservation_expires_at']);
        });
    }
};

