<?php

return [
    'code_prefix' => env('REFERRAL_CODE_PREFIX', 'KPF'),

    /**
     * @deprecated Retired membership-referral cash discount. Always treated as 0 at checkout.
     * Referrals earn points only. Key is kept so older Settings rows still load.
     */
    'discount_percent' => 0,

    /** Points the referrer earns when the invitee successfully registers. */
    'register_points' => (int) env('REFERRAL_REGISTER_POINTS', 5),

    /** Extra points when the invitee submits a first valid application and pays the application fee. */
    'application_points' => (int) env('REFERRAL_APPLICATION_POINTS', 25),

    /**
     * @deprecated Use application_points. Kept so older Settings rows still load.
     */
    'referrer_points' => (int) env('REFERRAL_REFERRER_POINTS', 25),

    /**
     * @deprecated Prefer referrer_points. Kept for backward compatibility when referrer_points is unset.
     */
    'commission_percent' => (float) env('REFERRAL_COMMISSION_PERCENT', 10),

    /** Maximum share of a fee payable from referral points wallet (0–100). */
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

    /**
     * Days a referral link click stays tied to the referrer for registration.
     * If they click today and register within this window, the referrer is locked in.
     * Application-fee payment can happen later — the +application_points credit fires then.
     */
    'attribution_days' => (int) env('REFERRAL_ATTRIBUTION_DAYS', 30),

    /** Placeholders: {brand}, {referrer_name}, {referral_link}, {Referral Link}, {register_points}, {application_points} */
    'messages' => [
        'share_template' => 'Join me on {brand}. Use my invite link to create your account: {referral_link}',
        'invite_sms'     => 'Join me on {brand}. Register here: {referral_link}',
        'share_en' => <<<'MSG'
Join me on {brand}.
Use my invite link to create your account and discover {brand} services. When you join, I can earn reward points.
Register here:
{Referral Link}
MSG,
        'share_sw' => <<<'MSG'
Jiunge nami {brand}.
Tumia kiungo changu kufungua akaunti na kugundua huduma za {brand}. Ukijiunga, ninaweza kupata pointi za zawadi.
Jisajili hapa:
{Referral Link}
MSG,
    ],
];
