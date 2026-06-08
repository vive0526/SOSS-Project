<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_return_request_images')) {
            if (!$this->indexExists('order_return_request_images', 'idx_return_req_images_sort')) {
                Schema::table('order_return_request_images', function (Blueprint $table) {
                    $table->index(['order_return_request_id', 'sort_order'], 'idx_return_req_images_sort');
                });
            }

            return;
        }

        Schema::create('order_return_request_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_request_id')->constrained('order_return_requests')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['order_return_request_id', 'sort_order'], 'idx_return_req_images_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_request_images');
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );

        return $rows !== [];
    }
};
