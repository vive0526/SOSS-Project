<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cattle_requests', function (Blueprint $table) {
            $table->string('phone', 60)->nullable()->after('user_id');
            $table->string('purpose', 30)->nullable()->after('quantity');
            $table->date('preferred_date')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('cattle_requests', function (Blueprint $table) {
            $table->dropColumn(['phone', 'purpose', 'preferred_date']);
        });
    }
};

