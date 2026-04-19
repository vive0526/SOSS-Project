<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $staffId = DB::table('users')
            ->where('role', 'staff')
            ->orderBy('id')
            ->value('id');

        if (!$staffId) {
            return;
        }

        DB::table('users')
            ->where('role', 'customer')
            ->whereNull('created_by')
            ->update(['created_by' => $staffId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $staffId = DB::table('users')
            ->where('role', 'staff')
            ->orderBy('id')
            ->value('id');

        if (!$staffId) {
            return;
        }

        DB::table('users')
            ->where('role', 'customer')
            ->where('created_by', $staffId)
            ->update(['created_by' => null]);
    }
};
