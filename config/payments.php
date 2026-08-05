<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment gateway mode
    |--------------------------------------------------------------------------
    |
    | dummy — instant test payments (no M-Pesa / bank API). Use until live rails.
    | live  — production gateway behaviour (bank transfers await verification).
    |
    */
    'gateway_mode' => env('PAYMENT_GATEWAY_MODE', 'dummy'),

    /*
    |--------------------------------------------------------------------------
    | Mobile money payment threshold (TZS)
    |--------------------------------------------------------------------------
    |
    | Amounts above this threshold must use bank transfer only.
    |
    */
    'mobile_money_threshold' => (int) env('PAYMENT_MOBILE_MONEY_THRESHOLD', 3_000_000),

    'channels' => [
        'mobile_money' => ['M-Pesa', 'Tigo Pesa', 'Airtel Money', 'Halo Pesa'],
        'bank' => ['Bank transfer'],
    ],
];
