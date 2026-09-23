<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\User;

it('saves a cost profile and recomputes recommendations', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'price' => '1500000',
        'direct_cost' => '1000000',
        'recommended_price' => null,
        'floor_price' => null,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.costs.update'), [
            'staff_cost' => '0',
            'rent_cost' => '0',
            'utilities_cost' => '0',
            'other_overhead' => '0',
            'min_margin_percent' => '20',
        ])
        ->assertRedirect(route('dashboard.costs'));

    $profile = ShopCostProfile::query()->whereBelongsTo($shop)->first();

    expect($profile)->not->toBeNull()
        ->and($profile->min_margin_percent)->toBe('20');

    $product->refresh();

    expect($product->floor_price)->toBe('1200000')
        ->and($product->recommended_price)->toBe('1200000');
});

it('updates per-sku costs and recomputes', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    ShopCostProfile::factory()->for($shop)->create([
        'staff_cost' => '0',
        'rent_cost' => '0',
        'utilities_cost' => '0',
        'other_overhead' => '0',
        'min_margin_percent' => '0',
    ]);
    $product = Product::factory()->for($shop)->create([
        'price' => '2000000',
        'direct_cost' => null,
        'recommended_price' => null,
        'floor_price' => null,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.products.cost', $product), [
            'direct_cost' => '800000',
            'min_margin_percent' => '25',
            'max_price' => null,
        ])
        ->assertRedirect(route('dashboard.products'));

    $product->refresh();

    expect($product->direct_cost)->toBe('800000')
        ->and($product->min_margin_percent)->toBe('25')
        ->and($product->floor_price)->toBe('1000000')
        ->and($product->recommended_price)->toBe('1000000');
});

it('lists synced products with price floor and recommendation', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    Product::factory()->for($shop)->create([
        'name' => 'محصول نمونه',
        'price' => '1500000',
        'floor_price' => '1200000',
        'recommended_price' => '1300000',
        'below_floor' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.products'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/products')
            ->where('isEmpty', false)
            ->has('products', 1)
            ->where('products.0.name', 'محصول نمونه')
            ->where('products.0.price', '1500000')
            ->where('products.0.floor_price', '1200000')
            ->where('products.0.recommended_price', '1300000'));
});

it('shows empty states on the dashboard when key cost and products are missing', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/index')
            ->where('plan.label', 'Starter')
            ->where('empty.no_key', true)
            ->where('empty.no_products', true)
            ->where('empty.no_cost', true));
});
