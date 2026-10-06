<?php

use App\Actions\ComputeProductRecommendation;
use App\Actions\RecomputeShopRecommendations;
use App\Enums\PricingStrategy;
use App\Models\Product;
use App\Models\RivalSnapshot;
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
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::FloorPlusMargin,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '5000000',
        'direct_cost' => '1000000',
        'max_price' => '1200000',
    ]);

    // floor_plus_margin ignores rivals; recommended stays at the floor.
    // max_price only caps when a rival strategy would recommend above the floor.
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

it('sets cannot_match_profitably when rival target is below floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::MatchCheapest,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '1000000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '800000',
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['floor_price'])->toBe('1000000')
        ->and($result['recommended_price'])->toBe('1000000')
        ->and($result['cannot_match_profitably'])->toBeTrue()
        ->and($result['rivals_stale'])->toBeFalse();
});

it('clamps recommendation with max_price when max is above floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::MatchCheapest,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '5000000',
        'direct_cost' => '1000000',
        'max_price' => '1500000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '2000000',
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['floor_price'])->toBe('1000000')
        ->and($result['recommended_price'])->toBe('1500000')
        ->and($result['cannot_match_profitably'])->toBeFalse();
});

it('marks rivals_stale when snapshot is older than plan refresh window', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::MatchCheapest,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '1000000',
    ]);
    RivalSnapshot::factory()->for($product)->stale()->create([
        'cheapest_price' => '1800000',
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['rivals_stale'])->toBeTrue()
        ->and($result['recommended_price'])->toBe('1000000')
        ->and($result['floor_price'])->toBe('1000000')
        ->and($result['cannot_match_profitably'])->toBeFalse();
});

it('recommends undercut of cheapest above floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::UndercutPercent,
        'undercut_percent' => '1',
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1100000',
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['floor_price'])->toBe('500000')
        ->and($result['recommended_price'])->toBe('1089000')
        ->and($result['cannot_match_profitably'])->toBeFalse()
        ->and($result['rivals_stale'])->toBeFalse();
});

it('sets cannot_match when undercut target is below floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::UndercutPercent,
        'undercut_percent' => '10',
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '950000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1000000',
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['floor_price'])->toBe('950000')
        ->and($result['recommended_price'])->toBe('950000')
        ->and($result['cannot_match_profitably'])->toBeTrue();
});

it('falls back to cheapest when undercut_percent is 100 or more', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::UndercutPercent,
        'undercut_percent' => '100',
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1100000',
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['recommended_price'])->toBe('1100000')
        ->and($result['cannot_match_profitably'])->toBeFalse();
});

it('recommends median when above floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::Median,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1100000',
        'median_price' => '1250000',
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['recommended_price'])->toBe('1250000')
        ->and($result['cannot_match_profitably'])->toBeFalse();
});

it('falls back to cheapest when median_price is null', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::Median,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1100000',
        'median_price' => null,
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['recommended_price'])->toBe('1100000');
});

it('sets cannot_match when median is below floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::Median,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '1000000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '700000',
        'median_price' => '800000',
        'captured_at' => now(),
    ]);

    $result = app(ComputeProductRecommendation::class)->handle(
        $product->fresh(['shop.plan', 'rivalSnapshots']),
        $profile,
        '0',
    );

    expect($result['floor_price'])->toBe('1000000')
        ->and($result['recommended_price'])->toBe('1000000')
        ->and($result['cannot_match_profitably'])->toBeTrue();
});
