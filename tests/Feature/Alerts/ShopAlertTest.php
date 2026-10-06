<?php

use App\Actions\EvaluateShopAlerts;
use App\Actions\RecomputeShopRecommendations;
use App\Enums\AlertType;
use App\Mail\BelowFloorAlertMail;
use App\Mail\CostProfileStaleAlertMail;
use App\Mail\RivalUndercutAlertMail;
use App\Models\Product;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\ShopAlertDispatch;
use App\Models\ShopCostProfile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('emails below-floor alert after cost-driven recompute', function () {
    Mail::fake();
    config(['alerts.enabled' => true]);

    $user = User::factory()->create(['email' => 'seller@example.test']);
    $shop = Shop::factory()->forUser($user)->create(['alerts_enabled' => true]);
    ShopCostProfile::factory()->for($shop)->create([
        'staff_cost' => '0',
        'rent_cost' => '0',
        'utilities_cost' => '0',
        'other_overhead' => '0',
        'min_margin_percent' => '0',
    ]);
    Product::factory()->for($shop)->create([
        'price' => '500000',
        'direct_cost' => '1000000',
    ]);

    app(RecomputeShopRecommendations::class)->handle($shop->fresh(['costProfile']));
    app(EvaluateShopAlerts::class)->handle($shop->fresh(['costProfile', 'user', 'products']));

    Mail::assertSent(BelowFloorAlertMail::class, function (BelowFloorAlertMail $mail) use ($user): bool {
        return $mail->hasTo($user->email);
    });

    expect(ShopAlertDispatch::query()->where('alert_type', AlertType::BelowFloor)->exists())->toBeTrue();
});

it('emails rival undercut when cheapest beats threshold', function () {
    Mail::fake();
    config(['alerts.enabled' => true]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'alerts_enabled' => true,
        'rival_undercut_threshold_percent' => '5',
    ]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
        'below_floor' => false,
        'rivals_stale' => false,
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'cheapest_price' => '1500000',
        'captured_at' => now(),
    ]);

    app(EvaluateShopAlerts::class)->handle($shop->fresh(['costProfile', 'user', 'products']));

    Mail::assertSent(RivalUndercutAlertMail::class);
});

it('emails when cost profile is stale', function () {
    Mail::fake();
    config(['alerts.enabled' => true]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'alerts_enabled' => true,
        'cost_stale_days' => 7,
    ]);
    $profile = ShopCostProfile::factory()->for($shop)->create();
    $profile->forceFill(['updated_at' => now()->subDays(10)])->save();

    app(EvaluateShopAlerts::class)->handle($shop->fresh(['costProfile', 'user', 'products']));

    Mail::assertSent(CostProfileStaleAlertMail::class);
});

it('dedupes alerts within the configured window', function () {
    Mail::fake();
    config(['alerts.enabled' => true, 'alerts.dedupe_hours' => 24]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create(['alerts_enabled' => true]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    Product::factory()->for($shop)->create([
        'price' => '100000',
        'direct_cost' => '500000',
        'below_floor' => true,
        'floor_price' => '500000',
    ]);

    $evaluate = app(EvaluateShopAlerts::class);
    $evaluate->handle($shop->fresh(['costProfile', 'user', 'products']));
    $evaluate->handle($shop->fresh(['costProfile', 'user', 'products']));

    Mail::assertSent(BelowFloorAlertMail::class, 1);
});

it('does not email when shop alerts_enabled is false', function () {
    Mail::fake();
    config(['alerts.enabled' => true]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create(['alerts_enabled' => false]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    Product::factory()->for($shop)->create([
        'price' => '100000',
        'direct_cost' => '500000',
        'below_floor' => true,
        'floor_price' => '500000',
    ]);

    app(EvaluateShopAlerts::class)->handle($shop->fresh(['costProfile', 'user', 'products']));

    Mail::assertNothingSent();
});

it('does not email when alerts.enabled config is false', function () {
    Mail::fake();
    config(['alerts.enabled' => false]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create(['alerts_enabled' => true]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);
    Product::factory()->for($shop)->create([
        'price' => '100000',
        'direct_cost' => '500000',
        'below_floor' => true,
        'floor_price' => '500000',
    ]);

    app(EvaluateShopAlerts::class)->handle($shop->fresh(['costProfile', 'user', 'products']));

    Mail::assertNothingSent();
});
