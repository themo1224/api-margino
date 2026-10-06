<?php

use App\Actions\EvaluateShopAlerts;
use App\Enums\RivalSource;
use App\Mail\BelowFloorAlertMail;
use App\Models\Product;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

it('requires a bearer token for reports and alerts', function () {
    $this->getJson('/api/pricing/reports')->assertUnauthorized();
    $this->getJson('/api/pricing/alerts')->assertUnauthorized();
    $this->putJson('/api/pricing/alerts', [])->assertUnauthorized();
});

it('returns a report summary for the seller shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create();
    $product = Product::factory()->for($shop)->create([
        'price' => '1100000',
        'recommended_price' => '1000000',
        'recommendation_updated_at' => now()->subDay(),
    ]);
    RivalSnapshot::factory()->for($product)->create([
        'source' => RivalSource::Torob,
        'cheapest_price' => '1000000',
        'captured_at' => now()->subHour(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/reports')
        ->assertOk()
        ->assertJsonPath('data.recommendationsIssued', 1)
        ->assertJsonPath('data.rivalChecks', 1)
        ->assertJsonPath('data.priceActions', 0)
        ->assertJsonPath('data.avgVsRivalPct', 10)
        ->assertJsonCount(4, 'data.chart')
        ->assertJsonStructure(['data' => ['monthLabel', 'chart' => [['label', 'recommends', 'rivalHits']]]]);
});

it('returns default alert prefs and saves changes', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create(['alerts_enabled' => true]);

    Sanctum::actingAs($user);

    $this->getJson('/api/pricing/alerts')
        ->assertOk()
        ->assertJsonPath('data.belowCost', true)
        ->assertJsonPath('data.aboveRivals', true)
        ->assertJsonPath('data.staleCost', true)
        ->assertJsonPath('data.email', true)
        ->assertJsonPath('data.sms', false);

    $this->putJson('/api/pricing/alerts', [
        'belowCost' => false,
        'aboveRivals' => true,
        'staleCost' => true,
        'email' => true,
        'sms' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.belowCost', false);

    expect($shop->fresh()->alert_prefs['below_floor'])->toBeFalse();
});

it('validates alert prefs payload', function () {
    $user = User::factory()->create();
    Shop::factory()->forUser($user)->create();

    Sanctum::actingAs($user);

    $this->putJson('/api/pricing/alerts', ['belowCost' => 'nope'])
        ->assertUnprocessable();
});

it('skips below-floor email when the seller turned it off', function () {
    Mail::fake();
    config(['alerts.enabled' => true]);

    $user = User::factory()->create();
    $shop = Shop::factory()->forUser($user)->create([
        'alerts_enabled' => true,
        'alert_prefs' => ['below_floor' => false],
    ]);
    Product::factory()->for($shop)->create(['below_floor' => true]);

    app(EvaluateShopAlerts::class)->handle($shop->fresh());

    Mail::assertNotSent(BelowFloorAlertMail::class);
});
