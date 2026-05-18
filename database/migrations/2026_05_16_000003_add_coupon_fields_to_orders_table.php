<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('discount_amount')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 32)->nullable()->after('coupon_id');
            $table->string('order_discount_type', 16)->nullable()->after('coupon_code'); // percent|amount
            $table->decimal('order_discount_value', 10, 2)->nullable()->after('order_discount_type');
            $table->decimal('tax_rate', 7, 4)->default(0.0600)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'order_discount_type', 'order_discount_value', 'tax_rate']);
        });
    }
};

