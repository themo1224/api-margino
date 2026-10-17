<?php

use App\Models\Product;

it('returns list of products waiting for price sync to woocommerce', function () {
    [$shop, $plainKey] = connectorShop();

    // Create one product with pending sync, and one without
    $pending = Product::factory()->for($shop)->create([
        'external_id' => '101',
        'sku' => 'PRF-01',
        'price' => '4500000',
        'pending_price_sync' => true,
        'pending_price' => '4350000',
        'currency' => 'IRT',
    ]);

    $synced = Product::factory()->for($shop)->create([
        'external_id' => '102',
        'sku' => 'PRF-02',
        'price' => '3200000',
        'pending_price_sync' => false,
        'pending_price' => null,
        'currency' => 'IRT',
    ]);

    $this->getJson('/v1/connector/prices/pending', connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.external_id', '101')
        ->assertJsonPath('items.0.sku', 'PRF-01')
        ->assertJsonPath('items.0.price', '4350000')
        ->assertJsonPath('items.0.currency', 'IRT');
});

it('acknowledges synced prices and clears pending flag', function () {
    [$shop, $plainKey] = connectorShop();

    $p1 = Product::factory()->for($shop)->create([
        'external_id' => '201',
        'pending_price_sync' => true,
        'pending_price' => '5000000',
    ]);
    $p2 = Product::factory()->for($shop)->create([
        'external_id' => '202',
        'pending_price_sync' => true,
        'pending_price' => '6000000',
    ]);

    $this->postJson('/v1/connector/prices/ack', [
        'synced_external_ids' => ['201', '202'],
    ], connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('acknowledged', 2);

    expect($p1->fresh()->pending_price_sync)->toBeFalse()
        ->and($p1->fresh()->pending_price)->toBeNull()
        ->and($p2->fresh()->pending_price_sync)->toBeFalse()
        ->and($p2->fresh()->pending_price)->toBeNull();
});

it('does not return pending prices belonging to another shop', function () {
    [$shopA] = connectorShop();
    [$shopB, $keyB] = connectorShop();

    Product::factory()->for($shopA)->create([
        'external_id' => '301',
        'pending_price_sync' => true,
        'pending_price' => '1000000',
    ]);

    $this->getJson('/v1/connector/prices/pending', connectorHeaders($shopB, $keyB))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('count', 0)
        ->assertJsonPath('items', []);
});
