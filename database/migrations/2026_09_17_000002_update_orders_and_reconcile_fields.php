<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'customer_name_snapshot')) {
                $table->string('customer_name_snapshot')->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('orders', 'customer_phone_snapshot')) {
                $table->string('customer_phone_snapshot')->nullable()->after('customer_name_snapshot');
            }
            if (!Schema::hasColumn('orders', 'customer_notes_snapshot')) {
                $table->text('customer_notes_snapshot')->nullable()->after('customer_address');
            }
            if (!Schema::hasColumn('orders', 'whatsapp_status')) {
                $table->string('whatsapp_status')->default('prepared')->after('status'); // prepared, opened, sent, failed
            }
            if (!Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('whatsapp_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name_snapshot',
                'customer_phone_snapshot',
                'customer_notes_snapshot',
                'whatsapp_status',
                'idempotency_key',
            ]);
        });
    }
};
