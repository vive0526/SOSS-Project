<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();

            $table->string('discount_type', 16); // percent|amount
            $table->decimal('discount_value', 10, 2); // percent (e.g. 10.00) or amount (e.g. 25.00)

            $table->decimal('min_subtotal', 10, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('max_total_claims')->nullable();
            $table->unsignedInteger('max_claims_per_user')->nullable();
            $table->unsignedInteger('max_total_redemptions')->nullable();

            $table->string('status', 16)->default('active'); // active|inactive
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

