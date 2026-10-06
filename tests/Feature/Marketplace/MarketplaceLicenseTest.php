<?php

use App\Enums\LicenseSource;
use App\Enums\LicenseStatus;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopLicense;
use App\Models\User;

beforeEach(function () {
    config(['marketplace.webhook_secret' => 'test-marketplace-secret']);
});

it('rejects marketplace webhook without secret', function () {
    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_1',
        'shop_public_id' => 'shop_x',
        'ends_at' => now()->addMonth()->toIso8601String(),
    ])->assertUnauthorized();
});

it('activates a time-bound zhaket license on a shop', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->starter()->create();
    $shop = Shop::factory()->forUser($user)->create(['plan_id' => $plan->id]);

    $ends = now()->addMonth();

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_abc',
        'shop_public_id' => $shop->public_id,
        'plan_code' => 'plan_starter',
        'ends_at' => $ends->toIso8601String(),
    ], [
        'X-Marketplace-Secret' => 'test-marketplace-secret',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('license.source', 'zhaket');

    $license = ShopLicense::query()->where('external_license_id', 'zhk_abc')->first();

    expect($license)->not->toBeNull()
        ->and($license->source)->toBe(LicenseSource::Zhaket)
        ->and($license->shop_id)->toBe($shop->id)
        ->and($license->ends_at)->not->toBeNull();
});

it('refuses marketplace license without ends_at', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'rtl',
        'external_license_id' => 'rtl_1',
        'shop_public_id' => $shop->public_id,
        'plan_code' => 'plan_starter',
    ], [
        'X-Marketplace-Secret' => 'test-marketplace-secret',
    ])->assertStatus(422);
});

it('activates RTL license with ends_at', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->starter()->create();
    $shop = Shop::factory()->forUser($user)->create(['plan_id' => $plan->id]);
    $ends = now()->addMonth();

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'rtl',
        'external_license_id' => 'rtl_abc',
        'shop_public_id' => $shop->public_id,
        'plan_code' => 'plan_starter',
        'ends_at' => $ends->toIso8601String(),
    ], [
        'X-Marketplace-Secret' => 'test-marketplace-secret',
    ])->assertOk()
        ->assertJsonPath('license.source', 'rtl')
        ->assertJsonPath('shop.plan', 'plan_starter');

    $license = ShopLicense::query()->where('external_license_id', 'rtl_abc')->first();

    expect($license)->not->toBeNull()
        ->and($license->source)->toBe(LicenseSource::Rtl)
        ->and($shop->fresh()->plan_id)->toBe($plan->id);
});

it('renews upserts same external_license_id', function () {
    $user = User::factory()->create();
    $starter = Plan::factory()->starter()->create();
    $pro = Plan::factory()->pro()->create();
    $shop = Shop::factory()->forUser($user)->create(['plan_id' => $starter->id]);

    $headers = ['X-Marketplace-Secret' => 'test-marketplace-secret'];

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_renew',
        'shop_public_id' => $shop->public_id,
        'plan_code' => 'plan_starter',
        'ends_at' => now()->addWeek()->toIso8601String(),
    ], $headers)->assertOk();

    $laterEnds = now()->addMonths(2);

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_renew',
        'shop_public_id' => $shop->public_id,
        'plan_code' => 'plan_pro',
        'ends_at' => $laterEnds->toIso8601String(),
    ], $headers)->assertOk()
        ->assertJsonPath('shop.plan', 'plan_pro');

    expect(ShopLicense::query()->where('external_license_id', 'zhk_renew')->count())->toBe(1);

    $license = ShopLicense::query()->where('external_license_id', 'zhk_renew')->first();

    expect($license->plan_code)->toBe('plan_pro')
        ->and($license->ends_at->toDateString())->toBe($laterEnds->toDateString())
        ->and($shop->fresh()->plan_id)->toBe($pro->id);
});

it('422 for unknown shop_public_id', function () {
    Plan::factory()->starter()->create();

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_missing_shop',
        'shop_public_id' => 'shop_does_not_exist',
        'plan_code' => 'plan_starter',
        'ends_at' => now()->addMonth()->toIso8601String(),
    ], [
        'X-Marketplace-Secret' => 'test-marketplace-secret',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('shop_public_id');
});

it('422 for unknown or inactive plan_code', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    Plan::factory()->inactive()->create(['code' => 'plan_dead']);

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_bad_plan',
        'shop_public_id' => $shop->public_id,
        'plan_code' => 'plan_dead',
        'ends_at' => now()->addMonth()->toIso8601String(),
    ], [
        'X-Marketplace-Secret' => 'test-marketplace-secret',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('plan_code');
});

it('401 for wrong marketplace secret', function () {
    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_1',
        'shop_public_id' => 'shop_x',
        'ends_at' => now()->addMonth()->toIso8601String(),
    ], [
        'X-Marketplace-Secret' => 'wrong-secret',
    ])->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthorized');
});

it('401 when marketplace.webhook_secret is empty', function () {
    config(['marketplace.webhook_secret' => '']);

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'zhaket',
        'external_license_id' => 'zhk_1',
        'shop_public_id' => 'shop_x',
        'ends_at' => now()->addMonth()->toIso8601String(),
    ], [
        'X-Marketplace-Secret' => 'anything',
    ])->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthorized');
});

it('manual source may omit ends_at', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->starter()->create();
    $shop = Shop::factory()->forUser($user)->create(['plan_id' => $plan->id]);

    $this->postJson(route('webhooks.marketplace.license'), [
        'source' => 'manual',
        'external_license_id' => 'manual_1',
        'shop_public_id' => $shop->public_id,
        'plan_code' => 'plan_starter',
    ], [
        'X-Marketplace-Secret' => 'test-marketplace-secret',
    ])->assertOk()
        ->assertJsonPath('license.source', 'manual')
        ->assertJsonPath('license.ends_at', null);

    expect(ShopLicense::query()->where('external_license_id', 'manual_1')->value('ends_at'))->toBeNull();
});

it('isCurrentlyActive is false when ends_at past', function () {
    $license = ShopLicense::factory()->create([
        'status' => LicenseStatus::Active,
        'ends_at' => now()->subDay(),
    ]);

    expect($license->isCurrentlyActive())->toBeFalse();
});

it('isCurrentlyActive is false when status revoked', function () {
    $license = ShopLicense::factory()->create([
        'status' => LicenseStatus::Revoked,
        'ends_at' => now()->addMonth(),
    ]);

    expect($license->isCurrentlyActive())->toBeFalse();
});
