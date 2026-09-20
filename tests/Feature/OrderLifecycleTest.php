<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create([
            'name' => 'Dairy & Eggs',
            'slug' => 'dairy-eggs',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'category_id' => 1,
            'name' => 'Al Ain Fresh Milk 2L',
            'sku' => 'MILK-002',
            'barcode' => '6291000999888',
            'retail_price' => 11.00,
            'cost_price' => 7.50,
            'stock_quantity' => 50,
            'reserved_quantity' => 0,
            'minimum_stock_level' => 5,
            'unit' => 'Bottle',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'name' => 'Store Admin',
            'email' => 'admin@baqqala.ae',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);
    }

    public function test_customer_recognition_returns_enhanced_payload()
    {
        $customer = Customer::create([
            'name' => 'Fatima Al Zaabi',
            'phone' => '+971505556677',
            'status' => 'active',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Villa Home',
            'villa_number' => 'Villa 42',
            'street_address' => 'Street 19, Al Bateen',
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/v1/customer/recognize', [
            'phone' => '0505556677'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.recognized', true);
        $response->assertJsonPath('data.customer_exists', true);
        $response->assertJsonPath('data.customer.name', 'Fatima Al Zaabi');
        $response->assertJsonPath('data.default_address.villa_number', 'Villa 42');
        $this->assertCount(1, $response->json('data.addresses'));
    }

    public function test_server_authoritative_pricing_ignores_client_tampering()
    {
        // Client sends unit_price: 1.00, real retail_price in DB is 11.00
        $payload = [
            'name' => 'Smart Shopper',
            'phone' => '0501239876',
            'villa_number' => 'Villa 9',
            'delivery_address' => 'Sector 3',
            'payment_method' => 'cash',
            'idempotency_key' => 'tamper-proof-test-1',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 1.00 // Tampered!
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/orders', $payload);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->first();

        // 3 items * 11.00 AED = 33.00 AED total
        $this->assertEquals(33.00, (float) $order->total_amount);
        $this->assertEquals(11.00, (float) $order->items->first()->unit_price);
        $this->assertEquals(3, $this->product->fresh()->reserved_quantity);
    }

    public function test_allowed_state_transitions_flow()
    {
        $payload = [
            'name' => 'Khalifa Al Nuaimi',
            'phone' => '0504443322',
            'villa_number' => 'Villa 15',
            'delivery_address' => 'Al Mushrif',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2]
            ]
        ];

        $res = $this->postJson('/api/v1/orders', $payload);
        $orderId = $res->json('data.order.id');
        $order = Order::findOrFail($orderId);

        $this->assertEquals('pending', $order->status);

        // 1. Pending -> Confirmed
        $resConf = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'confirmed'
        ]);
        $resConf->assertStatus(200);
        $this->assertEquals('confirmed', $order->fresh()->status);

        // 2. Confirmed -> Preparing
        $resPrep = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'preparing'
        ]);
        $resPrep->assertStatus(200);
        $this->assertEquals('preparing', $order->fresh()->status);

        // 3. Preparing -> Out for Delivery
        $resOut = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'out_for_delivery'
        ]);
        $resOut->assertStatus(200);
        $this->assertEquals('out_for_delivery', $order->fresh()->status);

        // 4. Out for Delivery -> Delivered
        $resDel = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'delivered'
        ]);
        $resDel->assertStatus(200);
        $this->assertEquals('delivered', $order->fresh()->status);

        // Verify stock deducted upon delivery
        $this->assertEquals(48, $this->product->fresh()->stock_quantity);
        $this->assertEquals(0, $this->product->fresh()->reserved_quantity);
    }

    public function test_invalid_state_transition_is_rejected_with_422()
    {
        $payload = [
            'name' => 'Jump Customer',
            'phone' => '0502223344',
            'villa_number' => 'Villa 20',
            'delivery_address' => 'Corniche',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1]
            ]
        ];

        $res = $this->postJson('/api/v1/orders', $payload);
        $orderId = $res->json('data.order.id');

        // Attempt direct jump: pending -> delivered (Must fail with 422)
        $resJump = $this->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'delivered'
        ]);

        $resJump->assertStatus(422);
        $this->assertStringContainsString('Invalid order status transition', $resJump->json('message'));
        $this->assertEquals('pending', Order::find($orderId)->status);
    }

    public function test_order_cancellation_requires_reason_and_releases_reservation()
    {
        $payload = [
            'name' => 'Cancel Customer',
            'phone' => '0501119988',
            'villa_number' => 'Villa 3',
            'delivery_address' => 'Street 8',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 4]
            ]
        ];

        $res = $this->postJson('/api/v1/orders', $payload);
        $orderId = $res->json('data.order.id');
        $this->assertEquals(4, $this->product->fresh()->reserved_quantity);

        // 1. Attempt cancellation without reason (Must fail with 422)
        $resNoReason = $this->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => ''
        ]);
        $resNoReason->assertStatus(422);
        $this->assertStringContainsString('reason is required', strtolower($resNoReason->json('message')));

        // 2. Cancel with valid reason
        $resValid = $this->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer changed mind and will pick up in person'
        ]);
        $resValid->assertStatus(200);

        $order = Order::find($orderId);
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('Customer changed mind and will pick up in person', $order->cancellation_reason);

        // Stock reservation released
        $this->assertEquals(0, $this->product->fresh()->reserved_quantity);
        $this->assertEquals(50, $this->product->fresh()->available_stock);
    }

    public function test_failed_delivery_requires_reason_and_releases_reservation()
    {
        $orderService = app(OrderService::class);

        $payload = [
            'name' => 'No Answer Customer',
            'phone' => '0509991122',
            'villa_number' => 'Villa 99',
            'delivery_address' => 'Reem Island',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2]
            ]
        ];

        $res = $this->postJson('/api/v1/orders', $payload);
        $order = Order::findOrFail($res->json('data.order.id'));

        // Advance to out_for_delivery
        $orderService->confirmOrder($order, $this->adminUser);
        $orderService->startPreparing($order, $this->adminUser);
        $orderService->dispatchOrder($order, null, null, $this->adminUser);
        $this->assertEquals('out_for_delivery', $order->fresh()->status);

        // 1. Fail delivery without reason (Must fail with 422)
        $resNoReason = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'failed_delivery',
            'notes' => ''
        ]);
        $resNoReason->assertStatus(422);

        // 2. Fail delivery with reason
        $resFail = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'failed_delivery',
            'notes' => 'Customer gate locked and phone unreachable after 3 attempts'
        ]);
        $resFail->assertStatus(200);

        $this->assertEquals('failed_delivery', $order->fresh()->status);
        // Reservation released
        $this->assertEquals(0, $this->product->fresh()->reserved_quantity);
        $this->assertEquals(50, $this->product->fresh()->available_stock);
    }

    public function test_decoupled_cod_payment_collection()
    {
        $payload = [
            'name' => 'COD Collector',
            'phone' => '0507773344',
            'villa_number' => 'Villa 55',
            'delivery_address' => 'Khalidiya',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1]
            ]
        ];

        $res = $this->postJson('/api/v1/orders', $payload);
        $order = Order::findOrFail($res->json('data.order.id'));

        $this->assertEquals('pending', $order->payment_status);

        // Update payment to paid independently of order delivery
        $resPayment = $this->patchJson("/api/v1/admin/orders/{$order->id}/payment", [
            'payment_status' => 'paid',
            'payment_notes' => 'Cash collected exact AED 11.00'
        ]);
        $resPayment->assertStatus(200);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pending', $order->status); // Order status still pending!
    }

    public function test_scope_finalized_sales_strictly_includes_delivered()
    {
        // 1. Delivered order (Finalized sale)
        Order::create([
            'order_number' => 'ORD-FIN-1',
            'customer_name' => 'Delivered Cust',
            'customer_phone' => '+971501111111',
            'customer_address' => 'Zone 1',
            'customer_villa' => 'Villa 1',
            'total_amount' => 100.00,
            'subtotal' => 100.00,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
        ]);

        // 2. Pending order (Not finalized)
        Order::create([
            'order_number' => 'ORD-FIN-2',
            'customer_name' => 'Pending Cust',
            'customer_phone' => '+971502222222',
            'customer_address' => 'Zone 2',
            'customer_villa' => 'Villa 2',
            'total_amount' => 50.00,
            'subtotal' => 50.00,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        // 3. Cancelled order (Not finalized)
        Order::create([
            'order_number' => 'ORD-FIN-3',
            'customer_name' => 'Cancelled Cust',
            'customer_phone' => '+971503333333',
            'customer_address' => 'Zone 3',
            'customer_villa' => 'Villa 3',
            'total_amount' => 75.00,
            'subtotal' => 75.00,
            'status' => 'cancelled',
            'payment_method' => 'cod',
            'payment_status' => 'cancelled',
        ]);

        $finalizedTotal = Order::finalizedSales()->sum('total_amount');
        $this->assertEquals(100.00, (float) $finalizedTotal);
    }
}
