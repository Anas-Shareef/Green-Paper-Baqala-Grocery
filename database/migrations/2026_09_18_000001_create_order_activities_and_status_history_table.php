<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add fulfillment columns to orders table safely
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'ready_at')) {
                $table->timestamp('ready_at')->nullable()->after('preparing_at');
            }
            if (!Schema::hasColumn('orders', 'failed_delivery_at')) {
                $table->timestamp('failed_delivery_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('orders', 'failed_delivery_reason')) {
                $table->string('failed_delivery_reason')->nullable()->after('failed_delivery_at');
            }
            if (!Schema::hasColumn('orders', 'cod_collected_at')) {
                $table->timestamp('cod_collected_at')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'cod_collected_by')) {
                $table->string('cod_collected_by')->nullable()->after('cod_collected_at');
            }
            if (!Schema::hasColumn('orders', 'cod_collected_amount')) {
                $table->decimal('cod_collected_amount', 10, 2)->default(0.00)->after('cod_collected_by');
            }
            if (!Schema::hasColumn('orders', 'cod_difference_reason')) {
                $table->text('cod_difference_reason')->nullable()->after('cod_collected_amount');
            }
            if (!Schema::hasColumn('orders', 'priority')) {
                $table->string('priority')->default('normal')->after('status'); // normal, high, urgent
            }
            if (!Schema::hasColumn('orders', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('orders', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable()->after('internal_notes');
            }
            if (!Schema::hasColumn('orders', 'picking_status')) {
                $table->string('picking_status')->default('pending')->after('priority'); // pending, in_progress, completed
            }
        });

        // 2. Add item-level picking tracking to order_items safely
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'picked_quantity')) {
                $table->integer('picked_quantity')->default(0)->after('quantity');
            }
            if (!Schema::hasColumn('order_items', 'item_status')) {
                $table->string('item_status')->default('pending')->after('picked_quantity'); // pending, picked, unavailable, substituted
            }
        });

        // 3. Create order_activities table safely
        if (!Schema::hasTable('order_activities')) {
            Schema::create('order_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
                $table->string('actor_type')->nullable(); // admin, staff, driver, customer, system
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->string('activity_type'); // order_created, confirmed, preparing, ready, assigned, dispatched, delivered, cod_collected, cancelled, failed
                $table->text('description');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'created_at']);
            });
        }

        // 4. Create order_status_history table safely
        if (!Schema::hasTable('order_status_history')) {
            Schema::create('order_status_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->string('changed_by')->nullable();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_activities');

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'item_status')) {
                $table->dropColumn('item_status');
            }
            if (Schema::hasColumn('order_items', 'picked_quantity')) {
                $table->dropColumn('picked_quantity');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $cols = [
                'ready_at',
                'failed_delivery_at',
                'failed_delivery_reason',
                'cod_collected_at',
                'cod_collected_by',
                'cod_collected_amount',
                'cod_difference_reason',
                'priority',
                'internal_notes',
                'delivery_notes',
                'picking_status',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
