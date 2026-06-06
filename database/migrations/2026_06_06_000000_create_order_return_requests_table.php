<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 16)->index();
            $table->string('user_id', 16)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason', 40);
            $table->text('customer_note')->nullable();
            $table->unsignedBigInteger('requested_amount_cents');
            $table->string('currency', 3)->default('myr');
            $table->text('staff_note')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('handled_by', 16)->nullable()->index();
            $table->timestamp('handled_at')->nullable()->index();
            $table->timestamp('return_received_at')->nullable()->index();
            $table->timestamp('stock_returned_at')->nullable()->index();
            $table->timestamp('refunded_at')->nullable()->index();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['user_id', 'status']);

            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('handled_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_requests');
    }
};
