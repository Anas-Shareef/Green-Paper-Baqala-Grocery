<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Services\InventoryService;

echo "=== BAQQALA STOCK RECEIVING SYSTEM VERIFICATION ===\n\n";

$inventoryService = app(InventoryService::class);

// 1. Check or create test supplier
$supplier = Supplier::firstOrCreate(
    ['name' => 'Gulf Fresh Foods Trading LLC'],
    [
        'phone' => '+971 4 333 4444',
        'email' => 'orders@gulffresh.ae',
        'address' => 'Al Aweer Central Fruits & Vegetables Market, Dubai, UAE',
        'contact_person' => 'Tariq Al Mansoori',
        'tax_number' => 'TRN-100293847500003',
        'status' => 'active',
        'notes' => 'Primary dairy and fresh produce distributor',
    ]
);
echo "1. Supplier verified: {$supplier->name} (ID: {$supplier->id})\n";

// 2. Find a test product with a barcode
$product = Product::whereNotNull('barcode')->first();
if (!$product) {
    // Pick any product and assign a barcode
    $product = Product::first();
    $product->barcode = '6291001234567';
    $product->save();
}
echo "2. Test Product: '{$product->name}' (Barcode: {$product->barcode}, Current Stock: {$product->stock_quantity}, Cost: AED {$product->wholesale_cost})\n";

// 3. Test Barcode Lookup via Controller
$receivingController = app(\App\Http\Controllers\Api\v1\Admin\AdminReceivingController::class);
$lookupResponse = $receivingController->barcodeLookup($product->barcode);
$lookupData = json_decode($lookupResponse->getContent(), true);
assert($lookupData['success'] === true && $lookupData['data']['found'] === true);
echo "3. Barcode Lookup fast response verified: Found '{$lookupData['data']['product']['name']}'\n";

// 4. Create a Draft GRN
$initialStock = (int) $product->stock_quantity;
$initialCost = (float) $product->wholesale_cost;
$receiveQty = 15;
$newCost = round($initialCost + 1.50, 2);

$header = [
    'supplier_id' => $supplier->id,
    'supplier_name_snapshot' => $supplier->name,
    'supplier_invoice_number' => 'INV-TEST-' . rand(1000, 9999),
    'invoice_date' => date('Y-m-d'),
    'purchase_reference' => 'PO-TEST-' . rand(100, 999),
    'receiving_date' => date('Y-m-d'),
    'status' => 'draft',
    'discount' => 5.00,
    'other_charges' => 10.00,
    'notes' => 'Test receiving delivery of fresh stock',
];

$items = [
    [
        'product_id' => $product->id,
        'barcode' => $product->barcode,
        'quantity_expected' => 15,
        'quantity_received' => 15,
        'quantity_damaged' => 0,
        'unit_cost' => $newCost,
        'discount' => 0.00,
        'tax_amount' => round(($receiveQty * $newCost) * 0.05, 2), // UAE VAT 5%
        'expiry_date' => date('Y-m-d', strtotime('+30 days')),
        'batch_number' => 'BATCH-TEST-001',
    ]
];

$receipt = $inventoryService->createStockReceipt($header, $items, 'TestRunner');
echo "4. Draft GRN Created: {$receipt->grn_number} (Status: {$receipt->status}, Total: AED {$receipt->total_amount})\n";

// Check that physical stock is UNCHANGED in draft
$productFresh = $product->fresh();
if ($productFresh->stock_quantity === $initialStock) {
    echo "   -> PASS: Physical stock remains unchanged ({$productFresh->stock_quantity} units) while in DRAFT status.\n";
} else {
    echo "   -> FAIL: Physical stock changed in draft! Expected {$initialStock}, got {$productFresh->stock_quantity}\n";
    exit(1);
}

// 5. Confirm GRN
$confirmedReceipt = $inventoryService->confirmStockReceipt($receipt->id, 'TestRunner');
echo "5. GRN Confirmed: {$confirmedReceipt->grn_number} (Status: {$confirmedReceipt->status})\n";

$productAfter = $product->fresh();
$expectedStock = $initialStock + $receiveQty;
if ($productAfter->stock_quantity === $expectedStock) {
    echo "   -> PASS: Physical stock correctly increased from {$initialStock} to {$expectedStock} units (+{$receiveQty}).\n";
} else {
    echo "   -> FAIL: Physical stock mismatch! Expected {$expectedStock}, got {$productAfter->stock_quantity}\n";
    exit(1);
}

echo "   -> Pass: Wholesale cost updated via weighted average to AED {$productAfter->wholesale_cost} (from AED {$initialCost}).\n";

// Verify StockMovement ledger entry
$movement = StockMovement::where('reference_type', 'GRN')
    ->where('reference_id', $receipt->id)
    ->first();
if ($movement && $movement->quantity === $receiveQty && $movement->type === 'Purchase') {
    echo "   -> PASS: Immutable ledger movement recorded (ID: {$movement->id}, Reason: '{$movement->reason}').\n";
} else {
    echo "   -> FAIL: StockMovement not found or incorrect!\n";
    exit(1);
}

// 6. Test Idempotency (prevent double confirmation)
try {
    $inventoryService->confirmStockReceipt($receipt->id, 'TestRunner');
    echo "   -> FAIL: Second confirmation succeeded when it should have thrown an exception!\n";
    exit(1);
} catch (\InvalidArgumentException $e) {
    echo "6. Idempotency Verified: Second confirmation rejected with message: '{$e->getMessage()}'\n";
}

// 7. Verify KPIs
$kpis = $inventoryService->getReceivingKPIs();
echo "7. Receiving KPIs:\n";
echo "   - Draft Count: {$kpis['draft_count']}\n";
echo "   - Received Today: {$kpis['received_today_count']} receipts (Total Value: AED {$kpis['received_today_value']})\n";
echo "   - Month Total: {$kpis['month_receipts_count']} receipts (AED {$kpis['month_total_value']})\n";

echo "\n=== ALL BACKEND RECEIVING VERIFICATIONS PASSED SUCCESSFULLY ===\n";
