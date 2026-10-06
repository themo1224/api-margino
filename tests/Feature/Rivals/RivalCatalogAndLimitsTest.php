<?php

use App\Actions\DiscoverProductRivals;
use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Rivals\Fake\FakeRivalCatalogClient;
use App\Rivals\RivalCatalogRegistry;
use App\Rivals\Snapp\SnappCatalogClient;

beforeEach(function () {
    config(['rivals.driver' => 'fake', 'rivals.torob_enabled' => false, 'rivals.snapp_enabled' => false]);
});

it('discover skips new products when max_rival_products AutoLinked reached', function () {
    $plan = Plan::factory()->create(['max_rival_products' => 1]);
    $shop = Shop::factory()->create(['plan_id' => $plan->id]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);

    $linked = Product::factory()->for($shop)->create([
        'name' => 'Already Linked',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    ProductRivalMatch::factory()->for($linked)->create([
        'status' => RivalMatchStatus::AutoLinked,
    ]);

    $fresh = Product::factory()->for($shop)->create([
        'name' => 'Needs Discover',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);

    app(DiscoverProductRivals::class)->handle($fresh->fresh(['shop.plan']));

    expect(ProductRivalMatch::query()->whereBelongsTo($fresh)->exists())->toBeFalse();
});

it('max_rival_products 0 blocks all discover', function () {
    $plan = Plan::factory()->create(['max_rival_products' => 0]);
    $shop = Shop::factory()->create(['plan_id' => $plan->id]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    $product = Product::factory()->for($shop)->create([
        'name' => 'Blocked',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);

    app(DiscoverProductRivals::class)->handle($product->fresh(['shop.plan']));

    expect(ProductRivalMatch::query()->whereBelongsTo($product)->exists())->toBeFalse();
});

it('fake registry includes Snapp when snapp_enabled', function () {
    config(['rivals.driver' => 'fake', 'rivals.snapp_enabled' => true]);

    $clients = app(RivalCatalogRegistry::class)->enabledClients();

    expect($clients)->toHaveCount(2)
        ->and($clients[0]->source())->toBe(RivalSource::Torob)
        ->and($clients[1]->source())->toBe(RivalSource::Snapp)
        ->and($clients[0])->toBeInstanceOf(FakeRivalCatalogClient::class)
        ->and($clients[1])->toBeInstanceOf(FakeRivalCatalogClient::class);
});

it('http registry uses Snapp stub when only snapp enabled', function () {
    config([
        'rivals.driver' => 'http',
        'rivals.torob_enabled' => false,
        'rivals.snapp_enabled' => true,
    ]);

    $clients = app(RivalCatalogRegistry::class)->enabledClients();

    expect($clients)->toHaveCount(1)
        ->and($clients[0])->toBeInstanceOf(SnappCatalogClient::class)
        ->and($clients[0]->search('anything'))->toBe([])
        ->and($clients[0]->fetchPrices('x'))->toBeNull();
});

it('clientFor throws when source not enabled', function () {
    config([
        'rivals.driver' => 'fake',
        'rivals.snapp_enabled' => false,
    ]);

    expect(fn () => app(RivalCatalogRegistry::class)->clientFor(RivalSource::Snapp))
        ->toThrow(InvalidArgumentException::class);
});

it('fallback fake Torob when http and both flags false', function () {
    config([
        'rivals.driver' => 'http',
        'rivals.torob_enabled' => false,
        'rivals.snapp_enabled' => false,
    ]);

    $clients = app(RivalCatalogRegistry::class)->enabledClients();

    expect($clients)->toHaveCount(1)
        ->and($clients[0])->toBeInstanceOf(FakeRivalCatalogClient::class)
        ->and($clients[0]->source())->toBe(RivalSource::Torob);
});
