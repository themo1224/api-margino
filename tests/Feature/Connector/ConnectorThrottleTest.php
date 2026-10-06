<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

it('connector returns 429 when rate limit exceeded', function () {
    RateLimiter::for('connector', function (Request $request) {
        return Limit::perMinute(1)->by('connector-throttle-test');
    });

    [$shop, $plainKey] = connectorShop();
    $headers = connectorHeaders($shop, $plainKey);

    $this->postJson('/v1/connector/validate', [], $headers)->assertOk();

    $this->postJson('/v1/connector/validate', [], $headers)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
});
