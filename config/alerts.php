<?php

return [

    'enabled' => (bool) env('ALERTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dedupe window
    |--------------------------------------------------------------------------
    |
    | Same shop + fingerprint will not email again within this many hours.
    | Keeps "within ~1 hour of change" without inbox spam.
    |
    */

    'dedupe_hours' => (int) env('ALERTS_DEDUPE_HOURS', 24),

    'default_undercut_threshold_percent' => '5',

    'default_cost_stale_days' => 30,

];
