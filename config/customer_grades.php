<?php

/**
 * Default Source of Truth for customer grades.
 * Admin edits are stored in settings and snapshotted as rule versions.
 * Application code must read through CustomerGradeEngine — never hard-code thresholds.
 */
return [
    'grades' => ['bronze', 'silver', 'gold', 'platinum'],

    'score_bands' => [
        'bronze' => ['min' => 0, 'max' => 39],
        'silver' => ['min' => 40, 'max' => 59],
        'gold' => ['min' => 60, 'max' => 79],
        'platinum' => ['min' => 80, 'max' => 100],
    ],

    'weights' => [
        'repayment' => 35,
        'handled_credit' => 20,
        'relationship' => 15,
        'current_position' => 15,
        'stability' => 10,
        'verification' => 5,
    ],

    'lookback_months' => 12,
    'recent_weight' => 0.60,
    'lifetime_weight' => 0.40,

    'integrity' => [
        'min_qualifying_principal' => 100_000,
        'min_facility_age_days' => 21,
        'max_qualifying_facilities_per_30_days' => 2,
        'min_days_between_qualifying_facilities' => 14,
        'rapid_cycle_watch_count' => 5,
        'reversal_review_count' => 2,
        'upgrade_freeze_days_on_watch' => 30,
        'upgrade_freeze_days_on_review' => 60,
    ],

    'grace_days' => [
        'silver' => 14,
        'gold' => 30,
        'platinum' => 45,
    ],

    'review_days' => 30,

    'severe_immediate_downgrade_facts' => [
        'defaulted_facilities_count',
        'current_days_past_due',
    ],

    'country_bands' => [
        'TZ' => [
            'currency' => 'TZS',
            'lifetime_principal' => [0, 500_000, 2_000_000, 5_000_000, 10_000_000, 20_000_000],
            'largest_facility' => [0, 250_000, 1_000_000, 2_500_000, 5_000_000, 10_000_000],
            'potential_access' => [
                'bronze' => 500_000,
                'silver' => 1_500_000,
                'gold' => 5_000_000,
                'platinum' => 15_000_000,
            ],
        ],
        'KE' => [
            'currency' => 'KES',
            'lifetime_principal' => [0, 25_000, 100_000, 250_000, 500_000, 1_000_000],
            'largest_facility' => [0, 15_000, 50_000, 125_000, 250_000, 500_000],
            'potential_access' => [
                'bronze' => 25_000,
                'silver' => 75_000,
                'gold' => 250_000,
                'platinum' => 750_000,
            ],
        ],
    ],

    'gates' => [
        'bronze' => [
            'all' => [],
            'any_of' => ['count' => 0, 'rules' => []],
        ],
        'silver' => [
            'all' => [
                ['fact' => 'qualifying_completed_facilities', 'op' => '>=', 'value' => 1],
                ['fact' => 'current_days_past_due', 'op' => '<=', 'value' => 7],
                ['fact' => 'open_overdue_count', 'op' => '<=', 'value' => 0],
            ],
            'any_of' => ['count' => 0, 'rules' => []],
        ],
        'gold' => [
            'all' => [
                ['fact' => 'qualifying_completed_facilities', 'op' => '>=', 'value' => 2],
                ['fact' => 'effective_on_time_ratio', 'op' => '>=', 'value' => 90],
                ['fact' => 'relationship_days', 'op' => '>=', 'value' => 180],
                ['fact' => 'current_days_past_due', 'op' => '<=', 'value' => 0],
                ['fact' => 'open_overdue_count', 'op' => '<=', 'value' => 0],
                ['fact' => 'defaulted_facilities_count', 'op' => '<=', 'value' => 0],
            ],
            'any_of' => [
                'count' => 2,
                'rules' => [
                    ['fact' => 'qualifying_completed_facilities', 'op' => '>=', 'value' => 3],
                    ['fact' => 'lifetime_principal_borrowed', 'op' => '>=', 'value' => 5_000_000],
                    ['fact' => 'lifetime_amount_repaid', 'op' => '>=', 'value' => 6_000_000],
                ],
            ],
        ],
        'platinum' => [
            'all' => [
                ['fact' => 'qualifying_completed_facilities', 'op' => '>=', 'value' => 3],
                ['fact' => 'effective_on_time_ratio', 'op' => '>=', 'value' => 95],
                ['fact' => 'relationship_days', 'op' => '>=', 'value' => 365],
                ['fact' => 'current_days_past_due', 'op' => '<=', 'value' => 0],
                ['fact' => 'max_days_past_due_recent', 'op' => '<=', 'value' => 7],
                ['fact' => 'defaulted_facilities_count', 'op' => '<=', 'value' => 0],
                ['fact' => 'restructured_facilities_count', 'op' => '<=', 'value' => 1],
            ],
            'any_of' => ['count' => 0, 'rules' => []],
        ],
    ],

    'customer_copy' => [
        'bronze' => [
            'en' => "You're building your Kopafasta history.",
            'sw' => 'Unajenga historia yako ya Kopafasta.',
        ],
        'silver' => [
            'en' => "You've unlocked more with Kopafasta.",
            'sw' => 'Umefungua fursa zaidi na Kopafasta.',
        ],
        'gold' => [
            'en' => "You've built a strong Kopafasta relationship.",
            'sw' => 'Umejenga uhusiano thabiti na Kopafasta.',
        ],
        'platinum' => [
            'en' => 'Our highest customer status.',
            'sw' => 'Hii ni hadhi ya juu kabisa ya mteja.',
        ],
        'under_review' => [
            'en' => 'Your status is being reviewed. Keeping your commitments on time helps you maintain a strong Kopafasta status.',
            'sw' => 'Hadhi yako inakaguliwa. Kulipa kwa wakati kunasaidia kudumisha hadhi thabiti ya Kopafasta.',
        ],
        'next_step' => [
            'en' => 'Keep building a strong repayment history.',
            'sw' => 'Endelea kujenga historia nzuri ya malipo.',
        ],
    ],

    'benefits' => [
        'bronze' => [
            'priority' => 'standard',
            'repeat_journey' => 'full',
            'offer_tier' => 'standard',
            'rewards' => 'Standard Plus rewards when subscribed',
            'exclusive' => '',
            'max_tenure_months' => 12,
        ],
        'silver' => [
            'priority' => 'standard',
            'repeat_journey' => 'confirm',
            'offer_tier' => 'silver',
            'rewards' => 'Silver Plus rewards when subscribed',
            'exclusive' => 'Silver partner offers',
            'max_tenure_months' => 18,
        ],
        'gold' => [
            'priority' => 'priority',
            'repeat_journey' => 'welcome_back',
            'offer_tier' => 'gold',
            'rewards' => 'Gold Plus rewards when subscribed',
            'exclusive' => 'Priority service and Gold offers',
            'max_tenure_months' => 24,
        ],
        'platinum' => [
            'priority' => 'highest',
            'repeat_journey' => 'prefill',
            'offer_tier' => 'platinum',
            'rewards' => 'Highest Plus rewards when subscribed',
            'exclusive' => 'Exclusive opportunities and highest service priority',
            'max_tenure_months' => 36,
        ],
    ],

    'trust_labels' => [
        ['max' => 39, 'key' => 'building', 'en' => 'Building', 'sw' => 'Inajengwa'],
        ['max' => 59, 'key' => 'steady', 'en' => 'Steady', 'sw' => 'Thabiti'],
        ['max' => 79, 'key' => 'strong', 'en' => 'Strong', 'sw' => 'Imara'],
        ['max' => 100, 'key' => 'excellent', 'en' => 'Excellent', 'sw' => 'Bora'],
    ],
];
