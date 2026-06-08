<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_reviews')) {
            return;
        }

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 16)->index();
            $table->string('order_id', 16)->nullable()->index();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('user_id', 16)->nullable()->index();
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->string('status', 20)->default('approved')->index();
            $table->boolean('is_dummy')->default(false)->index();
            $table->timestamp('moderated_at')->nullable()->index();
            $table->string('moderated_by', 16)->nullable()->index();
            $table->timestamps();

            $table->unique('order_item_id', 'uq_product_reviews_order_item');
            $table->index(['product_id', 'status'], 'idx_product_reviews_product_status');

            $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
            $table->foreign('order_id')->references('order_id')->on('orders')->nullOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('moderated_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
