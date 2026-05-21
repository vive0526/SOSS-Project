<?php

use App\Support\MalaysiaStates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 32)->index();
            $table->string('label', 60)->nullable();
            $table->string('recipient_name', 120);
            $table->string('phone', 20);
            $table->string('address_line', 255);
            $table->string('city', 120);
            $table->string('state_key', 80);
            $table->string('postcode', 30);
            $table->string('country', 120)->default('Malaysia');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};

