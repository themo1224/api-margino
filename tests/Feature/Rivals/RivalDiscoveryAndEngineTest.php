<?php

use App\Actions\ComputeProductRecommendation;
use App\Actions\DiscoverProductRivals;
use App\Actions\RecomputeShopRecommendations;
use App\Enums\PricingStrategy;
use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use App\Jobs\DiscoverShopRivalsJob;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('auto-discovers torob rivals after sync without seller urls', function () {
    config(['rivals.driver' => 'fake', 'rivals.torob_enabled' => false]);

    $shop = Shop::factory()->create();
    ShopCostProfile::factory()->for($shop)->create([
        'staff_cost' => '0',
        'rent_cost' => '0',
        'utilities_cost' => '0',
        'other_overhead' => '0',
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'name' => 'عطر تست نمونه',
        'brand' => 'Acme',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);

    app(DiscoverProductRivals::class)->handle($product->fresh(['shop.plan']));

    $match = ProductRivalMatch::query()->whereBelongsTo($product)->where('source', RivalSource::Torob)->first();

    expect($match)->not->toBeNull()
        ->and($match->listing_url)->not->toBeNull()
        ->and($match->status)->toBeIn([RivalMatchStatus::AutoLinked, RivalMatchStatus::NeedsConfirm]);

    if ($match->isLinked()) {
        expect(RivalSnapshot::query()->whereBelongsTo($product)->exists())->toBeTrue();
    }
});

it('recommends rival cheapest when above floor and never goes below floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::MatchCheapest,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'staff_cost' => '0',
        'rent_cost' => '0',
        'utilities_cost' => '0',
        'other_overhead' => '0',
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1100000',
        'median_price' => '1250000',
        'competitor_count' => 4,
        'captured_at' => now(),
    ]);

    $product->load(['shop.plan', 'rivalSnapshots']);
    $result = app(ComputeProductRecommendation::class)->handle($product, $profile, '0');

    expect($result['floor_price'])->toBe('500000')
        ->and($result['recommended_price'])->toBe('1100000')
        ->and($result['rivals_stale'])->toBeFalse()
        ->and($result['cannot_match_profitably'])->toBeFalse()
        ->and(bccomp($result['recommended_price'], $result['floor_price'], 4))->toBe(1);
});

it('keeps recommendation at floor when cheapest rival is below floor', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::MatchCheapest,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '1500000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1000000',
        'median_price' => '1100000',
        'competitor_count' => 3,
        'captured_at' => now(),
    ]);

    $product->load(['shop.plan', 'rivalSnapshots']);
    $result = app(ComputeProductRecommendation::class)->handle($product, $profile, '0');

    expect($result['floor_price'])->toBe('1500000')
        ->and($result['recommended_price'])->toBe('1500000')
        ->and($result['cannot_match_profitably'])->toBeTrue()
        ->and($result['rivals_stale'])->toBeFalse();
});

it('falls back to cost floor when rivals are stale', function () {
    $shop = Shop::factory()->create([
        'pricing_strategy' => PricingStrategy::MatchCheapest,
    ]);
    ShopCostProfile::factory()->for($shop)->create([
        'min_margin_percent' => '0',
    ]);

    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '800000',
    ]);

    RivalSnapshot::factory()->for($product)->stale()->create([
        'cheapest_price' => '900000',
    ]);

    app(RecomputeShopRecommendations::class)->handle($shop->fresh(['costProfile', 'plan']));

    $product->refresh();

    expect($product->recommended_price)->toBe('800000')
        ->and($product->rivals_stale)->toBeTrue();
});

it('dispatches rival discovery after product sync', function () {
    Queue::fake();

    [$shop, $key] = connectorShop();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            [
                'external_id' => '42',
                'name' => 'Sync Rival Product',
                'price' => '1500000',
            ],
        ],
    ], connectorHeaders($shop, $key))->assertOk();

    Queue::assertPushed(DiscoverShopRivalsJob::class, fn (DiscoverShopRivalsJob $job): bool => $job->shopId === $shop->id);
});

it('surfaces cheapest median and count on the product dashboard', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'محصول رقیب',
        'price' => '2000000',
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1100000',
        'median_price' => '1250000',
        'competitor_count' => 5,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.products'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/products')
            ->has('products', 1)
            ->where('products.0.rival.cheapest_price', '1100000')
            ->where('products.0.rival.median_price', '1250000')
            ->where('products.0.rival.competitor_count', 5));
});
