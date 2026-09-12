<?php

use App\Http\Controllers\Connector\AcknowledgeAppliedPriceController;
use App\Http\Controllers\Connector\ProductRecommendationController;
use App\Http\Controllers\Connector\SyncProductsController;
use App\Http\Controllers\Connector\ValidateConnectorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['connector.key', 'throttle:connector'])
    ->prefix('connector')
    ->name('connector.')
    ->group(function (): void {
        Route::post('/validate', ValidateConnectorController::class)->name('validate');
        Route::post('/products/sync', SyncProductsController::class)->name('products.sync');
        Route::get('/products/{external_id}/recommendation', ProductRecommendationController::class)
            ->name('products.recommendation');
        Route::post('/products/{external_id}/applied', AcknowledgeAppliedPriceController::class)
            ->name('products.applied');
    });
