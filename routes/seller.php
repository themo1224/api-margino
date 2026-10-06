<?php

use App\Http\Controllers\Seller\AuthController;
use App\Http\Controllers\Seller\ConfirmRivalMatchController;
use App\Http\Controllers\Seller\PricingAlertsController;
use App\Http\Controllers\Seller\PricingApiKeysController;
use App\Http\Controllers\Seller\PricingOverviewController;
use App\Http\Controllers\Seller\PricingProductsController;
use App\Http\Controllers\Seller\PricingReportsController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->name('seller.auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('seller.auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('seller.auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('seller.me');

    Route::get('/pricing/overview', PricingOverviewController::class)->name('seller.pricing.overview');
    Route::get('/pricing/products', PricingProductsController::class)->name('seller.pricing.products');
    Route::post('/pricing/products/{product}/rivals/{match}/confirm', ConfirmRivalMatchController::class)
        ->name('seller.pricing.products.rivals.confirm');

    Route::get('/pricing/reports', PricingReportsController::class)->name('seller.pricing.reports');
    Route::get('/pricing/alerts', [PricingAlertsController::class, 'show'])->name('seller.pricing.alerts.show');
    Route::put('/pricing/alerts', [PricingAlertsController::class, 'update'])->name('seller.pricing.alerts.update');

    Route::get('/pricing/api-keys', [PricingApiKeysController::class, 'index'])->name('seller.pricing.api-keys.index');
    Route::post('/pricing/api-keys', [PricingApiKeysController::class, 'store'])->name('seller.pricing.api-keys.store');
    Route::post('/pricing/api-keys/{id}/revoke', [PricingApiKeysController::class, 'revoke'])
        ->whereNumber('id')
        ->name('seller.pricing.api-keys.revoke');
});
