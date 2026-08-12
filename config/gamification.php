<?php

return [
    'referral_levels' => [
        ['key' => 'bronze', 'label' => 'Bronze', 'min_referrals' => 0, 'max_referrals' => 5],
        ['key' => 'silver', 'label' => 'Silver', 'min_referrals' => 6, 'max_referrals' => 20],
        ['key' => 'gold', 'label' => 'Gold', 'min_referrals' => 21, 'max_referrals' => 50],
        ['key' => 'diamond', 'label' => 'Diamond', 'min_referrals' => 51, 'max_referrals' => null],
    ],

    'referral_level_benefits' => [
        'bronze'  => ['Lower membership fees', 'Referral wallet credits'],
        'silver'  => ['Lower membership fees', 'Faster processing', 'Better interest rates'],
        'gold'    => ['Higher loan limits', 'Exclusive campaigns', 'Better interest rates'],
        'diamond' => ['Highest loan limits', 'Priority support', 'Exclusive campaigns'],
    ],

    'referral_milestones' => [
        ['target' => 5, 'reward_label' => '50% membership discount', 'reward_type' => 'membership_discount', 'reward_value' => 50],
    ],

    'trust_score' => [
        'max_stars' => 5,
        'weights' => [
            'on_time_payments'   => 30,
            'profile_completion' => 25,
            'referrals'          => 15,
            'account_age'        => 10,
            'successful_loans'   => 20,
        ],
        'benefits' => [
            'Higher loan limits',
            'Faster application processing',
        ],
    ],

    'community_milestones' => [
        ['key' => 'help_5_join', 'target' => 5, 'title' => 'Help 5 people join', 'rewards' => ['Membership discount', 'Cashback', 'Bonus points']],
    ],

    'repayment_streak' => [
        'enabled' => true,
        'reward_label' => 'Repayment streak points',
        'milestones' => [
            ['count' => 3, 'points' => 10],
            ['count' => 5, 'points' => 20],
            ['count' => 7, 'points' => 35],
            ['count' => 10, 'points' => 50],
            ['count' => 12, 'points' => 75],
        ],
    ],

    'profile_strength' => [
        ['key' => 'bronze', 'label' => 'Bronze', 'min_percent' => 0, 'max_percent' => 39],
        ['key' => 'silver', 'label' => 'Silver', 'min_percent' => 40, 'max_percent' => 69],
        ['key' => 'gold', 'label' => 'Gold', 'min_percent' => 70, 'max_percent' => 89],
        ['key' => 'verified', 'label' => 'Verified', 'min_percent' => 90, 'max_percent' => 100],
    ],

    'referral_wallet' => [
        /** TZS per 1 referral point shown to members (1 = 1 pt per TZS commission). */
        'points_per_tzs' => (int) env('REFERRAL_WALLET_POINTS_PER_TZS', 1),
    ],

    'loyalty_points' => [
        'actions' => [
            'complete_profile'  => ['label' => 'Complete Profile', 'points' => 100],
            'refer_friend'      => ['label' => 'Refer Friend', 'points' => 250],
            'repay_on_time'     => ['label' => 'Repay On Time', 'points' => 300],
            'upload_documents'  => ['label' => 'Upload Documents', 'points' => 50],
            'update_information'=> ['label' => 'Update Information', 'points' => 20],
        ],
        'penalties' => [
            'late_repayment' => [
                'label'  => 'Late repayment',
                'points' => 50,
                'enabled'=> true,
            ],
            'late_fee_accrual' => [
                'label'  => 'Late fee charged',
                'points' => 25,
                'enabled'=> true,
            ],
        ],
        'redemptions' => [
            'membership_discount'   => 'Membership discounts',
            'interest_discount'     => 'Interest discounts',
            'processing_fee_discount'=> 'Processing fee discounts',
            'priority_support'      => 'Priority support',
            'merchandise'           => 'Merchandise',
        ],
        'redemption_options' => [
            [
                'key'           => 'membership_10',
                'label'         => '10% off membership fee',
                'label_sw'      => 'Punguzo la 10% kwa ada ya uanachama',
                'description'   => 'Applied to your next membership payment',
                'description_sw'=> 'Itatumika kwenye malipo yako ya uanachama yanayofuata',
                'points'        => 500,
                'benefit_type'  => 'percent_discount',
                'benefit_value' => 10,
                'fee_type'      => 'registration_fee',
                'expires_days'  => 90,
            ],
            [
                'key'           => 'application_fee_15',
                'label'         => '15% off application fee',
                'label_sw'      => 'Punguzo la 15% kwa ada ya maombi',
                'description'   => 'Applied when you pay your next loan application fee',
                'description_sw'=> 'Itatumika unapolipa ada ya maombi ya mkopo unaofuata',
                'points'        => 750,
                'benefit_type'  => 'percent_discount',
                'benefit_value' => 15,
                'fee_type'      => 'application_fee',
                'expires_days'  => 90,
            ],
            [
                'key'           => 'interest_half',
                'label'         => '0.5% interest discount',
                'label_sw'      => 'Punguzo la 0.5% kwa riba',
                'description'   => 'Lower rate on your next loan amount',
                'description_sw'=> 'Kiwango cha chini cha riba kwenye mkopo wako unaofuata',
                'points'        => 1000,
                'benefit_type'  => 'rate_discount',
                'benefit_value' => 0.005,
                'fee_type'      => null,
                'expires_days'  => 60,
            ],
            [
                'key'           => 'priority_support',
                'label'         => 'Priority support (30 days)',
                'label_sw'      => 'Usaidizi wa kipaumbele (siku 30)',
                'description'   => 'Faster responses from our support team',
                'description_sw'=> 'Majibu ya haraka kutoka kwa timu yetu ya usaidizi',
                'points'        => 400,
                'benefit_type'  => 'support_flag',
                'benefit_value' => 0,
                'fee_type'      => null,
                'expires_days'  => 30,
            ],
        ],
    ],

    'notifications' => [
        'categories' => ['repayment', 'application', 'promotions', 'referral', 'membership', 'group_loan'],
        'date_groups' => ['today', 'yesterday', 'earlier'],
    ],

    'leaderboard' => [
        'enabled' => true,
        'limit' => 10,
        'mask_names' => true,
    ],

    'underwriting_boosts' => [
        'referral_level' => [
            'bronze'  => ['limit_multiplier' => 1.00, 'rate_discount' => 0.000, 'processing_priority' => 0],
            'silver'  => ['limit_multiplier' => 1.05, 'rate_discount' => 0.005, 'processing_priority' => 1],
            'gold'    => ['limit_multiplier' => 1.10, 'rate_discount' => 0.010, 'processing_priority' => 2],
            'diamond' => ['limit_multiplier' => 1.15, 'rate_discount' => 0.015, 'processing_priority' => 3],
        ],
        'trust_score' => [
            'limit_multiplier_per_step'     => 0.02,
            'rate_discount_per_step'          => 0.002,
            'processing_priority_per_step'    => 0.5,
        ],
    ],
];
