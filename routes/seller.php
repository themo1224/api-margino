<?php

use App\Http\Controllers\Seller\ApplyProductPriceController;
use App\Http\Controllers\Seller\AuthController;
use App\Http\Controllers\Seller\BatchApplyPriceController;
use App\Http\Controllers\Seller\BatchRefreshRivalsController;
use App\Http\Controllers\Seller\ConfirmRivalMatchController;
use App\Http\Controllers\Seller\PricingAlertsController;
use App\Http\Controllers\Seller\PricingApiKeysController;
use App\Http\Controllers\Seller\PricingAutoApplyController;
use App\Http\Controllers\Seller\PricingCostsController;
use App\Http\Controllers\Seller\PricingOverviewController;
use App\Http\Controllers\Seller\PricingPlanController;
use App\Http\Controllers\Seller\PricingProductsController;
use App\Http\Controllers\Seller\PricingReportsController;
use App\Http\Controllers\Seller\ProductDetailController;
use App\Http\Controllers\Seller\RefreshProductRivalsController;
use App\Http\Controllers\Seller\UnlinkProductRivalController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->name('seller.auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('seller.auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('seller.auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('seller.me');

    Route::get('/pricing/overview', PricingOverviewController::class)->name('seller.pricing.overview');
    Route::get('/pricing/products', PricingProductsController::class)->name('seller.pricing.products');

    // Batch actions (registered before parameter patterns)
    Route::post('/pricing/products/batch-apply-price', BatchApplyPriceController::class)
        ->name('seller.pricing.products.batch-apply-price');
    Route::post('/pricing/products/batch-refresh', BatchRefreshRivalsController::class)
        ->name('seller.pricing.products.batch-refresh');

    // Single product actions
    Route::get('/pricing/products/{product}', ProductDetailController::class)
        ->name('seller.pricing.products.detail');
    Route::post('/pricing/products/{product}/apply-price', ApplyProductPriceController::class)
        ->name('seller.pricing.products.apply-price');
    Route::post('/pricing/products/{product}/refresh-rivals', RefreshProductRivalsController::class)
        ->name('seller.pricing.products.refresh-rivals');
    Route::post('/pricing/products/{product}/unlink-rival', UnlinkProductRivalController::class)
        ->name('seller.pricing.products.unlink-rival');
    Route::post('/pricing/products/{product}/rivals/{match}/confirm', ConfirmRivalMatchController::class)
        ->name('seller.pricing.products.rivals.confirm');

    // Costs
    Route::get('/pricing/costs', [PricingCostsController::class, 'show'])->name('seller.pricing.costs.show');
    Route::put('/pricing/costs', [PricingCostsController::class, 'update'])->name('seller.pricing.costs.update');

    // Plan & quota limits
    Route::get('/pricing/plan', [PricingPlanController::class, 'show'])->name('seller.pricing.plan.show');
    Route::post('/pricing/plan/upgrade', [PricingPlanController::class, 'upgrade'])->name('seller.pricing.plan.upgrade');

    Route::get('/pricing/reports', PricingReportsController::class)->name('seller.pricing.reports');
    Route::get('/pricing/alerts', [PricingAlertsController::class, 'show'])->name('seller.pricing.alerts.show');
    Route::put('/pricing/alerts', [PricingAlertsController::class, 'update'])->name('seller.pricing.alerts.update');

    // Auto-Apply (Pro Plan)
    Route::get('/pricing/auto-apply', [PricingAutoApplyController::class, 'show'])->name('seller.pricing.auto-apply.show');
    Route::put('/pricing/auto-apply', [PricingAutoApplyController::class, 'update'])->name('seller.pricing.auto-apply.update');
    Route::post('/pricing/auto-apply/run-now', [PricingAutoApplyController::class, 'runNow'])->name('seller.pricing.auto-apply.run-now');
    Route::post('/pricing/products/{product}/toggle-auto-apply', [PricingAutoApplyController::class, 'toggleProduct'])
        ->name('seller.pricing.products.toggle-auto-apply');

    Route::get('/pricing/api-keys', [PricingApiKeysController::class, 'index'])->name('seller.pricing.api-keys.index');
    Route::post('/pricing/api-keys', [PricingApiKeysController::class, 'store'])->name('seller.pricing.api-keys.store');
    Route::post('/pricing/api-keys/{id}/revoke', [PricingApiKeysController::class, 'revoke'])
        ->whereNumber('id')
        ->name('seller.pricing.api-keys.revoke');
});

