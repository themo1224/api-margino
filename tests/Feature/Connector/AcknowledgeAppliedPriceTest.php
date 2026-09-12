<?php

use App\Enums\AppliedSource;
use App\Enums\PlanStatus;
use App\Models\AppliedPrice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;

it('persists a manual apply and returns the contract payload', function () {
    [$shop, $plainKey] = connectorShop();
    Product::factory()->for($shop)->create(['external_id' => '42']);

    $this->postJson('/v1/connector/products/42/applied', [
        'applied_price' => '1450000',
        'currency' => 'IRR',
        'source' => 'manual',
        'applied_at' => '2026-09-07T12:05:00Z',
    ], connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertExactJson([
            'ok' => true,
            'external_id' => '42',
            'applied_price' => '1450000',
        ]);

    $applied = AppliedPrice::query()->whereBelongsTo($shop)->first();

    expect($applied)->not->toBeNull()
        ->and($applied->applied_price)->toBe('1450000')
        ->and($applied->currency)->toBe('IRR')
        ->and($applied->source)->toBe(AppliedSource::Manual)
        ->and(Product::query()->whereBelongsTo($shop)->where('external_id', '42')->value('last_applied_price'))
        ->toBe('1450000');
});

it('returns 404 when the product has never been synced', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/products/42/applied', [
        'applied_price' => '1450000',
        'currency' => 'IRR',
        'source' => 'manual',
        'applied_at' => '2026-09-07T12:05:00Z',
    ], connectorHeaders($shop, $plainKey))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'product_not_found');
});

it('returns 404 when the product belongs to another shop', function () {
    [$shopA] = connectorShop();
    [$shopB, $keyB] = connectorShop();
    Product::factory()->for($shopA)->create(['external_id' => '42']);

    $this->postJson('/v1/connector/products/42/applied', [
        'applied_price' => '1450000',
        'currency' => 'IRR',
        'source' => 'manual',
        'applied_at' => '2026-09-07T12:05:00Z',
    ], connectorHeaders($shopB, $keyB))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'product_not_found');
});

it('returns 422 when source is not manual', function () {
    [$shop, $plainKey] = connectorShop();
    Product::factory()->for($shop)->create(['external_id' => '42']);

    $this->postJson('/v1/connector/products/42/applied', [
        'applied_price' => '1450000',
        'currency' => 'IRR',
        'source' => 'auto',
        'applied_at' => '2026-09-07T12:05:00Z',
    ], connectorHeaders($shop, $plainKey))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');
});

it('returns 422 when applied_price is not a non-negative decimal string', function () {
    [$shop, $plainKey] = connectorShop();
    Product::factory()->for($shop)->create(['external_id' => '42']);

    $this->postJson('/v1/connector/products/42/applied', [
        'applied_price' => '-10',
        'currency' => 'IRR',
        'source' => 'manual',
        'applied_at' => '2026-09-07T12:05:00Z',
    ], connectorHeaders($shop, $plainKey))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');
});

it('returns 401 when the API key is missing', function () {
    $this->postJson('/v1/connector/products/42/applied', [
        'applied_price' => '1450000',
        'currency' => 'IRR',
        'source' => 'manual',
        'applied_at' => '2026-09-07T12:05:00Z',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_api_key');
});

it('returns 403 when the plan is inactive', function () {
    $plan = Plan::factory()->state(['status' => PlanStatus::Inactive])->create();
    [$shop, $plainKey] = connectorShop(fn () => Shop::factory()->for($plan)->create());
    Product::factory()->for($shop)->create(['external_id' => '42']);

    $this->postJson('/v1/connector/products/42/applied', [
        'applied_price' => '1450000',
        'currency' => 'IRR',
        'source' => 'manual',
        'applied_at' => '2026-09-07T12:05:00Z',
    ], connectorHeaders($shop, $plainKey))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'plan_inactive');
});
