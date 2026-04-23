<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            if ($driver === 'sqlite' && app()->environment('testing')) {
                return;
            }

            throw new RuntimeException('This migration currently supports MySQL only.');
        }

        $usersMap = DB::table('users')->pluck('user_id', 'id')->all();
        $productsMap = DB::table('products')->pluck('product_id', 'id')->all();
        $ordersMap = DB::table('orders')->pluck('order_id', 'id')->all();

        $this->migrateUsersCreatedBy($usersMap);
        $this->migrateProductsUserId($usersMap);
        $this->migrateOrdersUserId($usersMap);
        $this->migrateInventoryMovements($productsMap, $usersMap);
        $this->migrateOrderItems($ordersMap, $productsMap);
        $this->migrateOrderStatusHistories($ordersMap, $usersMap);
        $this->migrateProductImages($productsMap);
        $this->migrateSessionsUserId($usersMap);
    }

    public function down(): void
    {
        throw new RuntimeException('Down migration is not supported for this key conversion. Restore from backup if needed.');
    }

    private function migrateUsersCreatedBy(array $usersMap): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->string('created_by_new', 16)->nullable()->after('created_by');
        });

        DB::table('users')
            ->select('id', 'created_by')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($usersMap) {
                foreach ($rows as $row) {
                    $newValue = $usersMap[$row->created_by] ?? null;
                    if ($newValue === null) {
                        throw new RuntimeException('Missing user_id mapping for created_by user ' . $row->created_by);
                    }
                    DB::table('users')->where('id', $row->id)->update(['created_by_new' => $newValue]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });

        DB::statement("ALTER TABLE `users` CHANGE `created_by_new` `created_by` VARCHAR(16) NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    private function migrateProductsUserId(array $usersMap): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->string('user_id_new', 16)->after('user_id');
        });

        DB::table('products')
            ->select('id', 'user_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($usersMap) {
                foreach ($rows as $row) {
                    $newValue = $usersMap[$row->user_id] ?? null;
                    if ($newValue === null) {
                        throw new RuntimeException('Missing user_id mapping for product user_id ' . $row->user_id);
                    }
                    DB::table('products')->where('id', $row->id)->update(['user_id_new' => $newValue]);
                }
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        DB::statement("ALTER TABLE `products` CHANGE `user_id_new` `user_id` VARCHAR(16) NOT NULL");

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users');
        });
    }

    private function migrateOrdersUserId(array $usersMap): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->string('user_id_new', 16)->after('user_id');
        });

        DB::table('orders')
            ->select('id', 'user_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($usersMap) {
                foreach ($rows as $row) {
                    $newValue = $usersMap[$row->user_id] ?? null;
                    if ($newValue === null) {
                        throw new RuntimeException('Missing user_id mapping for order user_id ' . $row->user_id);
                    }
                    DB::table('orders')->where('id', $row->id)->update(['user_id_new' => $newValue]);
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        DB::statement("ALTER TABLE `orders` CHANGE `user_id_new` `user_id` VARCHAR(16) NOT NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users');
        });
    }

    private function migrateInventoryMovements(array $productsMap, array $usersMap): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['user_id']);
            $table->string('product_id_new', 16)->after('product_id');
            $table->string('user_id_new', 16)->after('user_id');
        });

        DB::table('inventory_movements')
            ->select('id', 'product_id', 'user_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($productsMap, $usersMap) {
                foreach ($rows as $row) {
                    $newProduct = $productsMap[$row->product_id] ?? null;
                    $newUser = $usersMap[$row->user_id] ?? null;

                    if ($newProduct === null) {
                        throw new RuntimeException('Missing product_id mapping for inventory movement product_id ' . $row->product_id);
                    }
                    if ($newUser === null) {
                        throw new RuntimeException('Missing user_id mapping for inventory movement user_id ' . $row->user_id);
                    }

                    DB::table('inventory_movements')->where('id', $row->id)->update([
                        'product_id_new' => $newProduct,
                        'user_id_new' => $newUser,
                    ]);
                }
            });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('product_id');
            $table->dropColumn('user_id');
        });

        DB::statement("ALTER TABLE `inventory_movements` CHANGE `product_id_new` `product_id` VARCHAR(16) NOT NULL");
        DB::statement("ALTER TABLE `inventory_movements` CHANGE `user_id_new` `user_id` VARCHAR(16) NOT NULL");

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    private function migrateOrderItems(array $ordersMap, array $productsMap): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['product_id']);
            $table->string('order_id_new', 16)->after('order_id');
            $table->string('product_id_new', 16)->after('product_id');
        });

        DB::table('order_items')
            ->select('id', 'order_id', 'product_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($ordersMap, $productsMap) {
                foreach ($rows as $row) {
                    $newOrder = $ordersMap[$row->order_id] ?? null;
                    $newProduct = $productsMap[$row->product_id] ?? null;

                    if ($newOrder === null) {
                        throw new RuntimeException('Missing order_id mapping for order item order_id ' . $row->order_id);
                    }
                    if ($newProduct === null) {
                        throw new RuntimeException('Missing product_id mapping for order item product_id ' . $row->product_id);
                    }

                    DB::table('order_items')->where('id', $row->id)->update([
                        'order_id_new' => $newOrder,
                        'product_id_new' => $newProduct,
                    ]);
                }
            });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('order_id');
            $table->dropColumn('product_id');
        });

        DB::statement("ALTER TABLE `order_items` CHANGE `order_id_new` `order_id` VARCHAR(16) NOT NULL");
        DB::statement("ALTER TABLE `order_items` CHANGE `product_id_new` `product_id` VARCHAR(16) NOT NULL");

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products');
        });
    }

    private function migrateOrderStatusHistories(array $ordersMap, array $usersMap): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['changed_by']);
            $table->string('order_id_new', 16)->after('order_id');
            $table->string('changed_by_new', 16)->nullable()->after('changed_by');
        });

        DB::table('order_status_histories')
            ->select('id', 'order_id', 'changed_by')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($ordersMap, $usersMap) {
                foreach ($rows as $row) {
                    $newOrder = $ordersMap[$row->order_id] ?? null;
                    if ($newOrder === null) {
                        throw new RuntimeException('Missing order_id mapping for status history order_id ' . $row->order_id);
                    }

                    $newUser = null;
                    if ($row->changed_by !== null) {
                        $newUser = $usersMap[$row->changed_by] ?? null;
                        if ($newUser === null) {
                            throw new RuntimeException('Missing user_id mapping for status history changed_by ' . $row->changed_by);
                        }
                    }

                    DB::table('order_status_histories')->where('id', $row->id)->update([
                        'order_id_new' => $newOrder,
                        'changed_by_new' => $newUser,
                    ]);
                }
            });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropColumn('order_id');
            $table->dropColumn('changed_by');
        });

        DB::statement("ALTER TABLE `order_status_histories` CHANGE `order_id_new` `order_id` VARCHAR(16) NOT NULL");
        DB::statement("ALTER TABLE `order_status_histories` CHANGE `changed_by_new` `changed_by` VARCHAR(16) NULL");

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('changed_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    private function migrateProductImages(array $productsMap): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->string('product_id_new', 16)->after('product_id');
        });

        DB::table('product_images')
            ->select('id', 'product_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($productsMap) {
                foreach ($rows as $row) {
                    $newProduct = $productsMap[$row->product_id] ?? null;
                    if ($newProduct === null) {
                        throw new RuntimeException('Missing product_id mapping for product image product_id ' . $row->product_id);
                    }
                    DB::table('product_images')->where('id', $row->id)->update([
                        'product_id_new' => $newProduct,
                    ]);
                }
            });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });

        DB::statement("ALTER TABLE `product_images` CHANGE `product_id_new` `product_id` VARCHAR(16) NOT NULL");

        Schema::table('product_images', function (Blueprint $table) {
            $table->foreign('product_id')->references('product_id')->on('products');
        });
    }

    private function migrateSessionsUserId(array $usersMap): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id_new', 16)->nullable()->after('user_id');
        });

        DB::table('sessions')
            ->select('id', 'user_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($usersMap) {
                foreach ($rows as $row) {
                    $newUser = $usersMap[$row->user_id] ?? null;
                    if ($newUser === null) {
                        continue;
                    }
                    DB::table('sessions')->where('id', $row->id)->update(['user_id_new' => $newUser]);
                }
            });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        DB::statement("ALTER TABLE `sessions` CHANGE `user_id_new` `user_id` VARCHAR(16) NULL");
        DB::statement("ALTER TABLE `sessions` ADD INDEX `sessions_user_id_index` (`user_id`)");
    }
};
