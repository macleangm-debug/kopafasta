<?php

return [
    // Flat one-time loan application registration fee (TZS).
    'registration_fee' => env('SITE_REGISTRATION_FEE', 10000),

    // Channels shown on the apply wizard's fee step.
    'fee_channels' => [
        ['name' => 'M-Pesa',       'till' => '123456',        'note' => 'Lipa na M-Pesa → Pay Bill → enter business no.'],
        ['name' => 'Tigo Pesa',    'till' => '654321',        'note' => 'Lipa kwa Tigo Pesa'],
        ['name' => 'Airtel Money', 'till' => '987654',        'note' => 'Airtel Money → Pay merchant'],
        ['name' => 'Bank (CRDB)',  'till' => '0150-XXXXX-00', 'note' => 'A/c name: Kopafasta Microfinance Ltd'],
    ],
];
