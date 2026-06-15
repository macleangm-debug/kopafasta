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
];
