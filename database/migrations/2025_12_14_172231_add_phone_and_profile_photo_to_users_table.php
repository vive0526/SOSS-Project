<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add phone number column (nullable)
            $table->string('phone')->nullable()->after('email');

            // Add profile photo column (nullable)
            $table->string('profile_photo')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the columns in case of rollback
            $table->dropColumn(['phone', 'profile_photo']);
        });
    }
};
