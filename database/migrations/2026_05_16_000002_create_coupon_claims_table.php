<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->string('user_id', 16);
            $table->string('order_id', 16)->nullable();
            $table->timestamp('claimed_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id')->references('order_id')->on('orders')->nullOnDelete();

            $table->unique(['coupon_id', 'user_id']);
            $table->index(['user_id', 'redeemed_at']);
            $table->index(['coupon_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_claims');
    }
};

