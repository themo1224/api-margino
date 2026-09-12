<?php

use App\Models\Product;

it('returns the stub recommendation after sync', function () {
    [$shop, $plainKey] = connectorShop();
    $headers = connectorHeaders($shop, $plainKey);

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            ['external_id' => '42', 'name' => 'Sample Product', 'price' => '1500000'],
        ],
    ], $headers)->assertOk();

    $response = $this->getJson('/v1/connector/products/42/recommendation', $headers)
        ->assertOk()
        ->assertJsonPath('external_id', '42')
        ->assertJsonPath('recommended_price', '1500000')
        ->assertJsonPath('currency', 'IRR')
        ->assertJsonPath('below_floor', false)
        ->assertJsonPath('floor_price', null);

    expect($response->json('updated_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
});

it('returns 404 before the product is synced', function () {
    [$shop, $plainKey] = connectorShop();

    $this->getJson('/v1/connector/products/42/recommendation', connectorHeaders($shop, $plainKey))
        ->assertNotFound()
        ->assertExactJson([
            'error' => [
                'code' => 'product_not_found',
                'message' => 'The product is not known to the API.',
            ],
        ]);
});

it('returns 404 when the product belongs to another shop', function () {
    [$shopA, $keyA] = connectorShop();
    [$shopB, $keyB] = connectorShop();

    Product::factory()->for($shopA)->create(['external_id' => '42']);

    $this->getJson('/v1/connector/products/42/recommendation', connectorHeaders($shopB, $keyB))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'product_not_found');
});

it('returns 401 when the API key is missing', function () {
    $this->getJson('/v1/connector/products/42/recommendation')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_api_key');
});
