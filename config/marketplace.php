<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace license webhooks (Zhaket / RTL)
    |--------------------------------------------------------------------------
    |
    | Thin subscription mapping: purchase → time-bound plan on a shop.
    | Never grant forever unlock of the engine + rivals.
    |
    */

    'webhook_secret' => env('MARKETPLACE_WEBHOOK_SECRET', ''),

];
