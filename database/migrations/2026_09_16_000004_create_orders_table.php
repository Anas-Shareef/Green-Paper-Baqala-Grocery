<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('delivery_charge', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('internal_delivery_cost', 10, 2)->default(0.00);
            $table->decimal('product_cost', 10, 2)->default(0.00);
            $table->decimal('gross_profit', 10, 2)->default(0.00);
            $table->decimal('net_profit', 10, 2)->default(0.00);
            $table->string('payment_method')->default('Cash'); // Cash, Card, UPI
            $table->string('payment_status')->default('pending'); // pending, paid
            $table->string('status')->default('pending'); // pending, accepted, preparing, out_for_delivery, delivered, cancelled
            $table->string('order_source')->default('PWA'); // POS, PWA, Phone
            $table->foreignId('delivery_staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('customer_villa')->nullable();
            $table->text('customer_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
