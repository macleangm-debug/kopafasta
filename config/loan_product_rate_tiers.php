<?php

/**
 * Default tiered monthly rates (stored as decimals; admins enter percents).
 * Each tier monthly_rate is the TOTAL rate shown to borrowers (BOT + processing + risk + insurance).
 */
return [
    'default_template' => [
        ['min_amount' => 100_000, 'max_amount' => 500_000, 'monthly_rate' => 0.17],
        ['min_amount' => 500_001, 'max_amount' => 1_000_000, 'monthly_rate' => 0.15],
        ['min_amount' => 1_000_001, 'max_amount' => 3_000_000, 'monthly_rate' => 0.13],
        ['min_amount' => 3_000_001, 'max_amount' => null, 'monthly_rate' => 0.12],
    ],

    'templates' => [
        'IL' => [
            ['min_amount' => 100_000, 'max_amount' => 500_000, 'monthly_rate' => 0.17],
            ['min_amount' => 500_001, 'max_amount' => 1_000_000, 'monthly_rate' => 0.15],
            ['min_amount' => 1_000_001, 'max_amount' => 3_000_000, 'monthly_rate' => 0.13],
            ['min_amount' => 3_000_001, 'max_amount' => null, 'monthly_rate' => 0.12],
        ],
        'GL' => [
            ['min_amount' => 50_000, 'max_amount' => 500_000, 'monthly_rate' => 0.16],
            ['min_amount' => 500_001, 'max_amount' => 2_000_000, 'monthly_rate' => 0.14],
            ['min_amount' => 2_000_001, 'max_amount' => null, 'monthly_rate' => 0.12],
        ],
        'EM' => [
            ['min_amount' => 50_000, 'max_amount' => 300_000, 'monthly_rate' => 0.22],
            ['min_amount' => 300_001, 'max_amount' => 1_000_000, 'monthly_rate' => 0.20],
            ['min_amount' => 1_000_001, 'max_amount' => null, 'monthly_rate' => 0.18],
        ],
        'BP' => [
            ['min_amount' => 500_000, 'max_amount' => 2_000_000, 'monthly_rate' => 0.16],
            ['min_amount' => 2_000_001, 'max_amount' => 10_000_000, 'monthly_rate' => 0.14],
            ['min_amount' => 10_000_001, 'max_amount' => null, 'monthly_rate' => 0.12],
        ],
        'AL' => [
            ['min_amount' => 500_000, 'max_amount' => 5_000_000, 'monthly_rate' => 0.14],
            ['min_amount' => 5_000_001, 'max_amount' => 25_000_000, 'monthly_rate' => 0.12],
            ['min_amount' => 25_000_001, 'max_amount' => null, 'monthly_rate' => 0.10],
        ],
        'AB' => [
            ['min_amount' => 500_000, 'max_amount' => 5_000_000, 'monthly_rate' => 0.13],
            ['min_amount' => 5_000_001, 'max_amount' => 25_000_000, 'monthly_rate' => 0.11],
            ['min_amount' => 25_000_001, 'max_amount' => null, 'monthly_rate' => 0.09],
        ],
        'FC' => [
            ['min_amount' => 50_000, 'max_amount' => 500_000, 'monthly_rate' => 0.18],
            ['min_amount' => 500_001, 'max_amount' => null, 'monthly_rate' => 0.15],
        ],
        'KB' => [
            ['min_amount' => 100_000, 'max_amount' => 1_000_000, 'monthly_rate' => 0.16],
            ['min_amount' => 1_000_001, 'max_amount' => null, 'monthly_rate' => 0.13],
        ],
        'EL' => [
            ['min_amount' => 50_000, 'max_amount' => 500_000, 'monthly_rate' => 0.17],
            ['min_amount' => 500_001, 'max_amount' => 3_000_000, 'monthly_rate' => 0.14],
            ['min_amount' => 3_000_001, 'max_amount' => null, 'monthly_rate' => 0.12],
        ],
        'WL' => [
            ['min_amount' => 50_000, 'max_amount' => 500_000, 'monthly_rate' => 0.17],
            ['min_amount' => 500_001, 'max_amount' => 2_000_000, 'monthly_rate' => 0.14],
            ['min_amount' => 2_000_001, 'max_amount' => null, 'monthly_rate' => 0.12],
        ],
        'SAL-12' => [
            ['min_amount' => 200_000, 'max_amount' => 1_000_000, 'monthly_rate' => 0.14],
            ['min_amount' => 1_000_001, 'max_amount' => 3_000_000, 'monthly_rate' => 0.12],
            ['min_amount' => 3_000_001, 'max_amount' => null, 'monthly_rate' => 0.10],
        ],
        'BIZ-30' => [
            ['min_amount' => 100_000, 'max_amount' => 500_000, 'monthly_rate' => 0.16],
            ['min_amount' => 500_001, 'max_amount' => null, 'monthly_rate' => 0.13],
        ],
        'AGR-24' => [
            ['min_amount' => 300_000, 'max_amount' => 2_000_000, 'monthly_rate' => 0.14],
            ['min_amount' => 2_000_001, 'max_amount' => null, 'monthly_rate' => 0.11],
        ],
    ],
];
