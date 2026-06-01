<?php

return [
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
        'mobile_money' => ['M-Pesa', 'Tigo Pesa', 'Airtel Money'],
        'bank' => ['Bank transfer'],
    ],
];
