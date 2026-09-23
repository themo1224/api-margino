<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local / testing API key (never used in production seeders)
    |--------------------------------------------------------------------------
    |
    | WordPress plan 1.3 can paste this key against http://localhost:8000/v1
    | after `php artisan db:seed`. Do not set this to a production secret.
    |
    */

    'dev_api_key' => env('CONNECTOR_DEV_API_KEY', 'dev_pk_local_connector_key_do_not_use_in_prod'),

    'key_prefix_length' => 12,

    'sync_batch_max' => 500,

    /*
    |--------------------------------------------------------------------------
    | Stub recommendations when shop has no cost profile
    |--------------------------------------------------------------------------
    |
    | When true (default in local/testing), B4 stub copies store price into
    | recommended_price if the shop has no cost profile. Production must leave
    | this false so sellers never get “recommended = current” without costs.
    |
    */

    'stub_recommendations' => env(
        'CONNECTOR_STUB_RECOMMENDATIONS',
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    ),

];
