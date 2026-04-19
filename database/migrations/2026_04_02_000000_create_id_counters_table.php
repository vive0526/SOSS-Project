<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_counters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('prefix', 8);
            $table->unsignedBigInteger('next_number')->default(1001);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_counters');
    }
};

