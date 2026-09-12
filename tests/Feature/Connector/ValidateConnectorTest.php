<?php

use App\Enums\PlanStatus;
use App\Enums\ShopStatus;
use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Support\Str;

it('returns 401 JSON when the Authorization header is missing', function () {
    $this->postJson('/v1/connector/validate')
        ->assertUnauthorized()
        ->assertExactJson([
            'error' => [
                'code' => 'invalid_api_key',
                'message' => 'The API key is missing or invalid.',
            ],
        ]);
});

it('returns 401 when the API key is invalid', function () {
    $this->postJson('/v1/connector/validate', [], [
        'Authorization' => 'Bearer not-a-real-key',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_api_key');
});

it('returns 401 when the API key is revoked', function () {
    $plainKey = 'test_'.Str::random(40);
    $shop = Shop::factory()->create();
    ApiKey::factory()->for($shop)->plaintext($plainKey)->revoked()->create();

    $this->postJson('/v1/connector/validate', [], connectorHeaders($shop, $plainKey))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_api_key');
});

it('returns 200 with shop and plan for a valid key', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/validate', [], connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertExactJson([
            'ok' => true,
            'shop' => [
                'id' => $shop->public_id,
                'name' => $shop->name,
            ],
            'plan' => [
                'id' => $shop->plan->code,
                'status' => PlanStatus::Active->value,
                'label' => $shop->plan->label,
            ],
        ]);
});

it('returns 200 when the body is omitted', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/validate', [], connectorHeaders($shop, $plainKey))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('returns 403 when the plan is inactive', function (PlanStatus $status) {
    $plan = Plan::factory()->state(['status' => $status])->create();
    [$shop, $plainKey] = connectorShop(fn () => Shop::factory()->for($plan)->create());

    $this->postJson('/v1/connector/validate', [], connectorHeaders($shop, $plainKey))
        ->assertForbidden()
        ->assertExactJson([
            'error' => [
                'code' => 'plan_inactive',
                'message' => 'The shop or plan is not allowed to use the connector.',
            ],
        ]);
})->with([
    'inactive' => PlanStatus::Inactive,
    'past_due' => PlanStatus::PastDue,
]);

it('returns 403 when the shop is inactive', function () {
    [$shop, $plainKey] = connectorShop(
        fn () => Shop::factory()->inactive()->create(),
    );

    expect($shop->status)->toBe(ShopStatus::Inactive);

    $this->postJson('/v1/connector/validate', [], connectorHeaders($shop, $plainKey))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'plan_inactive');
});

it('returns 422 when extra JSON properties are sent', function () {
    [$shop, $plainKey] = connectorShop();

    $this->postJson('/v1/connector/validate', [
        'unexpected' => true,
    ], connectorHeaders($shop, $plainKey))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.message', fn (string $message): bool => $message !== '');
});
