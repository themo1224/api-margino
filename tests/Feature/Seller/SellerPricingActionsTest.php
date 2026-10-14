<?php

use App\Enums\AppliedSource;
use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use App\Models\AppliedPrice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for all seller pricing actions', function () {
    $this->getJson('/api/pricing/products/1')->assertUnauthorized();
    $this->postJson('/api/pricing/products/1/apply-price')->assertUnauthorized();
    $this->postJson('/api/pricing/products/batch-apply-price')->assertUnauthorized();
    $this->postJson('/api/pricing/products/1/refresh-rivals')->assertUnauthorized();
    $this->postJson('/api/pricing/products/batch-refresh')->assertUnauthorized();
    $this->postJson('/api/pricing/products/1/unlink-rival')->assertUnauthorized();
    $this->getJson('/api/pricing/costs')->assertUnauthorized();
    $this->putJson('/api/pricing/costs')->assertUnauthorized();
    $this->getJson('/api/pricing/plan')->assertUnauthorized();
    $this->postJson('/api/pricing/plan/upgrade')->assertUnauthorized();
});

it('returns detailed product data including competitors, cost breakdown, and history', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'گوشی سامسونگ A54',
        'price' => '15000000',
        'recommended_price' => '14800000',
        'floor_price' => '13500000',
    ]);

    RivalSnapshot::factory()->for($product)->create([
        'source' => RivalSource::Torob,
        'cheapest_price' => '14500000',
        'median_price' => '14900000',
        'competitor_count' => 12,
        'captured_at' => now(),
    ]);

    AppliedPrice::create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'applied_price' => '15000000',
        'currency' => 'IRT',
        'source' => AppliedSource::Manual,
        'applied_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/pricing/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $product->id)
        ->assertJsonPath('data.name', 'گوشی سامسونگ A54')
        ->assertJsonStructure([
            'status',
            'data' => [
                'id',
                'name',
                'storePrice',
                'costFloor',
                'recommendedPrice',
                'competitors',
                'costBreakdown' => [
                    'baseCost',
                    'overheadPct',
                    'dollarMultiplier',
                    'targetMarginPct',
                    'calculatedFloor',
                ],
                'priceHistory',
            ],
        ]);

    expect($response->json('data.competitors'))->not->toBeEmpty();
    expect($response->json('data.priceHistory'))->not->toBeEmpty();
});

it('prevents viewing product detail belonging to another shop', function () {
    $user1 = User::factory()->create();
    $shop1 = Shop::factory()->forUser($user1)->create();
    $product1 = Product::factory()->for($shop1)->create();

    $user2 = User::factory()->create();
    Shop::factory()->forUser($user2)->create();

    Sanctum::actingAs($user2);

    $this->getJson("/api/pricing/products/{$product1->id}")
        ->assertNotFound();
});

it('applies custom price override and records applied price entry', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'price' => '1000000',
        'recommended_price' => '950000',
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/pricing/products/{$product->id}/apply-price", [
        'price' => 970000,
    ])
        ->assertOk()
        ->assertJsonPath('data.storePrice', 970000);

    expect((float) $product->fresh()->price)->toBe(970000.0);
    expect((float) $product->fresh()->last_applied_price)->toBe(970000.0);

    expect(AppliedPrice::where('product_id', $product->id)->count())->toBe(1);
    expect((float) AppliedPrice::where('product_id', $product->id)->first()->applied_price)->toBe(970000.0);
});

it('applies default recommended price when price is not passed', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'price' => '1000000',
        'recommended_price' => '920000',
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/pricing/products/{$product->id}/apply-price")
        ->assertOk()
        ->assertJsonPath('data.storePrice', 920000);

    expect((float) $product->fresh()->price)->toBe(920000.0);
});

it('applies recommended prices in batch across multiple products', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $p1 = Product::factory()->for($shop)->create(['price' => '1000', 'recommended_price' => '900']);
    $p2 = Product::factory()->for($shop)->create(['price' => '2000', 'recommended_price' => '1800']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/pricing/products/batch-apply-price', [
        'productIds' => [$p1->id, $p2->id],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect((float) $p1->fresh()->price)->toBe(900.0);
    expect((float) $p2->fresh()->price)->toBe(1800.0);
});

it('refreshes single product and batch products rivals', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create(['rivals_stale' => true]);

    Sanctum::actingAs($user);

    $this->postJson("/api/pricing/products/{$product->id}/refresh-rivals")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $product->id);

    $this->postJson('/api/pricing/products/batch-refresh', [
        'productIds' => [$product->id],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('unlinks rival match and marks product rivals stale', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create(['rivals_stale' => false]);
    $match = ProductRivalMatch::factory()->for($product)->create([
        'status' => RivalMatchStatus::AutoLinked,
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'source' => RivalSource::Torob,
        'cheapest_price' => '1000',
        'captured_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/pricing/products/{$product->id}/unlink-rival")
        ->assertOk()
        ->assertJsonPath('data.rivalStatus', 'stale');

    expect($match->fresh()->status)->toBe(RivalMatchStatus::Rejected);
    expect($product->fresh()->rivals_stale)->toBeTrue();
});

it('manages cost profile through get and put endpoints', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/costs')
        ->assertOk()
        ->assertJsonPath('data.rentMonthly', 0);

    $this->putJson('/api/pricing/costs', [
        'rentMonthly' => 12000000,
        'staffMonthly' => 25000000,
        'otherMonthly' => 3000000,
        'targetMarginPct' => 20,
    ])
        ->assertOk()
        ->assertJsonPath('data.rentMonthly', 12000000)
        ->assertJsonPath('data.staffMonthly', 25000000)
        ->assertJsonPath('data.targetMarginPct', 20);

    $profile = ShopCostProfile::where('shop_id', $shop->id)->first();
    expect($profile)->not->toBeNull();
    expect((float) $profile->rent_cost)->toBe(12000000.0);
    expect((float) $profile->min_margin_percent)->toBe(20.0);
});

it('shows plan usage and supports upgrading tier', function () {
    $starterPlan = Plan::factory()->create([
        'code' => 'starter',
        'label' => 'طرح پایه',
        'rival_refresh_hours' => 24,
        'max_rival_products' => 50,
    ]);
    $proPlan = Plan::factory()->create([
        'code' => 'pro',
        'label' => 'طرح حرفه‌ای',
        'rival_refresh_hours' => 6,
        'max_rival_products' => 200,
    ]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'plan_id' => $starterPlan->id,
    ]);


    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/plan')
        ->assertOk()
        ->assertJsonPath('data.tier', 'starter')
        ->assertJsonPath('data.productsLimit', 50)
        ->assertJsonPath('data.rivalRefreshInterval', 'daily')
        ->assertJsonPath('data.autoApplyAllowed', false);

    $this->postJson('/api/pricing/plan/upgrade', [
        'tier' => 'pro',
        'billingCycle' => 'yearly',
    ])
        ->assertOk()
        ->assertJsonPath('data.tier', 'pro')
        ->assertJsonPath('data.productsLimit', 200)
        ->assertJsonPath('data.rivalRefreshInterval', 'hourly')
        ->assertJsonPath('data.autoApplyAllowed', true)
        ->assertJsonPath('data.billingCycle', 'yearly');

    expect($shop->fresh()->plan_id)->toBe($proPlan->id);
});
