<?php

return [
    'code_prefix' => env('REFERRAL_CODE_PREFIX', 'KPF'),

    'discount_percent' => (float) env('REFERRAL_DISCOUNT_PERCENT', 10),

    'commission_percent' => (float) env('REFERRAL_COMMISSION_PERCENT', 10),

    /** Maximum share of a fee payable from referral wallet (0–100). */
    'wallet_max_fee_percent' => (float) env('REFERRAL_WALLET_MAX_FEE_PERCENT', 50),

    'wallet_allowed_for' => [
        'registration_fee',
        'application_fee',
        'post_approval_fee',
    ],

    'wallet_blocked_for' => [
        'loan_repayment',
        'interest',
        'penalty',
    ],

    /** Placeholders: {brand}, {referrer_name}, {referral_code}, {referral_link}, {discount_percent} */
    'messages' => [
        'share_template' => 'Join {brand} with my referral code {referral_code}. Register here: {referral_link}',
        'invite_sms'     => 'Use my {brand} referral link for {discount_percent}% off registration fees: {referral_link}',
    ],
];
