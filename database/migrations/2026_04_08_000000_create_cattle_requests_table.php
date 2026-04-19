<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle_requests', function (Blueprint $table) {
            $table->id();

            $table->string('product_id', 16);
            $table->string('user_id', 16);

            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 20)->default('pending')->index();

            $table->text('customer_note')->nullable();
            $table->text('staff_note')->nullable();
            $table->string('rejection_reason', 255)->nullable();

            $table->string('handled_by', 16)->nullable();
            $table->dateTime('handled_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->foreign('product_id')
                ->references('product_id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('handled_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattle_requests');
    }
};

