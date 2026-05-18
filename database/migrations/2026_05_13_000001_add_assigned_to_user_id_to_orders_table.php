<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('assigned_to_user_id', 16)
                ->nullable()
                ->after('assigned_to');

            $table->index('assigned_to_user_id');
            $table->foreign('assigned_to_user_id')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropIndex(['assigned_to_user_id']);
            $table->dropColumn('assigned_to_user_id');
        });
    }
};

