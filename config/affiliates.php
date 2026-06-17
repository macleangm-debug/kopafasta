<?php

return [
    'code_prefix' => env('AFFILIATE_CODE_PREFIX', 'KPA'),

    'default_registration_discount_percent' => (float) env('AFFILIATE_REGISTRATION_DISCOUNT', 10),
    'default_application_discount_percent'  => (float) env('AFFILIATE_APPLICATION_DISCOUNT', 10),
    'default_commission_percent'          => (float) env('AFFILIATE_COMMISSION_PERCENT', 10),

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
