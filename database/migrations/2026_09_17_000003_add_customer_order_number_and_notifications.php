<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add customer_order_number to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'customer_order_number')) {
                $table->unsignedInteger('customer_order_number')->default(1)->after('order_number');
            }
        });

        // 2. Create admin_notifications table
        if (!Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('type')->default('new_order');
                $table->string('title');
                $table->text('message');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('order_number')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        // 3. Create admin_push_subscriptions table
        if (!Schema::hasTable('admin_push_subscriptions')) {
            Schema::create('admin_push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->text('endpoint');
                $table->text('public_key')->nullable();
                $table->text('auth_token')->nullable();
                $table->string('device_name')->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'customer_order_number')) {
                $table->dropColumn('customer_order_number');
            }
        });

        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('admin_push_subscriptions');
    }
};
