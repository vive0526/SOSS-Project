<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal_amount', 10, 2)->default(0)->after('status');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal_amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('line_subtotal', 10, 2)->default(0)->after('unit_price');
            $table->decimal('line_discount', 10, 2)->default(0)->after('line_subtotal');
            $table->decimal('line_tax', 10, 2)->default(0)->after('line_discount');
            $table->decimal('line_total', 10, 2)->default(0)->after('line_tax');
        });

        Schema::create('order_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 16);
            $table->string('tax_name', 120);
            $table->decimal('tax_rate', 7, 4);
            $table->decimal('taxable_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->index(['order_id']);
        });

        // Backfill existing orders/items:
        // - Historically, `orders.total_amount` is treated as the grand total (items + shipping).
        // - We default discounts/taxes to zero and derive `subtotal_amount = total_amount - shipping_fee`.
        DB::statement('UPDATE `orders` SET `subtotal_amount` = GREATEST(0, (`total_amount` - COALESCE(`shipping_fee`, 0))), `discount_amount` = 0, `tax_amount` = 0');
        DB::statement('UPDATE `order_items` SET `line_subtotal` = COALESCE(`total_price`, 0), `line_discount` = 0, `line_tax` = 0, `line_total` = COALESCE(`total_price`, 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tax_lines');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['line_subtotal', 'line_discount', 'line_tax', 'line_total']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal_amount', 'discount_amount', 'tax_amount']);
        });
    }
};

