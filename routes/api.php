<?php

use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Baqqala Customer API Routes (For React Customer PWA)
|--------------------------------------------------------------------------
*/

Route::get('/home', [ProductController::class, 'home']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);

Route::post('/auth/send-otp', [CustomerAuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [CustomerAuthController::class, 'verifyOtp']);

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/history', [OrderController::class, 'customerHistory']);
Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
