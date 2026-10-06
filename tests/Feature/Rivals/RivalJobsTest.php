<?php

use App\Actions\DiscoverProductRivals;
use App\Actions\EvaluateShopAlerts;
use App\Actions\RecomputeShopRecommendations;
use App\Actions\RefreshProductRivalSnapshot;
use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use App\Jobs\DiscoverShopRivalsJob;
use App\Jobs\DispatchDueRivalRefreshesJob;
use App\Jobs\EvaluateAllShopAlertsJob;
use App\Jobs\RefreshShopRivalSnapshotsJob;
use App\Mail\BelowFloorAlertMail;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['rivals.driver' => 'fake', 'rivals.torob_enabled' => false, 'alerts.enabled' => true]);
});

it('DiscoverShopRivalsJob discovers up to max_rival_products then recomputes', function () {
    $plan = Plan::factory()->create(['max_rival_products' => 1]);
    $shop = Shop::factory()->create(['plan_id' => $plan->id]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);

    $first = Product::factory()->for($shop)->create([
        'name' => 'First Product',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    $second = Product::factory()->for($shop)->create([
        'name' => 'Second Product',
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);

    (new DiscoverShopRivalsJob($shop->id))->handle(
        app(DiscoverProductRivals::class),
        app(RecomputeShopRecommendations::class),
        app(EvaluateShopAlerts::class),
    );

    expect(ProductRivalMatch::query()->whereBelongsTo($first)->exists())->toBeTrue()
        ->and(ProductRivalMatch::query()->whereBelongsTo($second)->exists())->toBeFalse()
        ->and($first->fresh()->recommended_price)->not->toBeNull();
});

it('DiscoverShopRivalsJob no-ops for missing shop', function () {
    expect(fn () => (new DiscoverShopRivalsJob(999999))->handle(
        app(DiscoverProductRivals::class),
        app(RecomputeShopRecommendations::class),
        app(EvaluateShopAlerts::class),
    ))->not->toThrow(Throwable::class);
});

it('RefreshShopRivalSnapshotsJob refreshes stale linked matches only', function () {
    $plan = Plan::factory()->create(['rival_refresh_hours' => 24, 'max_rival_products' => 50]);
    $shop = Shop::factory()->create(['plan_id' => $plan->id]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);

    $staleProduct = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);
    $freshProduct = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => '500000',
    ]);

    $staleMatch = ProductRivalMatch::factory()->for($staleProduct)->create([
        'source' => RivalSource::Torob,
        'status' => RivalMatchStatus::AutoLinked,
        'listing_identity' => 'fake-torob-stale',
    ]);
    $freshMatch = ProductRivalMatch::factory()->for($freshProduct)->create([
        'source' => RivalSource::Torob,
        'status' => RivalMatchStatus::AutoLinked,
        'listing_identity' => 'fake-torob-fresh',
    ]);

    $oldSnapshot = RivalSnapshot::factory()->for($staleProduct)->create([
        'source' => RivalSource::Torob,
        'listing_identity' => 'fake-torob-stale',
        'captured_at' => now()->subDays(3),
    ]);
    $freshSnapshot = RivalSnapshot::factory()->for($freshProduct)->create([
        'source' => RivalSource::Torob,
        'listing_identity' => 'fake-torob-fresh',
        'captured_at' => now()->subHour(),
    ]);

    (new RefreshShopRivalSnapshotsJob($shop->id))->handle(
        app(RefreshProductRivalSnapshot::class),
        app(RecomputeShopRecommendations::class),
        app(EvaluateShopAlerts::class),
    );

    expect(RivalSnapshot::query()->whereBelongsTo($staleProduct)->count())->toBe(2)
        ->and(RivalSnapshot::query()->whereBelongsTo($freshProduct)->count())->toBe(1)
        ->and($oldSnapshot->fresh()->exists())->toBeTrue()
        ->and($freshSnapshot->fresh()->captured_at->equalTo($freshSnapshot->captured_at))->toBeTrue();

    expect($staleMatch->fresh()->isLinked())->toBeTrue()
        ->and($freshMatch->fresh()->isLinked())->toBeTrue();
});

