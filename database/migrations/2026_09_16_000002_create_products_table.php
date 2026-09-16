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
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('barcode')->index(); // Stored as string to preserve leading zeros
            $table->string('sku')->nullable()->index();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('unit')->default('1 unit'); // e.g., 1 L, 500g, 1 pack
            $table->decimal('wholesale_cost', 10, 2)->default(0.00);
            $table->decimal('retail_price', 10, 2)->default(0.00);
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('minimum_stock_level')->default(5);
            $table->integer('maximum_stock_level')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
