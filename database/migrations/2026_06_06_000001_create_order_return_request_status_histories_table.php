<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_return_request_status_histories')) {
            return;
        }

        Schema::create('order_return_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_return_request_id');
            $table->foreign('order_return_request_id', 'orrsh_order_return_request_id_fk')
                  ->references('id')->on('order_return_requests')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->text('note')->nullable();
            $table->string('changed_by', 16)->nullable()->index();
            $table->timestamps();

            $table->foreign('changed_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_request_status_histories');
    }
};