it('RefreshShopRivalSnapshotsJob respects max_rival_products product cap', function () {
    $plan = Plan::factory()->create(['rival_refresh_hours' => 1, 'max_rival_products' => 1]);
    $shop = Shop::factory()->create(['plan_id' => $plan->id]);
    ShopCostProfile::factory()->for($shop)->create(['min_margin_percent' => '0']);

    $first = Product::factory()->for($shop)->create(['price' => '2000000', 'direct_cost' => '500000']);
    $second = Product::factory()->for($shop)->create(['price' => '2000000', 'direct_cost' => '500000']);

    ProductRivalMatch::factory()->for($first)->create([
        'status' => RivalMatchStatus::AutoLinked,
        'listing_identity' => 'fake-torob-first',
    ]);
    ProductRivalMatch::factory()->for($second)->create([
        'status' => RivalMatchStatus::AutoLinked,
        'listing_identity' => 'fake-torob-second',
    ]);

    RivalSnapshot::factory()->for($first)->create([
        'listing_identity' => 'fake-torob-first',
        'captured_at' => now()->subDays(2),
    ]);
    RivalSnapshot::factory()->for($second)->create([
        'listing_identity' => 'fake-torob-second',
        'captured_at' => now()->subDays(2),
    ]);

    (new RefreshShopRivalSnapshotsJob($shop->id))->handle(
        app(RefreshProductRivalSnapshot::class),
        app(RecomputeShopRecommendations::class),
        app(EvaluateShopAlerts::class),
    );

    expect(RivalSnapshot::query()->whereBelongsTo($first)->count())->toBe(2)
        ->and(RivalSnapshot::query()->whereBelongsTo($second)->count())->toBe(1);
});

it('DispatchDueRivalRefreshesJob queues RefreshShopRivalSnapshotsJob per shop', function () {
    Queue::fake();

    $shops = Shop::factory()->count(2)->create();

    (new DispatchDueRivalRefreshesJob)->handle();

    Queue::assertPushed(RefreshShopRivalSnapshotsJob::class, 2);
    foreach ($shops as $shop) {
        Queue::assertPushed(
            RefreshShopRivalSnapshotsJob::class,
            fn (RefreshShopRivalSnapshotsJob $job): bool => $job->shopId === $shop->id,
        );
    }
});

it('EvaluateAllShopAlertsJob evaluates only alerts_enabled shops', function () {
    Mail::fake();

    $enabledUser = User::factory()->create(['email' => 'on@example.test']);
    $enabledShop = Shop::factory()->forUser($enabledUser)->create(['alerts_enabled' => true]);
    ShopCostProfile::factory()->for($enabledShop)->create(['min_margin_percent' => '0']);
    Product::factory()->for($enabledShop)->create([
        'price' => '100000',
        'direct_cost' => '500000',
        'below_floor' => true,
        'floor_price' => '500000',
    ]);

    $disabledUser = User::factory()->create(['email' => 'off@example.test']);
    $disabledShop = Shop::factory()->forUser($disabledUser)->create(['alerts_enabled' => false]);
    ShopCostProfile::factory()->for($disabledShop)->create(['min_margin_percent' => '0']);
    Product::factory()->for($disabledShop)->create([
        'price' => '100000',
        'direct_cost' => '500000',
        'below_floor' => true,
        'floor_price' => '500000',
    ]);

    (new EvaluateAllShopAlertsJob)->handle(app(EvaluateShopAlerts::class));

    Mail::assertSent(BelowFloorAlertMail::class, function (BelowFloorAlertMail $mail) use ($enabledUser): bool {
        return $mail->hasTo($enabledUser->email);
    });
    Mail::assertNotSent(BelowFloorAlertMail::class, function (BelowFloorAlertMail $mail) use ($disabledUser): bool {
        return $mail->hasTo($disabledUser->email);
    });
});

it('schedule registers hourly rival refresh and alert jobs', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain(DispatchDueRivalRefreshesJob::class)
        ->and($output)->toContain(EvaluateAllShopAlertsJob::class)
        ->and($output)->toContain('0 * * * *');
});
