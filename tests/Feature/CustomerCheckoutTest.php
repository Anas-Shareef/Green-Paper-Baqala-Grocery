<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create([
            'name' => 'Fresh Produce',
            'slug' => 'fresh-produce',
            'status' => 'active',
        ]);

        Product::create([
            'category_id' => 1,
            'name' => 'Fresh Banana Pack',
            'sku' => 'BANANA-001',
            'barcode' => '6291000111222',
            'retail_price' => 15.00,
            'cost_price' => 8.00,
            'stock_quantity' => 100,
            'minimum_stock_level' => 10,
            'unit' => 'Pack',
            'status' => 'active',
        ]);
    }

    public function test_phone_number_normalization()
    {
        $this->assertEquals('+971501234567', PhoneNumberService::normalize('0501234567'));
        $this->assertEquals('+971501234567', PhoneNumberService::normalize('+971501234567'));
        $this->assertEquals('+971501234567', PhoneNumberService::normalize('971501234567'));
        $this->assertEquals('971501234567', PhoneNumberService::formatForWhatsApp('0501234567'));
    }

    public function test_customer_identification_endpoint()
    {
        // 1. Unrecognized phone
        $res1 = $this->postJson('/api/v1/customer/identify', [
            'phone' => '0509998877'
        ]);
        $res1->assertStatus(200);
        $res1->assertJsonPath('data.customer_exists', false);

        // 2. Create customer and recognize
        $customer = Customer::create([
            'name' => 'Salem Al Mansoori',
            'phone' => '+971509998877',
            'status' => 'active',
        ]);
        CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Home',
            'villa_number' => '45',
            'street_address' => 'Street 10, Zone B',
            'is_default' => true,
        ]);

        $res2 = $this->postJson('/api/v1/customer/identify', [
            'phone' => '0509998877'
        ]);
        $res2->assertStatus(200);
        $res2->assertJsonPath('data.customer_exists', true);
        $res2->assertJsonPath('data.customer.name', 'Salem Al Mansoori');
        $res2->assertJsonCount(1, 'data.addresses');
    }

    public function test_order_creation_transaction_and_whatsapp_url()
    {
        $payload = [
            'name' => 'Tariq Al Hammadi',
            'phone' => '0501112233',
            'villa_number' => 'Villa 88',
            'delivery_address' => 'Street 7, Zone C',
            'zone' => 'Zone C',
            'payment_method' => 'cash',
            'idempotency_key' => 'idemp-test-101',
            'items' => [
                ['product_id' => 1, 'quantity' => 4]
            ]
        ];

        $response = $this->postJson('/api/v1/orders', $payload);
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order.status', 'pending');
        $response->assertJsonPath('data.order.whatsapp_status', 'prepared');
        $this->assertStringContainsString('https://wa.me/', $response->json('data.whatsapp_url'));

        // Assert stock deduction
        $product = Product::find(1);
        $this->assertEquals(96, $product->stock_quantity);

        // Assert Customer and Address created
        $customer = Customer::where('phone', '+971501112233')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('Tariq Al Hammadi', $customer->name);
        $this->assertEquals(1, $customer->addresses()->count());
    }

    public function test_idempotency_duplicate_protection()
    {
        $payload = [
            'name' => 'Double Click User',
            'phone' => '0507776655',
            'villa_number' => 'Villa 1',
            'delivery_address' => 'Main Street',
            'payment_method' => 'cash',
            'idempotency_key' => 'same-uuid-12345',
            'items' => [
                ['product_id' => 1, 'quantity' => 2]
            ]
        ];

        // First click
        $res1 = $this->postJson('/api/v1/orders', $payload);
        $res1->assertStatus(201);
        $orderNumber1 = $res1->json('data.order_number');

        // Second click with identical idempotency key
        $res2 = $this->postJson('/api/v1/orders', $payload);
        $res2->assertStatus(201);
        $orderNumber2 = $res2->json('data.order_number');

        $this->assertEquals($orderNumber1, $orderNumber2);
        $this->assertTrue($res2->json('data.is_duplicate'));

        // Stock deducted only once for 2 items
        $product = Product::find(1);
        $this->assertEquals(98, $product->stock_quantity);
    }

    public function test_historical_address_snapshot_integrity()
    {
        // 1. Place Order 1 with Address A
        $res1 = $this->postJson('/api/v1/orders', [
            'name' => 'Rashid',
            'phone' => '0503332211',
            'villa_number' => 'Villa A',
            'delivery_address' => 'Old Street',
            'payment_method' => 'cash',
            'items' => [['product_id' => 1, 'quantity' => 1]]
        ]);
        $order1Number = $res1->json('data.order_number');

        // 2. Update customer default address to Villa B
        $customer = Customer::where('phone', '+971503332211')->first();
        $address = $customer->addresses()->first();
        $address->update(['villa_number' => 'Villa B', 'street_address' => 'New Street']);

        // 3. Verify Order 1 still has Villa A address snapshot
        $order1 = Order::where('order_number', $order1Number)->first();
        $this->assertEquals('Villa A', $order1->customer_villa);
        $this->assertEquals('Old Street', $order1->customer_address);
    }
}
