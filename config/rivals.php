<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rival catalog drivers
    |--------------------------------------------------------------------------
    |
    | Spike: Torob go (flagged), Snapp stub only. See
    | docs/spikes/torob-snapp-rival-fetch.md. Testing/local default to fake.
    |
    */

    'driver' => env('RIVALS_DRIVER', env('APP_ENV') === 'testing' ? 'fake' : 'http'),

    'torob_enabled' => (bool) env('RIVALS_TOROB_ENABLED', false),

    'snapp_enabled' => (bool) env('RIVALS_SNAPP_ENABLED', false),

    'stale_multiplier' => 1.5,

    'auto_link_min_confidence' => (float) env('RIVALS_AUTO_LINK_CONFIDENCE', 0.78),

    'http' => [
        'torob_base_url' => env('TOROB_API_BASE', 'https://api.torob.com'),
        'timeout' => (int) env('RIVALS_HTTP_TIMEOUT', 15),
        'min_interval_ms' => (int) env('RIVALS_MIN_INTERVAL_MS', 1200),
        'user_agent' => env('TOROB_USER_AGENT', 'PricingSaaS/1.0 (+rivals; research)'),
    ],

];
