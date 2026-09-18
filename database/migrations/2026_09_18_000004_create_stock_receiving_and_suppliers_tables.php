<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Suppliers Table
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name')->index();
                $table->string('phone')->nullable()->index();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('tax_number')->nullable();
                $table->string('status')->default('active')->index(); // active, inactive
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Stock Receipts (Goods Received Notes - GRNs)
        if (!Schema::hasTable('stock_receipts')) {
            Schema::create('stock_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('grn_number')->unique(); // e.g. GRN-000001
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->string('supplier_name_snapshot')->nullable()->index();
                $table->string('supplier_invoice_number')->nullable()->index();
                $table->date('invoice_date')->nullable();
                $table->string('purchase_reference')->nullable()->index();
                $table->date('receiving_date')->index();
                $table->string('status')->default('draft')->index(); // draft, pending_review, received, cancelled
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('discount', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('other_charges', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->string('payment_status')->default('unpaid')->index(); // unpaid, partially_paid, paid
                $table->text('notes')->nullable();
                $table->string('attachment_url')->nullable();
                $table->string('created_by')->nullable();
                $table->string('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index(['supplier_id', 'status']);
                $table->index(['receiving_date', 'status']);
            });
        }

        // 3. Stock Receipt Items Table
        if (!Schema::hasTable('stock_receipt_items')) {
            Schema::create('stock_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_receipt_id')->constrained('stock_receipts')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('barcode')->nullable()->index();
                $table->string('product_name');
                $table->integer('quantity_expected')->default(0);
                $table->integer('quantity_received')->default(1);
                $table->integer('quantity_damaged')->default(0);
                $table->integer('quantity_sellable')->default(1);
                $table->decimal('unit_cost', 10, 2)->default(0.00);
                $table->decimal('discount', 10, 2)->default(0.00);
                $table->decimal('tax_amount', 10, 2)->default(0.00);
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->string('batch_number')->nullable()->index();
                $table->date('expiry_date')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['stock_receipt_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipt_items');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('suppliers');
    }
};
