<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Additive columns to products (expiry_date, supplier_name)
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->index();
            }
            if (!Schema::hasColumn('products', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->index();
            }
        });

        // 2. Additive column to stock_movements (unit_cost)
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'unit_cost')) {
                $table->decimal('unit_cost', 10, 2)->nullable();
            }
        });

        // 3. Physical Stock Counts table
        if (!Schema::hasTable('stock_counts')) {
            Schema::create('stock_counts', function (Blueprint $table) {
                $table->id();
                $table->string('count_number')->unique(); // e.g. SC-000001
                $table->string('status')->default('draft')->index(); // draft, counting, pending_review, approved, cancelled
                $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
                $table->integer('total_items')->default(0);
                $table->integer('variance_count')->default(0);
                $table->decimal('variance_value', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->string('created_by')->nullable();
                $table->string('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        // 4. Stock Count Items table
        if (!Schema::hasTable('stock_count_items')) {
            Schema::create('stock_count_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->integer('system_quantity')->default(0);
                $table->integer('physical_quantity')->nullable();
                $table->integer('variance')->default(0);
                $table->decimal('unit_cost', 10, 2)->default(0.00);
                $table->decimal('variance_value', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['stock_count_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'supplier_name')) {
                $table->dropColumn('supplier_name');
            }
            if (Schema::hasColumn('products', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }
        });
    }
};
