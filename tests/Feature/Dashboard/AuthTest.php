<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the register page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/register'));
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
    $user = User::factory()->create([
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

it('redirects guests away from the dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});
