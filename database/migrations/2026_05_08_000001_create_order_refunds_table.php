<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();

            $table->string('order_id', 16)->index();

            $table->string('provider', 20)->default('stripe');
            $table->string('provider_refund_id', 64);
            $table->string('provider_payment_intent_id', 64)->nullable()->index();

            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('myr');

            $table->string('reason', 40)->nullable();
            $table->string('status', 20)->index();

            $table->string('requested_by', 16)->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();

            $table->json('provider_payload')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_refund_id']);
            $table->index(['order_id', 'status']);

            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('requested_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};

