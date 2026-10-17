<?php

use App\Enums\AppliedSource;
use App\Enums\LicenseSource;
use App\Enums\LicenseStatus;
use App\Models\AppliedPrice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\ShopLicense;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->starterPlan = Plan::factory()->create([
        'code' => 'starter',
        'label' => 'طرح پایه',
        'rival_refresh_hours' => 24,
        'max_rival_products' => 50,
    ]);

    $this->proPlan = Plan::factory()->create([
        'code' => 'pro',
        'label' => 'طرح حرفه‌ای',
        'rival_refresh_hours' => 6,
        'max_rival_products' => 200,
    ]);
});

it('requires authentication for auto-apply endpoints', function () {
    $this->getJson('/api/pricing/auto-apply')->assertUnauthorized();
    $this->putJson('/api/pricing/auto-apply')->assertUnauthorized();
    $this->postJson('/api/pricing/auto-apply/run-now')->assertUnauthorized();
    $this->postJson('/api/pricing/products/1/toggle-auto-apply')->assertUnauthorized();
});

it('returns auto-apply settings and correct plan eligibility', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'plan_id' => $this->starterPlan->id,
        'auto_apply_enabled' => false,
        'auto_apply_max_drop_percent' => '15.00',
    ]);

    Product::factory()->for($shop)->create([
        'auto_apply_excluded' => false,
    ]);
    Product::factory()->for($shop)->create([
        'auto_apply_excluded' => true,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/auto-apply')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.maxDropPercent', 15)
        ->assertJsonPath('data.planAllowed', false)
        ->assertJsonPath('data.planCode', 'starter')
        ->assertJsonPath('data.totalProductsCount', 2)
        ->assertJsonPath('data.excludedProductsCount', 1);
});

it('rejects enabling auto-apply if store is on starter plan', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'plan_id' => $this->starterPlan->id,
        'auto_apply_enabled' => false,
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/pricing/auto-apply', [
        'enabled' => true,
        'maxDropPercent' => 15,
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'plan_upgrade_required');

    expect($shop->fresh()->auto_apply_enabled)->toBeFalse();
});

it('allows enabling auto-apply when store is on active Pro plan', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'plan_id' => $this->proPlan->id,
        'auto_apply_enabled' => false,
    ]);

    ShopLicense::factory()->create([
        'shop_id' => $shop->id,
        'source' => LicenseSource::Manual,
        'plan_code' => 'pro',
        'status' => LicenseStatus::Active,
        'ends_at' => now()->addMonth(),
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/pricing/auto-apply', [
        'enabled' => true,
        'maxDropPercent' => 12.5,
        'onlyFreshRivals' => true,
        'notifyDaily' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.maxDropPercent', 12.5)
        ->assertJsonPath('data.planAllowed', true);

    $refreshed = $shop->fresh();
    expect($refreshed->auto_apply_enabled)->toBeTrue();
    expect((float) $refreshed->auto_apply_max_drop_percent)->toBe(12.5);
    expect($refreshed->auto_apply_notify_daily)->toBeFalse();
});

it('toggles product auto-apply exclusion', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'plan_id' => $this->proPlan->id,
    ]);

    $product = Product::factory()->for($shop)->create([
        'auto_apply_excluded' => false,
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/pricing/products/{$product->id}/toggle-auto-apply", [
        'excluded' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.autoApplyExcluded', true);

    expect($product->fresh()->auto_apply_excluded)->toBeTrue();

    // Toggle back
    $this->postJson("/api/pricing/products/{$product->id}/toggle-auto-apply", [
        'excluded' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.autoApplyExcluded', false);

    expect($product->fresh()->auto_apply_excluded)->toBeFalse();
});

it('executes run-now cycle and safely applies recommendations', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'plan_id' => $this->proPlan->id,
        'auto_apply_enabled' => true,
        'auto_apply_max_drop_percent' => '15.00',
        'auto_apply_only_fresh_rivals' => true,
    ]);

    ShopLicense::factory()->create([
        'shop_id' => $shop->id,
        'source' => LicenseSource::Manual,
        'plan_code' => 'pro',
        'status' => LicenseStatus::Active,
        'ends_at' => now()->addMonth(),
    ]);

    ShopCostProfile::factory()->create([
        'shop_id' => $shop->id,
    ]);

    // Product 1: Safe recommendation (price: 100000 -> 95000, 5% drop, floor: 80000)
    $safeProduct = Product::factory()->for($shop)->create([
        'price' => '100000',
        'recommended_price' => '95000',
        'floor_price' => '80000',
        'below_floor' => false,
        'cannot_match_profitably' => false,
        'rivals_stale' => false,
        'auto_apply_excluded' => false,
    ]);

    // Product 2: Floor danger (below_floor = true) -> MUST NOT auto-apply
    $dangerProduct = Product::factory()->for($shop)->create([
        'price' => '100000',
        'recommended_price' => '70000',
        'floor_price' => '80000',
        'below_floor' => true,
        'cannot_match_profitably' => false,
        'rivals_stale' => false,
        'auto_apply_excluded' => false,
    ]);

    // Product 3: Circuit breaker drop exceeded (price: 100000 -> 80000 = 20% drop > 15% limit) -> MUST NOT auto-apply
    $spikeProduct = Product::factory()->for($shop)->create([
        'price' => '100000',
        'recommended_price' => '80000',
        'floor_price' => '70000',
        'below_floor' => false,
        'cannot_match_profitably' => false,
        'rivals_stale' => false,
        'auto_apply_excluded' => false,
    ]);

    // Product 4: Excluded SKU -> MUST NOT auto-apply
    $excludedProduct = Product::factory()->for($shop)->create([
        'price' => '100000',
        'recommended_price' => '95000',
        'floor_price' => '80000',
        'below_floor' => false,
        'cannot_match_profitably' => false,
        'rivals_stale' => false,
        'auto_apply_excluded' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/pricing/auto-apply/run-now')
        ->assertOk()
        ->assertJsonPath('data.applied_count', 1);

    expect((float) $safeProduct->fresh()->price)->toBe(95000.0);
    expect($safeProduct->fresh()->pending_price_sync)->toBeTrue();
    expect((float) $safeProduct->fresh()->pending_price)->toBe(95000.0);
    expect($safeProduct->fresh()->last_auto_applied_at)->not->toBeNull();

    // Verify AppliedPrice record created with source = auto
    $applied = AppliedPrice::where('product_id', $safeProduct->id)->first();
    expect($applied)->not->toBeNull();
    expect($applied->source)->toBe(AppliedSource::Auto);
    expect((float) $applied->applied_price)->toBe(95000.0);

    // Untouched products check
    expect((float) $dangerProduct->fresh()->price)->toBe(100000.0);
    expect($dangerProduct->fresh()->pending_price_sync)->toBeFalse();

    expect((float) $spikeProduct->fresh()->price)->toBe(100000.0);
    expect($spikeProduct->fresh()->pending_price_sync)->toBeFalse();

    expect((float) $excludedProduct->fresh()->price)->toBe(100000.0);
    expect($excludedProduct->fresh()->pending_price_sync)->toBeFalse();
});
