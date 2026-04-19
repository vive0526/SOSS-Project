<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // Product name
            $table->text('description');  // Product description
            $table->decimal('price', 10, 2);  // Price of product
            $table->integer('stock_quantity');  // Quantity in stock
            $table->string('image')->nullable();  // Image path
            $table->foreignId('category_id')->nullable()->constrained('categories');  // Category for grouping products
            $table->foreignId('user_id')->constrained('users'); // Staff who created the product
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
 