<?php

use App\Http\Controllers\Api\v1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\v1\Admin\AdminCustomerController;
use App\Http\Controllers\Api\v1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\v1\Admin\AdminExpenseController;
use App\Http\Controllers\Api\v1\Admin\AdminInventoryController;
use App\Http\Controllers\Api\v1\Admin\AdminOrderController;
use App\Http\Controllers\Api\v1\Admin\AdminProductController;
use App\Http\Controllers\Api\v1\Admin\AdminReceivingController;
use App\Http\Controllers\Api\v1\Admin\AdminReportController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\CustomerAddressController;
use App\Http\Controllers\Api\v1\HealthController;
use App\Http\Controllers\Api\v1\OrderController;
use App\Http\Controllers\Api\v1\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Baqqala Canonical REST API Routes (/api/v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Health & System Diagnostics
    Route::get('/health/database', [HealthController::class, 'databaseCheck']);

    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

    // Customer Recognition, Profile & Address Book Routes
    Route::post('/customer/recognize', [CustomerAddressController::class, 'recognize']);
    Route::post('/customer/identify', [CustomerAddressController::class, 'recognize']);
    Route::put('/customer/profile', [CustomerAddressController::class, 'updateProfile']);
    Route::get('/customer/addresses', [CustomerAddressController::class, 'index']);
    Route::post('/customer/addresses', [CustomerAddressController::class, 'store']);
    Route::put('/customer/addresses/{id}', [CustomerAddressController::class, 'update']);
    Route::delete('/customer/addresses/{id}', [CustomerAddressController::class, 'destroy']);
    Route::post('/customer/addresses/{id}/default', [CustomerAddressController::class, 'setDefault']);

    // Customer Storefront Routes
    Route::get('/home', [ProductController::class, 'home']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/categories', [ProductController::class, 'categories']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/history', [OrderController::class, 'customerHistory']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);

    // Admin Routes
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/export', [AdminProductController::class, 'export']);
        Route::get('/products/import-template', [AdminProductController::class, 'importTemplate']);
        Route::post('/products/import', [AdminProductController::class, 'import']);
        Route::post('/products/bulk-delete', [AdminProductController::class, 'bulkDelete']);
        Route::post('/products/bulk-category', [AdminProductController::class, 'bulkCategory']);
        Route::post('/products/bulk-status', [AdminProductController::class, 'bulkStatus']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::get('/products/{id}', [AdminProductController::class, 'show']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::post('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

        // Inventory Management & Stock Ledger Routes
        Route::get('/inventory', [AdminInventoryController::class, 'index']);
        Route::get('/inventory/summary', [AdminInventoryController::class, 'summary']);
        Route::get('/inventory/movements', [AdminInventoryController::class, 'movements']);
        Route::get('/inventory/reorder', [AdminInventoryController::class, 'reorder']);
        Route::get('/inventory/valuation', [AdminInventoryController::class, 'valuation']);
        Route::get('/inventory/reconciliation', [AdminInventoryController::class, 'reconciliation']);
        Route::post('/inventory/reconciliation/{id}/correct', [AdminInventoryController::class, 'correctReconciliation']);
        Route::get('/inventory/counts', [AdminInventoryController::class, 'listCounts']);
        Route::post('/inventory/counts', [AdminInventoryController::class, 'startCount']);
        Route::get('/inventory/counts/{id}', [AdminInventoryController::class, 'showCount']);
        Route::post('/inventory/counts/{id}/approve', [AdminInventoryController::class, 'approveCount']);
        Route::get('/inventory/{id}', [AdminInventoryController::class, 'show']);
        Route::get('/inventory/{id}/ledger', [AdminInventoryController::class, 'ledger']);
        Route::post('/inventory/adjustments', [AdminInventoryController::class, 'adjustStock']);

        // Stock Receiving Station & Goods Received Note (GRN) Routes
        Route::get('/receiving', [AdminReceivingController::class, 'index']);
        Route::get('/receiving/kpis', [AdminReceivingController::class, 'kpis']);
        Route::get('/receiving/suppliers', [AdminReceivingController::class, 'suppliers']);
        Route::post('/receiving/suppliers', [AdminReceivingController::class, 'storeSupplier']);
        Route::post('/receiving/quick-product', [AdminReceivingController::class, 'quickCreateProduct']);
        Route::post('/receiving', [AdminReceivingController::class, 'store']);
        Route::get('/receiving/{id}', [AdminReceivingController::class, 'show']);
        Route::put('/receiving/{id}', [AdminReceivingController::class, 'update']);
        Route::post('/receiving/{id}/confirm', [AdminReceivingController::class, 'confirm']);
        Route::post('/receiving/{id}/cancel', [AdminReceivingController::class, 'cancel']);
        Route::post('/receiving/{id}/return', [AdminReceivingController::class, 'returnStock']);

        // Barcode Quick Lookup (Datalogic QuickScan Lite)
        Route::get('/products/barcode/{barcode}', [AdminReceivingController::class, 'barcodeLookup']);

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/export', [AdminOrderController::class, 'export']);
        Route::get('/orders/import-template', [AdminOrderController::class, 'importTemplate']);
        Route::post('/orders/import', [AdminOrderController::class, 'import']);
        Route::post('/orders/bulk-action', [AdminOrderController::class, 'bulkAction']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::match(['put', 'patch'], '/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/confirm', [AdminOrderController::class, 'confirm']);
        Route::post('/orders/{id}/prepare', [AdminOrderController::class, 'prepare']);
        Route::post('/orders/{id}/ready', [AdminOrderController::class, 'ready']);
        Route::post('/orders/{id}/assign-driver', [AdminOrderController::class, 'assignDriver']);
        Route::post('/orders/{id}/dispatch', [AdminOrderController::class, 'dispatch']);
        Route::post('/orders/{id}/deliver', [AdminOrderController::class, 'deliver']);
        Route::match(['post', 'patch'], '/orders/{id}/payment', [AdminOrderController::class, 'payment']);
        Route::post('/orders/{id}/fail-delivery', [AdminOrderController::class, 'failDelivery']);
        Route::post('/orders/{id}/cancel', [AdminOrderController::class, 'cancel']);
        Route::post('/orders/{id}/pick-item', [AdminOrderController::class, 'pickItem']);
        Route::get('/orders/{id}/activity', [AdminOrderController::class, 'activity']);

        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::get('/customers/{id}', [AdminCustomerController::class, 'show']);
        Route::put('/customers/{id}', [AdminCustomerController::class, 'update']);

        Route::get('/expenses', [AdminExpenseController::class, 'index']);
        Route::post('/expenses', [AdminExpenseController::class, 'store']);
        Route::delete('/expenses/{id}', [AdminExpenseController::class, 'destroy']);

        Route::get('/reports', [AdminReportController::class, 'index']);

        // Admin Realtime Notifications & Push Subscriptions
        Route::get('/notifications', [\App\Http\Controllers\Api\v1\Admin\AdminNotificationController::class, 'index']);
        Route::get('/realtime-check', [\App\Http\Controllers\Api\v1\Admin\AdminNotificationController::class, 'realtimeCheck']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\v1\Admin\AdminNotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\v1\Admin\AdminNotificationController::class, 'markAllAsRead']);
        Route::post('/push-subscriptions', [\App\Http\Controllers\Api\v1\Admin\AdminNotificationController::class, 'subscribePush']);
    });
});
