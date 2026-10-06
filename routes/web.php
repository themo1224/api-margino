<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Dashboard\ApiKeyController;
use App\Http\Controllers\Dashboard\CostProfileController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Webhooks\MarketplaceLicenseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('webhooks/marketplace/license', MarketplaceLicenseController::class)
    ->name('webhooks.marketplace.license');

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('dashboard/api-keys', [ApiKeyController::class, 'index'])->name('dashboard.api-keys');
    Route::post('dashboard/api-keys', [ApiKeyController::class, 'store'])->name('dashboard.api-keys.store');
    Route::delete('dashboard/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('dashboard.api-keys.destroy');

    Route::get('dashboard/costs', [CostProfileController::class, 'edit'])->name('dashboard.costs');
    Route::put('dashboard/costs', [CostProfileController::class, 'update'])->name('dashboard.costs.update');

    Route::get('dashboard/products', [ProductController::class, 'index'])->name('dashboard.products');
    Route::put('dashboard/products/{product}/cost', [ProductController::class, 'updateCost'])->name('dashboard.products.cost');
    Route::post('dashboard/products/{product}/rivals/{match}/confirm', [ProductController::class, 'confirmRival'])
        ->name('dashboard.products.rivals.confirm');

    Route::get('dashboard/reports', ReportController::class)->name('dashboard.reports');
});
