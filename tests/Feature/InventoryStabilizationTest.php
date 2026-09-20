<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderCreationService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStabilizationTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected Product $product;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@baqqala.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $this->category = Category::create([
            'name' => 'Dairy & Eggs',
            'slug' => 'dairy-eggs',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Al Ain Fresh Milk 2L',
            'sku' => 'MILK-2L-001',
            'barcode' => '6291003456789',
            'retail_price' => 12.50,
            'wholesale_cost' => 8.00,
            'stock_quantity' => 50,
            'reserved_quantity' => 0,
            'minimum_stock_level' => 10,
            'unit' => 'bottle',
            'status' => 'active',
        ]);
    }

    public function test_product_model_available_stock_and_helpers()
    {
        $this->assertEquals(50, $this->product->available_stock);
        $this->assertFalse($this->product->is_low_stock);
        $this->assertFalse($this->product->is_out_of_stock);

        // 1. Reserve 10 units
        $this->product->reserveStock(10);
        $this->product->refresh();
        $this->assertEquals(50, $this->product->stock_quantity); // Shelf untouched
        $this->assertEquals(10, $this->product->reserved_quantity);
        $this->assertEquals(40, $this->product->available_stock);

        // 2. Release 5 units
        $this->product->releaseReservation(5);
        $this->product->refresh();
        $this->assertEquals(50, $this->product->stock_quantity);
        $this->assertEquals(5, $this->product->reserved_quantity);
        $this->assertEquals(45, $this->product->available_stock);

        // 3. Finalize sale of remaining 5 units
        $this->product->finalizeSale(5);
        $this->product->refresh();
        $this->assertEquals(45, $this->product->stock_quantity); // Deducted upon delivery
        $this->assertEquals(0, $this->product->reserved_quantity);
        $this->assertEquals(45, $this->product->available_stock);
    }

    public function test_order_lifecycle_online_reservation_and_delivery()
    {
        $orderService = app(OrderService::class);

        // 1. Customer creates online order for 5 units
        $creationService = app(OrderCreationService::class);
        $res = $creationService->createOrder([
            'customer_name' => 'Fatima Al Mansoori',
            'customer_phone' => '0502223344',
            'villa_number' => 'Villa 42',
            'delivery_address' => 'Al Bateen',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5]
            ]
        ]);

        $order = $res['order'];
        $this->product->refresh();

        // Physical shelf stock untouched, reservation incremented
        $this->assertEquals(50, $this->product->stock_quantity);
        $this->assertEquals(5, $this->product->reserved_quantity);
        $this->assertEquals(45, $this->product->available_stock);

        // Ledger movement recorded for reservation
        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovement::TYPE_RESERVATION)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(5, $movement->quantity);

        // 2. Deliver Order -> physical and reserved deducted, sale movement recorded
        $orderService->deliverOrder($order, $this->adminUser);
        $this->product->refresh();

        $this->assertEquals(45, $this->product->stock_quantity);
        $this->assertEquals(0, $this->product->reserved_quantity);
        $this->assertEquals(45, $this->product->available_stock);

        $saleMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovement::TYPE_SALE)
            ->first();
        $this->assertNotNull($saleMovement);
        $this->assertEquals(-5, $saleMovement->quantity);
    }

    public function test_order_cancellation_releases_reservation()
    {
        $orderService = app(OrderService::class);
        $creationService = app(OrderCreationService::class);

        $res = $creationService->createOrder([
            'customer_name' => 'Khalid Saeed',
            'customer_phone' => '0509990011',
            'villa_number' => 'Villa 7',
            'delivery_address' => 'Khalidiya',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 8]
            ]
        ]);

        $order = $res['order'];
        $this->product->refresh();
        $this->assertEquals(8, $this->product->reserved_quantity);

        // Cancel order -> reservation released
        $orderService->cancelOrder($order, 'Customer changed mind');
        $this->product->refresh();

        $this->assertEquals(50, $this->product->stock_quantity); // Shelf unchanged
        $this->assertEquals(0, $this->product->reserved_quantity);
        $this->assertEquals(50, $this->product->available_stock);

        $releaseMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovement::TYPE_RESERVATION_RELEASE)
            ->first();
        $this->assertNotNull($releaseMovement);
        $this->assertEquals(-8, $releaseMovement->quantity);
    }

    public function test_stock_receipt_confirmation_is_idempotent()
    {
        $inventoryService = app(InventoryService::class);

        $receipt = StockReceipt::create([
            'grn_number' => 'GRN-TEST-0001',
            'supplier_name_snapshot' => 'Al Ain Dairy LLC',
            'supplier_invoice_number' => 'INV-999',
            'invoice_date' => now()->toDateString(),
            'receiving_date' => now()->toDateString(),
            'status' => 'draft',
            'total_amount' => 160.00,
            'received_by' => $this->adminUser->id,
        ]);

        StockReceiptItem::create([
            'stock_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity_expected' => 20,
            'quantity_received' => 20,
            'quantity_sellable' => 20,
            'quantity_damaged' => 0,
            'unit_cost' => 8.00,
            'total_cost' => 160.00,
        ]);

        // First confirmation
        $confirmed = $inventoryService->confirmStockReceipt($receipt->id, $this->adminUser->id);
        $this->assertEquals('received', $confirmed->status);

        $this->product->refresh();
        $this->assertEquals(70, $this->product->stock_quantity); // 50 + 20

        // Duplicate confirmation (Idempotency)
        $secondConfirm = $inventoryService->confirmStockReceipt($receipt->id, $this->adminUser->id);
        $this->assertEquals('received', $secondConfirm->status);

        $this->product->refresh();
        $this->assertEquals(70, $this->product->stock_quantity); // Still 70, not 90!
    }

    public function test_stock_adjustment_validation_and_audit_entry()
    {
        $inventoryService = app(InventoryService::class);

        // Reserve 10 units first
        $this->product->reserveStock(10);
        $this->product->refresh();

        // 1. Attempt adjustment below reserved stock should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $inventoryService->adjustStock(
            $this->product->id,
            5, // Less than reserved 10!
            'Invalid count',
            'Correction',
            'Admin'
        );
    }

    public function test_reconciliation_diagnostic_and_correction()
    {
        $inventoryService = app(InventoryService::class);

        // Artificially create a discrepancy: change physical stock without movement
        $this->product->stock_quantity = 80; // Was 50, zero movements for the 30 difference
        $this->product->save();

        // Run reconciliation report
        $report = $inventoryService->getReconciliationReport();
        $this->assertGreaterThan(0, $report['summary']['discrepancies_count']);

        // Apply audit-safe correction
        $corrected = $inventoryService->applyReconciliationCorrection(
            $this->product->id,
            75,
            'Audited shelf count verified by Store Manager',
            'Admin'
        );

        $this->assertEquals(75, $corrected->stock_quantity);

        $correctionMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovement::TYPE_STOCK_CORRECTION)
            ->first();
        $this->assertNotNull($correctionMovement);
        $this->assertEquals(-5, $correctionMovement->quantity); // 80 -> 75
        $this->assertEquals(80, $correctionMovement->stock_before);
        $this->assertEquals(75, $correctionMovement->stock_after);
    }

    public function test_admin_inventory_endpoints()
    {
        // 1. Inventory Index
        $res = $this->getJson('/api/v1/admin/inventory');
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('data'));

        // 2. Product Ledger
        $ledgerRes = $this->getJson("/api/v1/admin/inventory/{$this->product->id}/ledger");
        $ledgerRes->assertStatus(200);
        $ledgerRes->assertJsonPath('success', true);
        $this->assertNotNull($ledgerRes->json('data'));

        // 3. Reconciliation Report
        $recRes = $this->getJson('/api/v1/admin/inventory/reconciliation');
        $recRes->assertStatus(200);
        $recRes->assertJsonPath('success', true);
        $recRes->assertJsonStructure([
            'data' => [
                'summary',
                'discrepancies'
            ]
        ]);
    }

    public function test_assets_can_be_retrieved()
    {
        $res = $this->get('/assets/index-DutNN4xF.css');
        $res->assertStatus(200);
        $this->assertStringContainsString('text/css', $res->headers->get('Content-Type'));
    }
}
