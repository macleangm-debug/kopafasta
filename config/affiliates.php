<?php

return [
    'code_prefix' => env('AFFILIATE_CODE_PREFIX', 'KPA'),

    'default_registration_discount_percent' => (float) env('AFFILIATE_REGISTRATION_DISCOUNT', 10),
    'default_application_discount_percent'  => (float) env('AFFILIATE_APPLICATION_DISCOUNT', 10),
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
        'period_days'                         => (int) env('AFFILIATE_EVAL_PERIOD_DAYS', 30),
        'min_events_for_scoring'              => (int) env('AFFILIATE_EVAL_MIN_EVENTS', 3),
        'watchlist_risk_score'                => (float) env('AFFILIATE_EVAL_WATCHLIST_RISK', 60),
        'watchlist_fraud_score'               => (float) env('AFFILIATE_EVAL_WATCHLIST_FRAUD', 50),
        'suspend_risk_score'                  => (float) env('AFFILIATE_EVAL_SUSPEND_RISK', 80),
        'suspend_fraud_score'                 => (float) env('AFFILIATE_EVAL_SUSPEND_FRAUD', 75),
        'duplicate_ip_registration_threshold' => (int) env('AFFILIATE_EVAL_DUP_IP', 3),
        'low_conversion_threshold'            => (float) env('AFFILIATE_EVAL_LOW_CONV', 5),
        'high_click_threshold'                => (int) env('AFFILIATE_EVAL_HIGH_CLICKS', 50),
        'weights' => [
            'volume'     => 0.3,
            'conversion' => 0.4,
            'commission' => 0.3,
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
        'registration_fee'  => true,
        'application_fee' => true,
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
];
