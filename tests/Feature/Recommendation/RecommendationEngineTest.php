<?php

use App\Actions\ComputeProductRecommendation;
use App\Actions\RecomputeShopRecommendations;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCostProfile;

it('computes floor_plus_margin recommendation at the effective floor', function () {
    $shop = Shop::factory()->create();
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'staff_cost' => '0',
        'rent_cost' => '0',
        'utilities_cost' => '0',
        'other_overhead' => '0',
        'min_margin_percent' => '20',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '1500000',
        'direct_cost' => '1000000',
        'recommended_price' => null,
        'floor_price' => null,
    ]);

    // allocated 0 + direct 1000000 → cost floor 1000000 → ×1.20 = 1200000
    $result = app(ComputeProductRecommendation::class)->handle($product, $profile, '0');

    expect($result['floor_price'])->toBe('1200000')
        ->and($result['recommended_price'])->toBe('1200000')
        ->and($result['below_floor'])->toBeFalse()
        ->and(bccomp($result['recommended_price'], $result['floor_price'], 4))->toBe(0);
});

it('sets below_floor when store price is under the effective floor', function () {
    $shop = Shop::factory()->create();
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '500000',
        'direct_cost' => '1000000',
    ]);

    $result = app(ComputeProductRecommendation::class)->handle($product, $profile, '0');

    expect($result['floor_price'])->toBe('1000000')
        ->and($result['recommended_price'])->toBe('1000000')
        ->and($result['below_floor'])->toBeTrue();
});

it('splits overhead equally across SKUs', function () {
    $shop = Shop::factory()->create();
    ShopCostProfile::factory()->for($shop)->create([
        'staff_cost' => '1000000',
        'rent_cost' => '0',
        'utilities_cost' => '0',
        'other_overhead' => '0',
        'min_margin_percent' => '0',
    ]);

    Product::factory()->for($shop)->create([
        'external_id' => '1',
        'price' => '2000000',
        'direct_cost' => '0',
    ]);
    Product::factory()->for($shop)->create([
        'external_id' => '2',
        'price' => '2000000',
        'direct_cost' => '0',
    ]);

    app(RecomputeShopRecommendations::class)->handle($shop->fresh());

    $floors = Product::query()->whereBelongsTo($shop)->orderBy('external_id')->pluck('floor_price');

    expect($floors->all())->toBe(['500000', '500000']);
});

it('uses per-product margin override over shop profile', function () {
    $shop = Shop::factory()->create();
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '10',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '5000000',
        'direct_cost' => '1000000',
        'min_margin_percent' => '50',
    ]);

    // 1000000 * 1.50 = 1500000 (not 1.10)
    $result = app(ComputeProductRecommendation::class)->handle($product, $profile, '0');

    expect($result['recommended_price'])->toBe('1500000')
        ->and($result['floor_price'])->toBe('1500000');
});

it('does not raise recommended above the floor via max_price under floor_plus_margin', function () {
    $shop = Shop::factory()->create();
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '5000000',
        'direct_cost' => '1000000',
        'max_price' => '1200000',
    ]);

    // Until rivals (B7), strategy is floor_plus_margin: recommended stays at the floor.
    // max_price only caps when a future strategy would recommend above the floor.
    $result = app(ComputeProductRecommendation::class)->handle($product, $profile, '0');

    expect($result['floor_price'])->toBe('1000000')
        ->and($result['recommended_price'])->toBe('1000000');
});

it('ignores max_price when it is below the effective floor', function () {
    $shop = Shop::factory()->create();
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '5000000',
        'direct_cost' => '1000000',
        'max_price' => '500000',
    ]);

    $result = app(ComputeProductRecommendation::class)->handle($product, $profile, '0');

    expect($result['floor_price'])->toBe('1000000')
        ->and($result['recommended_price'])->toBe('1000000');
});

it('clears recommendations without a cost profile when stub is disabled', function () {
    config(['connector.stub_recommendations' => false]);

    $shop = Shop::factory()->create();
    $product = Product::factory()->for($shop)->create([
        'price' => '1500000',
        'recommended_price' => '1500000',
        'floor_price' => null,
        'recommendation_updated_at' => now(),
    ]);

    app(RecomputeShopRecommendations::class)->handle($shop->fresh());

    $product->refresh();

    expect($product->recommended_price)->toBeNull()
        ->and($product->floor_price)->toBeNull()
        ->and($product->below_floor)->toBeFalse()
        ->and($product->recommendation_updated_at)->toBeNull();
});

it('stubs recommended_price to store price without a cost profile when stub is enabled', function () {
    config(['connector.stub_recommendations' => true]);

    $shop = Shop::factory()->create();
    $product = Product::factory()->for($shop)->create([
        'price' => '1500000',
        'recommended_price' => null,
        'recommendation_updated_at' => null,
    ]);

    app(RecomputeShopRecommendations::class)->handle($shop->fresh());

    $product->refresh();

    expect($product->recommended_price)->toBe('1500000')
        ->and($product->floor_price)->toBeNull()
        ->and($product->below_floor)->toBeFalse()
        ->and($product->recommendation_updated_at)->not->toBeNull();
});
