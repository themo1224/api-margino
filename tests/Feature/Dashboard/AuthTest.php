<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the register page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/register'));
});

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

it('registers a seller with one starter shop and logs them in', function () {
    $response = $this->post(route('register'), [
        'name' => 'علی فروشنده',
        'email' => 'seller@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'shop_name' => 'فروشگاه تست',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'seller@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->shop)->not->toBeNull()
        ->and($user->shop->name)->toBe('فروشگاه تست')
        ->and($user->shop->plan->code)->toBe('plan_starter')
        ->and($user->shop->plan->label)->toBe('Starter');
});

it('rejects registration with duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->from(route('register'))
        ->post(route('register'), [
            'name' => 'فروشنده',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'shop_name' => 'فروشگاه',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects registration with invalid password', function () {
    $this->from(route('register'))
        ->post(route('register'), [
            'name' => 'فروشنده',
            'email' => 'new@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'shop_name' => 'فروشگاه',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('password');

    $this->from(route('register'))
        ->post(route('register'), [
            'name' => 'فروشنده',
            'email' => 'new2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
            'shop_name' => 'فروشگاه',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});

it('logs a seller in and out', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('password123'),
    ]);
    Shop::factory()->forUser($user)->create();

    $this->post(route('login'), [
        'email' => 'login@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'bad@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'bad@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects guests away from protected dashboard routes', function (string $routeName) {
    $this->get(route($routeName))
        ->assertRedirect(route('login'));
})->with([
    'dashboard' => 'dashboard',
    'products' => 'dashboard.products',
    'costs' => 'dashboard.costs',
    'api-keys' => 'dashboard.api-keys',
]);
