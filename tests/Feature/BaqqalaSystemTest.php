<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PdfInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaqqalaSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pos_sale_deducts_stock_and_records_stock_movement_ledger()
    {
        $milk = Product::where('barcode', '8901288030609')->firstOrFail();
        $initialStock = $milk->stock_quantity;

        $orderService = app(OrderService::class);
        $order = $orderService->createOrder([
            ['product_id' => $milk->id, 'quantity' => 2]
        ], [
            'order_source' => 'POS',
            'payment_method' => 'Cash',
        ]);

        $milk->refresh();
        $this->assertEquals($initialStock - 2, $milk->stock_quantity);

        $movement = StockMovement::where('reference_id', $order->id)->where('type', 'POS Sale')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity);
    }

    public function test_pwa_checkout_enforces_minimum_order_value_mov()
    {
        $milk = Product::where('barcode', '8901288030609')->firstOrFail();
        $orderService = app(OrderService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Minimum order value is ₹300/');

        $orderService->createOrder([
            ['product_id' => $milk->id, 'quantity' => 2]
        ], [
            'order_source' => 'PWA',
            'payment_method' => 'Cash',
        ]);
    }

    public function test_net_profit_is_calculated_correctly_using_wholesale_and_delivery_costs()
    {
        $milk = Product::where('barcode', '8901288030609')->firstOrFail();
        $eggs = Product::where('barcode', '8901030012345')->firstOrFail();

        $orderService = app(OrderService::class);
        $order = $orderService->createOrder([
            ['product_id' => $milk->id, 'quantity' => 2],
            ['product_id' => $eggs->id, 'quantity' => 1],
        ], [
            'order_source' => 'PWA',
            'internal_delivery_cost' => 25.00,
        ]);

        $this->assertEquals(360.00, $order->total_amount);
        $this->assertEquals(270.00, $order->product_cost);
        $this->assertEquals(90.00, $order->gross_profit);
        $this->assertEquals(65.00, $order->net_profit);
    }

    public function test_customer_api_never_exposes_wholesale_cost()
    {
        $response = $this->getJson('/api/home');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertNotEmpty($json['featured_products']);

        foreach ($json['featured_products'] as $product) {
            $this->assertArrayNotHasKey('wholesale_cost', $product);
        }
    }

    public function test_pdf_invoice_generates_cleanly()
    {
        $order = Order::firstOrFail();
        $pdfService = app(PdfInvoiceService::class);

        $pdf = $pdfService->generateInvoicePdf($order);
        $this->assertNotEmpty($pdf->output());
    }

    public function test_admin_whatsapp_page_loads_without_query_exception()
    {
        $admin = User::where('role', 'super_admin')->firstOrFail();
        $response = $this->actingAs($admin)->get('/admin/whatsapp');
        $response->assertStatus(200);
    }
}
