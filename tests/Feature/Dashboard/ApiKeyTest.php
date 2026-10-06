<?php

use App\Actions\HashApiKey;
use App\Models\ApiKey;
use App\Models\Shop;
use App\Models\User;

it('issues an api key, reveals plaintext once, and authenticates the connector', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();

    $response = $this->actingAs($user)
        ->post(route('dashboard.api-keys.store'), [
            'name' => 'کلید وردپرس',
        ]);

    $response->assertRedirect(route('dashboard.api-keys'))
        ->assertSessionHas('revealed_api_key');

    $plainKey = session('revealed_api_key');

    expect($plainKey)->toBeString()->toStartWith('pk_');

    $apiKey = ApiKey::query()->whereBelongsTo($shop)->first();

    expect($apiKey)->not->toBeNull()
        ->and($apiKey->name)->toBe('کلید وردپرس')
        ->and($apiKey->revoked_at)->toBeNull()
        ->and(app(HashApiKey::class)->equals($plainKey, $apiKey->key_hash))->toBeTrue();

    $this->postJson('/v1/connector/validate', [], [
        'Authorization' => 'Bearer '.$plainKey,
    ])->assertOk()->assertJsonPath('ok', true);

    $this->actingAs($user)
        ->get(route('dashboard.api-keys'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/api-keys')
            ->where('revealedKey', $plainKey)
            ->has('keys', 1));
});

it('revokes an api key so the connector rejects it', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $plainKey = 'pk_test_revoke_key_abcdefghijklmnopqrstuvwxyz';
    $apiKey = ApiKey::factory()->for($shop)->plaintext($plainKey)->create();

    $this->actingAs($user)
        ->delete(route('dashboard.api-keys.destroy', $apiKey))
        ->assertRedirect(route('dashboard.api-keys'));

    expect($apiKey->fresh()->revoked_at)->not->toBeNull();

    $this->postJson('/v1/connector/validate', [], [
        'Authorization' => 'Bearer '.$plainKey,
    ])->assertUnauthorized()->assertJsonPath('error.code', 'invalid_api_key');
});

it('does not allow revoking another shops key', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    $otherShop = Shop::factory()->create();
    $foreignKey = ApiKey::factory()->for($otherShop)->create();

    $this->actingAs($user)
        ->delete(route('dashboard.api-keys.destroy', $foreignKey))
        ->assertNotFound();

    expect($foreignKey->fresh()->revoked_at)->toBeNull();
});

it('lists api keys for the authenticated shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    ApiKey::factory()->for($shop)->create(['name' => 'کلید اصلی']);

    $this->actingAs($user)
        ->get(route('dashboard.api-keys'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/api-keys')
            ->has('keys', 1)
            ->where('keys.0.name', 'کلید اصلی')
            ->where('keys.0.is_active', true)
            ->where('revealedKey', null)
            ->missing('keys.0.key_hash')
            ->missing('keys.0.plaintext'));
});

it('requires auth to issue an api key', function () {
    $this->post(route('dashboard.api-keys.store'), [
        'name' => 'کلید',
    ])->assertRedirect(route('login'));
});

it('does not list another shops keys', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    ApiKey::factory()->for($shop)->create(['name' => 'کلید من']);

    $otherShop = Shop::factory()->create();
    ApiKey::factory()->for($otherShop)->create(['name' => 'کلید دیگران']);

    $this->actingAs($user)
        ->get(route('dashboard.api-keys'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('keys', 1)
            ->where('keys.0.name', 'کلید من'));
});
