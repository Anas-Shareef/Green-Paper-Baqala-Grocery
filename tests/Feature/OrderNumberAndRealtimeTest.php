<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\AdminNotification;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberAndRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
            'status' => 'active',
        ]);

        Product::create([
            'category_id' => 1,
            'name' => 'Dishwashing Liquid 1L',
            'sku' => 'DISH-001',
            'barcode' => '6291000999888',
            'retail_price' => 135.00,
            'cost_price' => 70.00,
            'stock_quantity' => 200,
            'minimum_stock_level' => 10,
            'unit' => 'Bottle',
            'status' => 'active',
        ]);
    }

    public function test_customer_order_number_sequence_and_zone_deduplication()
    {
        // 1. Abdullah's 1st order (Customer Order #1)
        $res1 = $this->postJson('/api/v1/orders', [
            'name' => 'Abdullah Omar',
            'phone' => '0508889900',
            'villa_number' => '94',
            'delivery_address' => 'Street 11',
            'zone' => 'Zone B',
            'payment_method' => 'cash',
            'items' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $res1->assertStatus(201);
        $res1->assertJsonPath('data.order.customer_order_number', 1);
        $order1 = Order::find($res1->json('data.order.id'));
        $this->assertEquals(1, $order1->customer_order_number);
        $this->assertStringContainsString('Customer Order: #1', $res1->json('data.message_body'));
        $this->assertStringContainsString('Baqqala Order ID: ORD-', $res1->json('data.message_body'));

        // Check canonical address: "Villa 94, Street 11, Zone B" - NO "Zone B, Zone B"
        $this->assertEquals('Villa 94, Street 11, Zone B', $order1->customer_address);
        $this->assertFalse(str_contains($res1->json('data.message_body'), 'Zone B, Zone B'));

        // 2. Sarah's 1st order (Customer Order #1 despite global count)
        $res2 = $this->postJson('/api/v1/orders', [
            'name' => 'Sarah Johnson',
            'phone' => '0501119999',
            'villa_number' => '45',
            'delivery_address' => 'Street 12, Zone C',
            'zone' => 'Zone C',
            'payment_method' => 'cash',
            'items' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $res2->assertStatus(201);
        $res2->assertJsonPath('data.order.customer_order_number', 1);
        $order2 = Order::find($res2->json('data.order.id'));
        $this->assertEquals(1, $order2->customer_order_number);

        // 3. Abdullah's 2nd order (Customer Order #2)
        $res3 = $this->postJson('/api/v1/orders', [
            'name' => 'Abdullah Omar',
            'phone' => '0508889900',
            'villa_number' => '94',
            'delivery_address' => 'Street 11, Zone B',
            'zone' => 'Zone B',
            'payment_method' => 'cash',
            'items' => [['product_id' => 1, 'quantity' => 2]]
        ]);

        $res3->assertStatus(201);
        $res3->assertJsonPath('data.order.customer_order_number', 2);
        $order3 = Order::find($res3->json('data.order.id'));
        $this->assertEquals(2, $order3->customer_order_number);
    }

    public function test_admin_notifications_endpoints()
    {
        // Create order
        $this->postJson('/api/v1/orders', [
            'name' => 'Realtime Customer',
            'phone' => '0507771122',
            'villa_number' => 'Villa 12',
            'delivery_address' => 'Street 5',
            'payment_method' => 'cash',
            'items' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        // Check AdminNotifications created in DB
        $notification = AdminNotification::latest()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('new_order', $notification->type);

        // Fetch notifications endpoint
        $res1 = $this->getJson('/api/v1/admin/notifications');
        $res1->assertStatus(200);
        $res1->assertJsonPath('success', true);

        // Check realtime-check polling endpoint
        $res2 = $this->getJson('/api/v1/admin/realtime-check');
        $res2->assertStatus(200);
        $res2->assertJsonPath('data.unread_count', 1);

        // Mark as read
        $res3 = $this->postJson('/api/v1/admin/notifications/' . $notification->id . '/read');
        $res3->assertStatus(200);

        $this->assertTrue((bool) $notification->fresh()->is_read);

        // Subscribe to Web Push
        $res4 = $this->postJson('/api/v1/admin/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => [
                'p256dh' => 'test-public-key',
                'auth' => 'test-auth-token'
            ]
        ]);
        $res4->assertStatus(200);
        $res4->assertJsonPath('success', true);
    }
}
