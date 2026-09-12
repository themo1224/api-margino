<?php

use App\Models\Product;

it('creates products and returns accepted ids', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            [
                'external_id' => '42',
                'sku' => 'SKU-001',
                'name' => 'Sample Product',
                'price' => '1500000',
            ],
        ],
    ], connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertExactJson([
            'synced' => 1,
            'accepted' => ['42'],
            'rejected' => [],
        ]);

    $product = Product::query()->whereBelongsTo($shop)->where('external_id', '42')->first();

    expect($product)->not->toBeNull()
        ->and($product->name)->toBe('Sample Product')
        ->and($product->price)->toBe('1500000')
        ->and($product->currency)->toBe('IRR')
        ->and($product->recommended_price)->toBe('1500000')
        ->and($product->below_floor)->toBeFalse()
        ->and($product->floor_price)->toBeNull();
});

it('updates name and price on a second sync of the same external_id', function () {
    [$shop, $plainKey] = connectorShop();
    $headers = connectorHeaders($shop, $plainKey);

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            ['external_id' => '42', 'name' => 'Old', 'price' => '1000'],
        ],
    ], $headers)->assertOk();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            ['external_id' => '42', 'name' => 'New', 'price' => '2000'],
        ],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('synced', 1);

    expect(Product::query()->whereBelongsTo($shop)->where('external_id', '42')->count())->toBe(1)
        ->and(Product::query()->whereBelongsTo($shop)->where('external_id', '42')->value('name'))->toBe('New')
        ->and(Product::query()->whereBelongsTo($shop)->where('external_id', '42')->value('price'))->toBe('2000')
        ->and(Product::query()->whereBelongsTo($shop)->where('external_id', '42')->value('recommended_price'))->toBe('2000');
});

it('returns 200 with invalid_price rejects and still accepts valid items', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            ['external_id' => '42', 'name' => 'Good', 'price' => '1500000'],
            ['external_id' => '99', 'name' => 'Bad', 'price' => '-1'],
        ],
    ], connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertExactJson([
            'synced' => 1,
            'accepted' => ['42'],
            'rejected' => [
                [
                    'external_id' => '99',
                    'code' => 'invalid_price',
                    'message' => 'Price must be a non-negative decimal string.',
                ],
            ],
        ]);

    expect(Product::query()->whereBelongsTo($shop)->where('external_id', '99')->exists())->toBeFalse();
});

it('returns 200 with synced 0 for an empty products array', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [],
    ], connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertExactJson([
            'synced' => 0,
            'accepted' => [],
            'rejected' => [],
        ]);
});

it('returns 422 when extra JSON properties are sent', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [],
        'extra' => true,
    ], connectorHeaders($shop, $plainKey))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');
});

it('returns 422 when currency is missing', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/products/sync', [
        'products' => [],
    ], connectorHeaders($shop, $plainKey))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');
});

it('does not update another shop\'s product with the same external_id', function () {
    [$shopA, $keyA] = connectorShop();
    [$shopB, $keyB] = connectorShop();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            ['external_id' => '42', 'name' => 'Shop A', 'price' => '1000'],
        ],
    ], connectorHeaders($shopA, $keyA))->assertOk();

    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [
            ['external_id' => '42', 'name' => 'Shop B', 'price' => '9999'],
        ],
    ], connectorHeaders($shopB, $keyB))->assertOk();

    expect(Product::query()->whereBelongsTo($shopA)->where('external_id', '42')->value('name'))->toBe('Shop A')
        ->and(Product::query()->whereBelongsTo($shopB)->where('external_id', '42')->value('name'))->toBe('Shop B')
        ->and(Product::query()->where('external_id', '42')->count())->toBe(2);
});

it('returns 401 when the API key is missing', function () {
    $this->postJson('/v1/connector/products/sync', [
        'currency' => 'IRR',
        'products' => [],
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_api_key');
});
