<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $dbName = DB::getDatabaseName();

        $row = DB::selectOne(
            'SELECT 1 AS one_col
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$dbName, $table, $indexName]
        );

        return $row !== null;
    }

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!$this->indexExists('orders', 'idx_orders_created_at')) {
                $table->index('created_at', 'idx_orders_created_at');
            }

            if (!$this->indexExists('orders', 'idx_orders_status_created_at')) {
                $table->index(['status', 'created_at'], 'idx_orders_status_created_at');
            }

            if (!$this->indexExists('orders', 'idx_orders_shipment_status_created_at')) {
                $table->index(['shipment_status', 'created_at'], 'idx_orders_shipment_status_created_at');
            }

            if (!$this->indexExists('orders', 'idx_orders_payment_status_created_at')) {
                $table->index(['payment_status', 'created_at'], 'idx_orders_payment_status_created_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'idx_users_name')) {
                $table->index('name', 'idx_users_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if ($this->indexExists('orders', 'idx_orders_payment_status_created_at')) {
                $table->dropIndex('idx_orders_payment_status_created_at');
            }

            if ($this->indexExists('orders', 'idx_orders_shipment_status_created_at')) {
                $table->dropIndex('idx_orders_shipment_status_created_at');
            }

            if ($this->indexExists('orders', 'idx_orders_status_created_at')) {
                $table->dropIndex('idx_orders_status_created_at');
            }

            if ($this->indexExists('orders', 'idx_orders_created_at')) {
                $table->dropIndex('idx_orders_created_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_name')) {
                $table->dropIndex('idx_users_name');
            }
        });
    }
};

