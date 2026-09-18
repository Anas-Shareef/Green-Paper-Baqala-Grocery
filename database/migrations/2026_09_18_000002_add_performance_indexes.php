<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Indexes for orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_idx');
            $table->index('created_at', 'orders_created_at_idx');
            $table->index('customer_id', 'orders_customer_id_idx');
            $table->index('payment_status', 'orders_payment_status_idx');
            $table->index('delivery_staff_id', 'orders_delivery_staff_id_idx');
            $table->index('order_source', 'orders_order_source_idx');
            $table->index(['status', 'created_at'], 'orders_status_created_at_idx');
        });

        // 2. Indexes for order_items table
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id', 'order_items_order_id_idx');
            $table->index('product_id', 'order_items_product_id_idx');
        });

        // 3. Indexes for products table
        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id', 'products_category_id_idx');
            $table->index(['status', 'category_id'], 'products_status_category_id_idx');
            $table->index('stock_quantity', 'products_stock_quantity_idx');
        });

        // 4. Indexes for expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('expense_date', 'expenses_expense_date_idx');
            $table->index('expense_category_id', 'expenses_expense_category_id_idx');
        });

        // 5. Indexes for customer_addresses table
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->index('customer_id', 'customer_addresses_customer_id_idx');
        });

        // 6. Indexes for customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->index('created_at', 'customers_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_created_at_idx');
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropIndex('customer_addresses_customer_id_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_expense_date_idx');
            $table->dropIndex('expenses_expense_category_id_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_id_idx');
            $table->dropIndex('products_status_category_id_idx');
            $table->dropIndex('products_stock_quantity_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_id_idx');
            $table->dropIndex('order_items_product_id_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_idx');
            $table->dropIndex('orders_created_at_idx');
            $table->dropIndex('orders_customer_id_idx');
            $table->dropIndex('orders_payment_status_idx');
            $table->dropIndex('orders_delivery_staff_id_idx');
            $table->dropIndex('orders_order_source_idx');
            $table->dropIndex('orders_status_created_at_idx');
        });
    }
};
