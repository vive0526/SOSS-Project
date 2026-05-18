<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->string('status', 20)->default('pending')->index();
            $table->string('carrier', 50)->nullable()->index();
            $table->string('tracking_number', 120)->nullable()->index();
            $table->timestamp('status_event_at')->nullable()->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')
                ->references('order_id')
                ->on('orders')
                ->cascadeOnDelete();
        });

        // Backfill a single "primary" shipment per order from legacy order-level shipment fields.
        DB::table('orders')
            ->select(['order_id', 'shipment_status', 'tracking_number', 'shipping_confirmed_at'])
            ->orderBy('order_id')
            ->chunk(500, function ($orders) {
                $now = now();
                $rows = [];

                foreach ($orders as $order) {
                    $status = (string) ($order->shipment_status ?? 'pending');
                    if ($status === '') {
                        $status = 'pending';
                    }

                    $statusEventAt = $order->shipping_confirmed_at;
                    $shippedAt = null;
                    $deliveredAt = null;

                    if (in_array($status, ['shipped', 'delivered'], true)) {
                        $shippedAt = $order->shipping_confirmed_at;
                        $statusEventAt = $statusEventAt ?: $now;
                    }

                    if ($status === 'delivered') {
                        $deliveredAt = $statusEventAt ?: $now;
                    }

                    $rows[] = [
                        'order_id' => $order->order_id,
                        'status' => $status,
                        'carrier' => null,
                        'tracking_number' => $order->tracking_number,
                        'status_event_at' => $statusEventAt,
                        'shipped_at' => $shippedAt,
                        'delivered_at' => $deliveredAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('shipments')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
