<?php

use App\Enums\RivalSource;
use App\Models\AppliedPrice;
use App\Models\Product;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\User;

it('shows a thin report for below floor above rival and apply rate', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    ShopCostProfile::factory()->for($shop)->create();

    $below = Product::factory()->for($shop)->create([
        'name' => 'زیر کف',
        'price' => '900000',
        'floor_price' => '1000000',
        'recommended_price' => '1000000',
        'below_floor' => true,
        'recommendation_updated_at' => now()->subDay(),
    ]);

    $above = Product::factory()->for($shop)->create([
        'name' => 'گران‌تر از رقیب',
        'price' => '2000000',
        'floor_price' => '1000000',
        'recommended_price' => '1500000',
        'below_floor' => false,
        'recommendation_updated_at' => now()->subDay(),
    ]);

    RivalSnapshot::factory()->for($above)->create([
        'source' => RivalSource::Torob,
        'cheapest_price' => '1500000',
        'captured_at' => now(),
    ]);

    AppliedPrice::factory()->for($shop)->for($below)->create([
        'applied_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.reports', ['days' => 30]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/reports')
            ->where('report.days', 30)
            ->where('report.below_floor.count', 1)
            ->where('report.above_rival.count', 1)
            ->where('report.apply_rate.applies', 1)
            ->where('report.apply_rate.recommendations', 2));
});

it('defaults days when omitted', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.reports'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/reports')
            ->where('report.days', 30)
            ->where('report.below_floor.count', 0)
            ->where('report.above_rival.count', 0)
            ->where('report.apply_rate.applies', 0)
            ->where('report.apply_rate.recommendations', 0));
});

it('shows empty report for a shop with no products', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.reports', ['days' => 7]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.days', 7)
            ->where('report.below_floor.count', 0)
            ->where('report.above_rival.count', 0)
            ->where('report.apply_rate.percent', null));
});

it('scopes apply rate to the days window', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();

    $product = Product::factory()->for($shop)->create([
        'recommended_price' => '1000000',
        'recommendation_updated_at' => now()->subDay(),
        'below_floor' => false,
    ]);

    AppliedPrice::factory()->for($shop)->for($product)->create([
        'applied_at' => now()->subDays(40),
    ]);
    AppliedPrice::factory()->for($shop)->for($product)->create([
        'applied_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.reports', ['days' => 30]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.apply_rate.applies', 1)
            ->where('report.apply_rate.recommendations', 1));
});

it('does not leak another shops products into the report', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    Product::factory()->for($shop)->create([
        'below_floor' => false,
        'recommended_price' => '1000000',
        'recommendation_updated_at' => now()->subDay(),
    ]);

    $otherShop = Shop::factory()->create();
    Product::factory()->for($otherShop)->create([
        'below_floor' => true,
        'floor_price' => '2000000',
        'price' => '1000000',
        'recommended_price' => '2000000',
        'recommendation_updated_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.reports', ['days' => 30]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.below_floor.count', 0)
            ->where('report.apply_rate.recommendations', 1));
});

it('requires auth for reports', function () {
    $this->get(route('dashboard.reports'))->assertRedirect(route('login'));
});
