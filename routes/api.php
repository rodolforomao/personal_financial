<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LiquidxPaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/switch-workspace', [AuthController::class, 'switchWorkspace']);
});

Route::post('/webhooks/liquidx/payments', LiquidxPaymentWebhookController::class)->name('webhooks.liquidx.payments');
