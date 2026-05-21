<?php

use App\Support\MalaysiaStates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('state_key', 80)->unique();
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $free = ['sabah', 'sarawak', 'wp_labuan'];

        $rows = [];
        foreach (MalaysiaStates::keys() as $stateKey) {
            $rows[] = [
                'state_key' => $stateKey,
                'shipping_fee' => in_array($stateKey, $free, true) ? 0 : 5,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('shipping_rates')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};

