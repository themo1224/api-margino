<?php

use App\Actions\DiscoverProductRivals;
use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\User;

beforeEach(function () {
    config(['rivals.driver' => 'fake', 'rivals.torob_enabled' => false]);
});

it('confirms pending rival match and refreshes snapshot', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
        'rivals_stale' => true,
    ]);
    $match = ProductRivalMatch::factory()->for($product)->needsConfirm()->create([
        'source' => RivalSource::Torob,
        'listing_identity' => 'fake-torob-confirm-me',
        'listing_title' => 'Pending listing',
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.products.rivals.confirm', [$product, $match]))
        ->assertRedirect(route('dashboard.products'))
        ->assertSessionHas('status', 'rival_match_confirmed');

    $match->refresh();

    expect($match->status)->toBe(RivalMatchStatus::AutoLinked)
        ->and($match->confirmed_at)->not->toBeNull()
        ->and(RivalSnapshot::query()->whereBelongsTo($product)->exists())->toBeTrue();
});

it('surfaces pending_rival_match on products index', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create(['name' => 'محصول در انتظار']);
    $match = ProductRivalMatch::factory()->for($product)->needsConfirm()->create([
        'confidence' => '0.6500',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.products'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/products')
            ->where('products.0.pending_rival_match.id', $match->id)
            ->where('products.0.pending_rival_match.confidence', 0.65));
});

it('returns 404 confirming another shops match', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    $otherShop = Shop::factory()->create();
    $foreignProduct = Product::factory()->for($otherShop)->create();
    $foreignMatch = ProductRivalMatch::factory()->for($foreignProduct)->needsConfirm()->create();

    $this->actingAs($user)
        ->post(route('dashboard.products.rivals.confirm', [$foreignProduct, $foreignMatch]))
        ->assertNotFound();

    expect($foreignMatch->fresh()->status)->toBe(RivalMatchStatus::NeedsConfirm)
        ->and($foreignMatch->fresh()->confirmed_at)->toBeNull();
});

it('guest cannot confirm rival', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->for($shop)->create();
    $match = ProductRivalMatch::factory()->for($product)->needsConfirm()->create();

    $this->post(route('dashboard.products.rivals.confirm', [$product, $match]))
        ->assertRedirect(route('login'));
});

it('discovers NeedsConfirm when confidence below auto_link threshold', function () {
    // Fake titles often score up to 0.99; require above the advisor cap to force NeedsConfirm.
    config(['rivals.auto_link_min_confidence' => 1.0]);

    $shop = Shop::factory()->create();
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    $product = Product::factory()->for($shop)->create([
        'name' => 'عطر تست نمونه',
        'brand' => 'Acme',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);

    app(DiscoverProductRivals::class)->handle($product->fresh(['shop.plan']));

    $match = ProductRivalMatch::query()->whereBelongsTo($product)->first();

    expect($match)->not->toBeNull()
        ->and($match->status)->toBe(RivalMatchStatus::NeedsConfirm)
        ->and($match->confirmed_at)->toBeNull()
        ->and(RivalSnapshot::query()->whereBelongsTo($product)->exists())->toBeFalse();

    expect($product->fresh()->rivals_stale)->toBeTrue();
});

it('auto-links when confidence meets default threshold', function () {
    config(['rivals.auto_link_min_confidence' => 0.78]);

    $shop = Shop::factory()->create();
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    $product = Product::factory()->for($shop)->create([
        'name' => 'عطر تست نمونه',
        'brand' => 'Acme',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);

    app(DiscoverProductRivals::class)->handle($product->fresh(['shop.plan']));

    $match = ProductRivalMatch::query()->whereBelongsTo($product)->first();

    expect($match)->not->toBeNull()
        ->and($match->status)->toBe(RivalMatchStatus::AutoLinked)
        ->and($match->confirmed_at)->not->toBeNull()
        ->and(RivalSnapshot::query()->whereBelongsTo($product)->exists())->toBeTrue();
});
