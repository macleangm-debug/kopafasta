<?php

return [
    'plans' => [
        'monthly' => [
            'code' => 'monthly',
            'period_days' => 365,
            'prices' => [
                'TZ' => ['amount' => 35000, 'currency' => 'TZS'],
                'KE' => ['amount' => 200, 'currency' => 'KES'],
            ],
        ],
    ],
    'complimentary_days' => 0,
    'tax_rate' => 0,
    'rewards' => [
        'catalog' => [
            [
                'code' => 'partner_voucher',
                'points' => 250,
                'title_en' => 'Partner voucher',
                'title_sw' => 'Vocha ya mshirika',
            ],
            [
                'code' => 'plus_month',
                'points' => 500,
                'title_en' => 'One month of Plus',
                'title_sw' => 'Mwezi mmoja wa Plus',
            ],
            [
                'code' => 'learning_session',
                'points' => 300,
                'title_en' => 'Special learning session',
                'title_sw' => 'Kikao maalum cha kujifunza',
            ],
        ],
    ],
    'nba_priority' => [
        'repayment_due_soon',
        'lesson_published_not_watched',
        'goal_near_completion',
        'no_business_entry_today',
        'no_money_entry_today',
        'reward_available',
        'business_week_improved',
        'monthly_report_ready',
        'capture_prompt',
    ],
];
