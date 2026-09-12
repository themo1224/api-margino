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

];
