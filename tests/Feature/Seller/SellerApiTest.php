<?php

use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use App\Models\ApiKey;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('registers a seller and returns a sanctum token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'فروشنده پنل',
        'email' => 'panel-seller@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'shop_name' => 'فروشگاه پنل',
    ]);

    $response->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'panel-seller@example.com')
        ->assertJsonPath('user.role', 'seller')
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'phone', 'role']]);

    $user = User::query()->where('email', 'panel-seller@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->shop)->not->toBeNull()
        ->and($user->shop->name)->toBe('فروشگاه پنل')
        ->and($user->tokens()->count())->toBe(1);
});

it('logs in and returns me for the bearer token', function () {
    $user = User::factory()->create([
        'email' => 'panel-login@example.com',
        'password' => Hash::make('password123'),
    ]);
    Shop::factory()->forUser($user)->create(['name' => 'فروشگاه ورود']);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'panel-login@example.com',
        'password' => 'password123',
    ])->assertOk();

    $token = $login->json('token');

    $this->withToken($token)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('email', 'panel-login@example.com')
        ->assertJsonPath('role', 'seller');
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'bad-panel@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'bad-panel@example.com',
        'password' => 'wrong',
    ])->assertUnprocessable();
});

it('requires a bearer token for pricing endpoints', function () {
    $this->getJson('/api/pricing/products')->assertUnauthorized();
    $this->getJson('/api/pricing/overview')->assertUnauthorized();
    $this->getJson('/api/pricing/api-keys')->assertUnauthorized();
});

it('lists only the authenticated shops products with rival fields', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $other = Shop::factory()->create();

    $mine = Product::factory()->for($shop)->create([
        'name' => 'عطر من',
        'sku' => 'SKU-MINE',
        'price' => '2000000',
        'floor_price' => '1500000',
        'recommended_price' => '1800000',
        'last_synced_at' => now(),
        'rivals_stale' => false,
        'cannot_match_profitably' => false,
    ]);

    RivalSnapshot::factory()->for($mine)->create([
        'source' => RivalSource::Torob,
        'cheapest_price' => '1700000',
        'median_price' => '1900000',
        'competitor_count' => 4,
        'captured_at' => now(),
    ]);

    Product::factory()->for($other)->create(['name' => 'محصول دیگران']);

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'عطر من')
        ->assertJsonPath('data.0.sku', 'SKU-MINE')
        ->assertJsonPath('data.0.rivalSource', 'torob')
        ->assertJsonPath('data.0.rivalCheapest', 1700000)
        ->assertJsonPath('data.0.rivalCount', 4)
        ->assertJsonPath('data.0.rivalStatus', 'matched')
        ->assertJsonPath('data.0.vsStorePct', 17.65);
});

it('marks synced products without snapshots as finding', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    Product::factory()->for($shop)->create([
        'name' => 'در حال جستجو',
        'price' => '1000000',
        'last_synced_at' => now(),
        'rivals_stale' => false,
        'cannot_match_profitably' => false,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/products')
        ->assertOk()
        ->assertJsonPath('data.0.rivalStatus', 'finding')
        ->assertJsonPath('data.0.rivalCheapest', null);
});

it('returns overview counts and onboarding flags for the shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create(['name' => 'فروشگاه نمای کلی']);
    ApiKey::factory()->for($shop)->create();

    $matched = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'recommended_price' => '1900000',
        'floor_price' => '1500000',
        'last_synced_at' => now(),
    ]);
    RivalSnapshot::factory()->for($matched)->create([
        'cheapest_price' => '1600000',
        'median_price' => '1700000',
        'competitor_count' => 2,
        'captured_at' => now(),
    ]);

    Product::factory()->for($shop)->create([
        'price' => '1000000',
        'recommended_price' => '1000000',
        'last_synced_at' => now(),
        'rivals_stale' => false,
        'cannot_match_profitably' => false,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/overview')
        ->assertOk()
        ->assertJsonPath('data.shopName', 'فروشگاه نمای کلی')
        ->assertJsonPath('data.productsTotal', 2)
        ->assertJsonPath('data.productsWithRivals', 1)
        ->assertJsonPath('data.hasActiveApiKey', true)
        ->assertJsonPath('data.findingCount', 1);
});

it('issues and revokes shop api keys for the panel', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    Sanctum::actingAs($user);

    $created = $this->postJson('/api/pricing/api-keys', [
        'name' => 'ووکامرس',
    ])->assertCreated();

    $created->assertJsonPath('data.name', 'ووکامرس')
        ->assertJsonStructure(['secret', 'data' => ['id', 'prefix', 'revoked']]);

    $id = $created->json('data.id');
    $plain = $created->json('secret');

    expect($plain)->toStartWith('pk_');

    $this->getJson('/api/pricing/api-keys')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.revoked', false);

    $this->postJson('/api/pricing/api-keys/'.$id.'/revoke')
        ->assertOk()
        ->assertJsonPath('data.0.revoked', true);

    $this->postJson('/v1/connector/validate', [], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertUnauthorized();
});

it('does not revoke another shops api key', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();
    $foreign = ApiKey::factory()->for(Shop::factory()->create())->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/pricing/api-keys/'.$foreign->id.'/revoke')
        ->assertNotFound();

    expect($foreign->fresh()->revoked_at)->toBeNull();
});

it('lists needs_confirm with pendingRivalMatch when no snapshot yet', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'نیاز به تأیید',
        'price' => '2000000',
        'last_synced_at' => now(),
        'rivals_stale' => false,
        'cannot_match_profitably' => false,
    ]);
    $match = ProductRivalMatch::factory()->for($product)->needsConfirm()->create([
        'source' => RivalSource::Torob,
        'listing_title' => 'لیست پیشنهادی',
        'confidence' => '0.6500',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/products')
        ->assertOk()
        ->assertJsonPath('data.0.rivalStatus', 'needs_confirm')
        ->assertJsonPath('data.0.pendingRivalMatch.id', (string) $match->id)
        ->assertJsonPath('data.0.pendingRivalMatch.listingTitle', 'لیست پیشنهادی')
        ->assertJsonPath('data.0.pendingRivalMatch.confidence', 0.65);
});

it('confirms a pending rival match via seller api', function () {
    config(['rivals.driver' => 'fake', 'rivals.torob_enabled' => false]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'last_synced_at' => now(),
    ]);
    $match = ProductRivalMatch::factory()->for($product)->needsConfirm()->create([
        'source' => RivalSource::Torob,
        'listing_identity' => 'fake-torob-panel-confirm',
        'listing_title' => 'Pending listing',
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/pricing/products/{$product->id}/rivals/{$match->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $product->id);

    $match->refresh();

    expect($match->status)->toBe(RivalMatchStatus::AutoLinked)
        ->and($match->confirmed_at)->not->toBeNull()
        ->and(RivalSnapshot::query()->whereBelongsTo($product)->exists())->toBeTrue();
});

it('returns 404 when confirming another shops rival match', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    $foreignProduct = Product::factory()->for(Shop::factory()->create())->create();
    $foreignMatch = ProductRivalMatch::factory()->for($foreignProduct)->needsConfirm()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/pricing/products/{$foreignProduct->id}/rivals/{$foreignMatch->id}/confirm")
        ->assertNotFound();

    expect($foreignMatch->fresh()->status)->toBe(RivalMatchStatus::NeedsConfirm);
});
