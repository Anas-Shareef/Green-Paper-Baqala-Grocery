<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('label')->default('Home'); // Home, Work, Other
            $table->string('villa_number')->nullable();
            $table->string('building_number')->nullable();
            $table->text('street_address')->nullable();
            $table->string('zone')->nullable();
            $table->string('landmark')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Migrate existing customer address fields into customer_addresses as defaults
        $customers = DB::table('customers')->get();
        foreach ($customers as $customer) {
            if (!empty($customer->address) || !empty($customer->villa_number)) {
                DB::table('customer_addresses')->insert([
                    'customer_id' => $customer->id,
                    'label' => 'Home',
                    'villa_number' => $customer->villa_number,
                    'street_address' => $customer->address,
                    'zone' => $customer->zone,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
