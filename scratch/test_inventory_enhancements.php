<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

echo "=== BAQQALA INVENTORY ENHANCEMENTS TEST ===\n\n";

$productController = app(\App\Http\Controllers\Api\v1\Admin\AdminProductController::class);
$categoryController = app(\App\Http\Controllers\Api\v1\Admin\AdminCategoryController::class);
$orderController = app(\App\Http\Controllers\Api\v1\Admin\AdminOrderController::class);

// 1. Test Category Creation & image_url attribute
$catRequest = new Request([
    'name' => 'Organic Fresh Produce ' . rand(100, 999),
    'description' => 'Locally grown organic fruits & greens',
    'sort_order' => 1,
    'status' => 'active',
    'image_url' => 'https://example.com/organic-banner.webp',
]);
$catResponse = $categoryController->store($catRequest);
$catData = json_decode($catResponse->getContent(), true);
assert($catData['success'] === true);
$category = Category::find($catData['data']['id']);
echo "1. Category created: {$category->name} (image_url: {$category->image_url})\n";
assert(!empty($category->image_url));

// 2. Test Product Creation with wholesale_cost, retail_price & image_url attribute
$prodSku = 'TEST-PROD-' . rand(1000, 9999);
$prodRequest = new Request([
    'name' => 'Organic Hass Avocados 500g',
    'category_id' => $category->id,
    'sku' => $prodSku,
    'barcode' => '629' . rand(1000000000, 9999999999),
    'unit' => 'pack',
    'wholesale_cost' => 8.50,
    'retail_price' => 14.00,
    'stock_quantity' => 25,
    'minimum_stock_level' => 5,
    'status' => 'active',
    'image_url' => 'https://example.com/avocado.webp',
]);
$prodResponse = $productController->store($prodRequest);
$prodData = json_decode($prodResponse->getContent(), true);
assert($prodData['success'] === true);
$product1 = Product::find($prodData['data']['id']);
echo "2. Product 1 created: {$product1->name} (SKU: {$product1->sku}, image_url: {$product1->image_url})\n";
assert(!empty($product1->image_url));
assert(floatval($product1->retail_price) === 14.00);

// Create Product 2 (unreferenced, will be deleted)
$prod2Request = new Request([
    'name' => 'Temporary Test Product ' . rand(100, 999),
    'category_id' => $category->id,
    'sku' => 'TEMP-' . rand(1000, 9999),
    'retail_price' => 5.00,
    'wholesale_cost' => 2.50,
    'stock_quantity' => 10,
    'status' => 'active',
]);
$prod2Response = $productController->store($prod2Request);
$prod2Data = json_decode($prod2Response->getContent(), true);
$product2 = Product::find($prod2Data['data']['id']);
echo "3. Product 2 created: {$product2->name} (ID: {$product2->id})\n";

// Attach a StockMovement to Product 1 so it has historical references
StockMovement::create([
    'product_id' => $product1->id,
    'type' => 'Purchase',
    'quantity' => 25,
    'stock_before' => 0,
    'stock_after' => 25,
    'unit_cost' => 8.50,
    'reference_type' => 'Manual',
    'reason' => 'Initial test stock',
    'created_by' => 'TestRunner',
]);
echo "4. Historical StockMovement attached to Product 1.\n";

// 5. Test Bulk Status Change
$bulkStatusReq = new Request([
    'product_ids' => [$product1->id, $product2->id],
    'status' => 'inactive',
]);
$statusRes = $productController->bulkStatus($bulkStatusReq);
$statusData = json_decode($statusRes->getContent(), true);
assert($statusData['success'] === true);
echo "5. Bulk Status Change: Set inactive for 2 products.\n";

// 6. Test Safe Bulk Delete:
// Product 1 has historical movements -> should be ARCHIVED (status inactive)
// Product 2 has NO references -> should be PERMANENTLY DELETED
$bulkDelReq = new Request([
    'product_ids' => [$product1->id, $product2->id],
]);
$delRes = $productController->bulkDelete($bulkDelReq);
$delData = json_decode($delRes->getContent(), true);
echo "6. Safe Bulk Delete Result: {$delData['data']['deleted']} deleted, {$delData['data']['archived']} archived.\n";
assert($delData['data']['archived'] >= 1);
assert($delData['data']['deleted'] >= 1);

// Verify Product 2 is completely gone, but Product 1 exists (archived)
assert(Product::find($product2->id) === null);
$p1After = Product::withTrashed()->find($product1->id);
assert($p1After !== null);
echo "   -> PASS: Product 1 safely archived, Product 2 permanently deleted.\n";

// 7. Test Product Import Template Download
$templateResponse = $productController->importTemplate();
assert($templateResponse->getStatusCode() === 200);
$templateContent = $templateResponse->getContent();
assert(str_contains($templateContent, 'product_name,sku,barcode,category'));
echo "7. Product Import Template verified (Content length: " . strlen($templateContent) . " bytes).\n";

// 8. Test Product Import (Preview & Confirm)
$csvData = "\xEF\xBB\xBFproduct_name,sku,barcode,category,unit,cost_price,selling_price,stock_quantity,low_stock_threshold,status,description\n";
$csvData .= "\"Imported Coconut Water 330ml\",\"COCO-330\",\"6299998887771\",\"{$category->name}\",\"bottle\",\"2.00\",\"3.50\",\"50\",\"10\",\"active\",\"Pure coconut water\"\n";
$tempCsvPath = sys_get_temp_dir() . '/test_prod_import.csv';
file_put_contents($tempCsvPath, $csvData);

$uploadedFile = new UploadedFile($tempCsvPath, 'test_prod_import.csv', 'text/csv', null, true);

// Preview mode
$importPreviewReq = new Request(['confirm' => false], [], [], [], ['file' => $uploadedFile]);
$importPreviewRes = $productController->import($importPreviewReq);
$previewData = json_decode($importPreviewRes->getContent(), true);
assert($previewData['success'] === true);
echo "8. Import Preview: {$previewData['data']['valid_count']} valid row(s), {$previewData['data']['new_count']} new product(s).\n";

// Confirm mode
$importConfirmReq = new Request(['confirm' => true], [], [], [], ['file' => $uploadedFile]);
$importConfirmRes = $productController->import($importConfirmReq);
$confirmData = json_decode($importConfirmRes->getContent(), true);
assert($confirmData['success'] === true);
$importedProd = Product::where('sku', 'COCO-330')->first();
assert($importedProd !== null && $importedProd->name === 'Imported Coconut Water 330ml');
echo "   -> PASS: Product successfully imported and created: '{$importedProd->name}' (Stock: {$importedProd->stock_quantity})\n";

// 9. Test Product Export
$exportRes = $productController->export(new Request(['q' => 'Coconut']));
$exportContent = $exportRes->getContent();
assert(str_contains($exportContent, 'COCO-330'));
echo "9. Product Export CSV verified (Found imported SKU COCO-330 in output).\n";

// 10. Test Orders Import Template
$orderTemplateRes = $orderController->importTemplate();
assert($orderTemplateRes->getStatusCode() === 200);
$orderTplContent = $orderTemplateRes->getContent();
assert(str_contains($orderTplContent, 'customer_name,customer_phone,villa_number'));
echo "10. Orders Import Template verified.\n";

echo "\n=== ALL INVENTORY & ORDER ENHANCEMENTS BACKEND TESTS PASSED ===\n";
