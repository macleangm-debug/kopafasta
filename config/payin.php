<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayIn (docs.payin.co.tz)
    |--------------------------------------------------------------------------
    |
    | Admin Settings → Integrations → PayIn can override these at runtime
    | via the `payin.*` Setting group. Env values are fallbacks only.
    |
    */
    'enabled' => (bool) env('PAYIN_ENABLED', false),
    'environment' => env('PAYIN_ENVIRONMENT', 'sandbox'), // sandbox|production
    'api_key' => env('PAYIN_API_KEY', ''),
    'api_secret' => env('PAYIN_API_SECRET', ''),
    'webhook_secret' => env('PAYIN_WEBHOOK_SECRET', ''),
    'default_callback_url' => env('PAYIN_CALLBACK_URL', ''),

    'base_urls' => [
        'sandbox' => 'https://api.sandbox.payin.co.tz/api/v1',
        'production' => 'https://api.payin.co.tz/api/v1',
    ],

    'operators' => [
        'mpesa' => 'M-Pesa',
        'airtel' => 'Airtel Money',
        'tigopesa' => 'Tigo Pesa',
        'halopesa' => 'Halo Pesa',
    ],
];
