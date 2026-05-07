<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_last_failed_at')->nullable()->after('payment_status')->index();
            $table->string('payment_last_failure_reason', 80)->nullable()->after('payment_last_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_last_failed_at']);
            $table->dropColumn(['payment_last_failed_at', 'payment_last_failure_reason']);
        });
    }
};

