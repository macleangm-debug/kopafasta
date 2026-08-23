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
            'valuer' => true,
        ],
        /**
         * Live amounts come from Settings → Partner membership.
         * Valuers split by applicant: individual vs company.
         */
        'category_fees' => [
            'valuer' => [
                'individual' => 1500,
                'company' => 2000,
            ],
        ],
    ],

    /**
     * Field-partner efficiency (valuer, GPS, insurance, recovery).
     * Bands and auto-coaching are edited in Settings → Partner performance.
     */
    'efficiency' => [
        'min_jobs_for_score' => 3,
        'strong_score' => 80,
        'watch_score' => 60,
        'force_at_risk_escalation_percent' => 40,
        'force_at_risk_fail_percent' => 40,
        'weight_completion' => 40,
        'weight_on_time' => 25,
        'weight_not_escalated' => 20,
        'weight_not_failed' => 15,
        'auto_nudge' => true,
        'auto_suspend' => true,
        'warnings_before_suspend' => 2,
        'nudge_cooldown_days' => 7,
    ],
];
