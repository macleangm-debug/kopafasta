<?php

return [
    /**
     * Annual / renewal membership for non-affiliate partners.
     * Affiliates use config('affiliates.membership') instead.
     */
    'membership' => [
        'enabled' => true,
        'default_fee_amount' => 0,
        'default_duration_days' => 365,
        'grace_period_days' => 14,
        'notify_days_before_expiry' => 30,
        /** Categories that must pay (empty = renew-on-expiry only, no activation fee). */
        'categories_requiring_payment' => [
            // 'valuer' => true,
        ],
        'category_fees' => [
            // 'valuer' => 50000,
        ],
    ],
];
