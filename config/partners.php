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
        'auto_recover' => true,
        'warnings_before_suspend' => 2,
        'nudge_cooldown_days' => 7,
        'excellent_score' => 90,
        'target_on_time_percent' => 90,
        'target_completion_percent' => 95,
        'recover_lookback_days' => 90,
    ],

    /**
     * Versioned Terms for task/case partners. Bodies live in lang/en|sw/partner_terms.php
     * and may be overridden in Settings. SLA/KPI numbers are never stored here —
     * Terms render from Origination auto-assign, Recovery policy, and Partner performance.
     */
    'terms' => [
        'require_before_jobs' => true,
        'material_change_requires_reacceptance' => false,
        'policy_version' => 1,
        'conduct_version' => '2026.09',
        'types' => [
            'valuer' => ['version' => 1],
            'gps_installer' => ['version' => 1],
            'insurance' => ['version' => 1],
            'call_center' => ['version' => 1],
            'debt_collector' => ['version' => 1],
            'auctioneer' => ['version' => 1],
            'legal_partner' => ['version' => 1],
        ],
    ],
];
