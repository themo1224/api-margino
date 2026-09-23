<?php

use App\Models\ApiKey;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * @return array{0: Shop, 1: string}
 */
function connectorShop(?callable $shopFactory = null): array
{
    $plainKey = 'test_'.Str::random(40);
    $shop = $shopFactory === null
        ? Shop::factory()->create()
        : $shopFactory();

    ApiKey::factory()->for($shop)->plaintext($plainKey)->create();

    return [$shop, $plainKey];
}

/**
 * @return array<string, string>
 */
function connectorHeaders(Shop $shop, string $plainKey): array
{
    return [
        'Authorization' => 'Bearer '.$plainKey,
        'Accept' => 'application/json',
    ];
}
