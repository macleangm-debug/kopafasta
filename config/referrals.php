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

    /** Days a referral link click stays tied to the referrer. */
    'attribution_days' => (int) env('REFERRAL_ATTRIBUTION_DAYS', 30),

    /** Placeholders: {brand}, {referrer_name}, {referral_code}, {referral_link}, {Referral Link}, {discount_percent} */
    'messages' => [
        'share_template' => 'Join {brand} with my referral code {referral_code}. Register here: {referral_link}',
        'invite_sms'     => 'Use my {brand} referral link for {discount_percent}% off registration fees: {referral_link}',
        'share_en' => <<<'MSG'
Join me on kopafasta, where you can access affordable loans, grow your financial profile, and enjoy exclusive member benefits.
Sign up using my referral link and you'll receive a discount on your membership fee during the current promotion.
Once you're a member, you'll be able apply for loans, invite friends, and unlock even more rewards.
Click my link to get started:
{Referral Link}
MSG,
        'share_sw' => <<<'MSG'
Jiunge nami kopafasta na upate fursa ya kupata mikopo nafuu, kujenga historia yako ya kifedha, pamoja na kufurahia manufaa ya wanachama.
Ukijisajili kupitia link yangu ya mwaliko, utapata punguzo kwenye ada ya uanachama wakati wa kampeni hii maalum.
Baada ya kuwa mwanachama utaweza kutuma maombi ya mkopo, kuwaalika wengine na kupata zawadi mbalimbali.
Bofya link hii ili kuanza:
{Referral Link}
MSG,
    ],
];
