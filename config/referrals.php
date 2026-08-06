<?php

return [
    'code_prefix' => env('REFERRAL_CODE_PREFIX', 'KPF'),

    /** Invitee membership fee discount when they register via a member link (%). */
    'discount_percent' => (float) env('REFERRAL_DISCOUNT_PERCENT', 10),

    /**
     * Fixed referral points credited to the referrer when the invitee pays membership.
     * Stored in the referral wallet using gamification.referral_wallet.points_per_tzs.
     */
    'referrer_points' => (int) env('REFERRAL_REFERRER_POINTS', 50),

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
     * Membership payment can happen later — points still credit once paid.
     */
    'attribution_days' => (int) env('REFERRAL_ATTRIBUTION_DAYS', 30),

    /** Placeholders: {brand}, {referrer_name}, {referral_link}, {Referral Link}, {discount_percent}, {referrer_points} */
    'messages' => [
        'share_template' => 'Join {brand} with my link and get {discount_percent}% off membership: {referral_link}',
        'invite_sms'     => 'Join {brand} via my invite link — {discount_percent}% off membership: {referral_link}',
        'share_en' => <<<'MSG'
Join me on {brand}.
Use my invite link to register and get {discount_percent}% off your membership fee.
After you join and pay, I also earn rewards for inviting you.
Register here:
{Referral Link}
MSG,
        'share_sw' => <<<'MSG'
Jiunge nami kwenye {brand}.
Tumia kiungo changu cha mwaliko kujisajili na upate punguzo la {discount_percent}% kwenye ada ya uanachama.
Baada ya kujiunga na kulipa, mimi pia ninapata zawadi kwa kukuwaalika.
Jisajili hapa:
{Referral Link}
MSG,
    ],
];
