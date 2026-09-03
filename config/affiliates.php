<?php

return [
    'code_prefix' => env('AFFILIATE_CODE_PREFIX', 'KPA'),

    'default_registration_discount_percent' => (float) env('AFFILIATE_REGISTRATION_DISCOUNT', 10),
    'default_application_discount_percent'  => (float) env('AFFILIATE_APPLICATION_DISCOUNT', 10),
    'default_plus_discount_percent'         => (float) env('AFFILIATE_PLUS_DISCOUNT', 10),
    'default_commission_percent'          => (float) env('AFFILIATE_COMMISSION_PERCENT', 10),

    /** percentage | fixed | tiered | hybrid */
    'commission_mode' => env('AFFILIATE_COMMISSION_MODE', 'percentage'),

    /** Fixed commission amounts (TZS) when mode is fixed or tier fallback. */
    'fixed_commission_amounts' => [
        'default'           => 0,
        'registration_fee'  => 0,
        'application_fee'   => 0,
        'post_approval_fee' => 0,
    ],

    /**
     * Tiered commission by referral count (registration/application events).
     * type: fixed | percentage — amount is TZS or percent respectively.
     */
    'commission_tiers' => [
        ['min_count' => 1, 'max_count' => 10, 'type' => 'fixed', 'amount' => 1000],
        ['min_count' => 11, 'max_count' => 50, 'type' => 'fixed', 'amount' => 1500],
        ['min_count' => 51, 'max_count' => null, 'type' => 'fixed', 'amount' => 2000],
    ],

    'hybrid_fixed_amount' => (float) env('AFFILIATE_HYBRID_FIXED', 500),
    'hybrid_percent'      => (float) env('AFFILIATE_HYBRID_PERCENT', 5),

    'evaluation' => [
        'auto_apply_actions'                  => (bool) env('AFFILIATE_EVAL_AUTO_APPLY', true),
        'auto_recover'                        => true,
        'policy_version'                      => 1,
        'period_days'                         => (int) env('AFFILIATE_EVAL_PERIOD_DAYS', 90),
        'min_events_for_scoring'              => (int) env('AFFILIATE_EVAL_MIN_EVENTS', 3),
        'watchlist_risk_score'                => (float) env('AFFILIATE_EVAL_WATCHLIST_RISK', 60),
        'watchlist_fraud_score'               => (float) env('AFFILIATE_EVAL_WATCHLIST_FRAUD', 50),
        'suspend_risk_score'                  => (float) env('AFFILIATE_EVAL_SUSPEND_RISK', 80),
        'suspend_fraud_score'                 => (float) env('AFFILIATE_EVAL_SUSPEND_FRAUD', 75),
        'duplicate_ip_registration_threshold' => (int) env('AFFILIATE_EVAL_DUP_IP', 3),
        'low_conversion_threshold'            => (float) env('AFFILIATE_EVAL_LOW_CONV', 5),
        'high_click_threshold'                => (int) env('AFFILIATE_EVAL_HIGH_CLICKS', 50),
        /** Borrower registrations via the affiliate code in the evaluation period. */
        'monthly_registration_target'         => (int) env('AFFILIATE_MONTHLY_REG_TARGET', 10),
        'volume_min_active_days'              => (int) env('AFFILIATE_VOLUME_MIN_ACTIVE_DAYS', 90),
        'volume_misses_before_nudge'          => (int) env('AFFILIATE_VOLUME_MISSES_NUDGE', 1),
        'volume_misses_before_watchlist'      => (int) env('AFFILIATE_VOLUME_MISSES_WATCHLIST', 2),
        'volume_misses_before_suspend'        => (int) env('AFFILIATE_VOLUME_MISSES_SUSPEND', 3),
        'weights' => [
            'volume'     => 0.3,
            'conversion' => 0.4,
            'commission' => 0.3,
        ],
        'kpis' => [
            'qualified_referrals' => ['enabled' => true, 'target' => 10, 'weight' => 1],
            'applications' => ['enabled' => false, 'target' => 5, 'weight' => 1],
            'disbursed_loans' => ['enabled' => false, 'target' => 3, 'weight' => 1],
            'conversion' => ['enabled' => false, 'target' => 30, 'weight' => 1],
        ],
    ],

    'fraud' => [
        'medium_score'                         => 20,
        'high_score'                           => 50,
        'blocked_score'                        => 80,
        'shared_phone_customer_threshold'      => 2,
        'shared_device_registration_threshold' => 2,
        'multi_account_device_threshold'       => 2,
    ],

    /** original_amount | discounted_amount */
    'commission_calculation_base'         => env('AFFILIATE_COMMISSION_BASE', 'discounted_amount'),

    /** Fee types where affiliate promo codes apply (defaults). */
    'applies_to' => [
        'application_fee'   => true,
        'kopafasta_plus'    => true,
        'registration_fee'  => false,
        'valuation_fee'     => false,
        'gps_fee'           => false,
        'post_approval_fee' => false,
        'interest'          => false,
        'repayments'        => false,
    ],

    /** Placeholders: {brand}, {affiliate_name}, {affiliate_code}, {affiliate_link}, {registration_link}, {verify_link} */
    'messages' => [
        'share_template'       => 'Join {brand} with my affiliate code {affiliate_code}. Register: {registration_link}',
        'referral_sms'         => 'Use affiliate code {affiliate_code} at {brand} for a discount on fees. Verify: {verify_link}',
        'verification_notice'  => 'This page confirms {affiliate_name} ({affiliate_code}) is a registered {brand} affiliate partner.',
        'welcome_partner'      => 'Welcome to the {brand} affiliate program, {affiliate_name}! Share your link: {affiliate_link}',
    ],

    /** Require KYC approval before affiliate link is publicly verified. */
    'require_kyc_for_verification' => true,

    /** Minimum commission balance (TZS) before an affiliate may request payout. */
    'minimum_payout_amount' => 50000,

    /**
     * Annual affiliate membership (anti-scam filter). Paid via the standard payment gate
     * before sharing is unlocked when required_before_sharing is true.
     */
    'membership' => [
        'enabled'                         => true,
        'fee_amount'                      => 50000,
        'fee_amount_individual'           => 25000,
        'fee_amount_company'              => 50000,
        'duration_days'                   => 365,
        'grace_period_hours'              => 48,
        'required_before_sharing'         => true,
        'renewal_window_days'             => 30,
        'require_terms_before_activation' => true,
        'promo_code_on_expiry'            => 'disable',
        'commission_after_expiry'         => 'historical_only',
    ],
];
