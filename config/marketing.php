<?php

/**
 * Growth workspace catalogs. Targeting dimensions and demo templates
 * are configuration Source of Truth — operational pages read these,
 * they do not invent new fields.
 */
return [
    'audience_dimensions' => [
        'country_code' => [
            'label' => 'Country',
            'options' => ['TZ' => 'Tanzania', 'KE' => 'Kenya'],
        ],
        'status' => [
            'label' => 'Customer status',
            'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'],
        ],
        'grades' => [
            'label' => 'Grade',
            'options' => ['bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum'],
        ],
        'plus' => [
            'label' => 'Kopafasta Plus',
            'options' => ['any' => 'Any', 'subscribed' => 'Subscribed', 'not_subscribed' => 'Not subscribed'],
        ],
        'borrowing' => [
            'label' => 'Borrowing relationship',
            'options' => [
                'any' => 'Any',
                'active_loan' => 'Has an active loan',
                'completed_loan' => 'Completed a loan',
                'never_borrowed' => 'Never borrowed',
            ],
        ],
        'affiliate' => [
            'label' => 'Affiliate status',
            'options' => [
                'any' => 'Any',
                'referred' => 'Referred by an affiliate',
                'not_referred' => 'Not referred',
            ],
        ],
    ],

    'campaign_intents' => [
        'encourage_plus' => 'Promote Kopafasta Plus',
        'promote_offer' => 'Promote an offer',
        'encourage_applications' => 'Encourage applications',
        'encourage_repayment' => 'Encourage repayment / healthy behaviour',
        'referral' => 'Referral campaign',
        'affiliate' => 'Affiliate campaign',
        'learning_content' => 'Promote learning / content',
        'customer_engagement' => 'Customer re-engagement',
        'fee_promotion' => 'Fee promotion',
        'announcement' => 'Announcement',
        'other' => 'Other',
    ],

    'personas' => [
        'small_business_builder' => [
            'name' => 'Small Business Builder',
            'role' => 'borrower',
            'summary' => 'Runs a small business. Gold. Plus member. Uses Business + Goals.',
            'traits' => ['Gold', 'Plus member', 'Business + Goals', 'Interested in growth'],
            'defaults' => ['grade' => 'gold', 'trust' => 82, 'plus' => true, 'amount' => 2_500_000],
        ],
        'new_borrower' => [
            'name' => 'New Borrower',
            'role' => 'borrower',
            'summary' => 'Bronze. First facility. Not Plus. Building Trust.',
            'traits' => ['Bronze', 'First facility', 'Not Plus', 'Building Trust'],
            'defaults' => ['grade' => 'bronze', 'trust' => 41, 'plus' => false, 'amount' => 400_000],
        ],
        'established_customer' => [
            'name' => 'Established Customer',
            'role' => 'borrower',
            'summary' => 'Platinum. Long relationship. Strong Trust.',
            'traits' => ['Platinum', 'Long relationship', 'Strong Trust'],
            'defaults' => ['grade' => 'platinum', 'trust' => 91, 'plus' => true, 'amount' => 8_000_000],
        ],
        'gold_plus_member' => [
            'name' => 'Gold Plus Member',
            'role' => 'plus',
            'summary' => 'Gold member with Plus active and a monthly report waiting.',
            'traits' => ['Gold', 'Plus active', 'Monthly report ready'],
            'defaults' => ['grade' => 'gold', 'trust' => 78, 'plus' => true, 'amount' => 1_800_000],
        ],
        'affiliate_with_earnings' => [
            'name' => 'Affiliate with earnings',
            'role' => 'affiliate',
            'summary' => 'Active affiliate with commission ready to present.',
            'traits' => ['Affiliate', 'Has earnings', 'Restricted demo'],
            'defaults' => ['grade' => null, 'trust' => null, 'plus' => false, 'amount' => 350_000],
            'restricted' => true,
        ],
    ],

    'scenarios' => [
        'loan_received' => ['label' => 'Loan received', 'roles' => ['borrower', 'plus']],
        'making_repayment' => ['label' => 'Loan repayment', 'roles' => ['borrower', 'plus']],
        'loan_completed' => ['label' => 'Loan completed', 'roles' => ['borrower', 'plus']],
        'grade_state' => ['label' => 'Grade state', 'roles' => ['borrower', 'plus']],
        'trust_state' => ['label' => 'Trust state', 'roles' => ['borrower', 'plus']],
        'plus_active' => ['label' => 'Plus member', 'roles' => ['plus', 'borrower']],
        'monthly_report_ready' => ['label' => 'Plus monthly report', 'roles' => ['plus']],
        'goal_progress' => ['label' => 'Goal progress', 'roles' => ['borrower', 'plus']],
        'affiliate_commission_earned' => ['label' => 'Affiliate earnings', 'roles' => ['affiliate']],
        'affiliate_withdrawal' => ['label' => 'Affiliate withdrawal', 'roles' => ['affiliate']],
    ],

    'demo_durations' => [
        '5' => '5 minutes',
        '15' => '15 minutes',
        '30' => '30 minutes',
        '60' => '1 hour',
        'today' => 'Today',
        'custom' => 'Custom',
    ],
];
