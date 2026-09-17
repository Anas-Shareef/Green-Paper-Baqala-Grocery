<?php

use App\Http\Controllers\Api\v1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\v1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\v1\Admin\AdminExpenseController;
use App\Http\Controllers\Api\v1\Admin\AdminInventoryController;
use App\Http\Controllers\Api\v1\Admin\AdminOrderController;
use App\Http\Controllers\Api\v1\Admin\AdminProductController;
use App\Http\Controllers\Api\v1\Admin\AdminReportController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\OrderController;
use App\Http\Controllers\Api\v1\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Baqqala Canonical REST API Routes (/api/v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

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
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::get('/products/{id}', [AdminProductController::class, 'show']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::post('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

        Route::get('/inventory', [AdminInventoryController::class, 'index']);
        Route::post('/inventory/adjustments', [AdminInventoryController::class, 'adjustStock']);
        Route::get('/inventory/movements', [AdminInventoryController::class, 'movements']);

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

        Route::get('/expenses', [AdminExpenseController::class, 'index']);
        Route::post('/expenses', [AdminExpenseController::class, 'store']);
        Route::delete('/expenses/{id}', [AdminExpenseController::class, 'destroy']);

        Route::get('/reports', [AdminReportController::class, 'index']);
    });
});
