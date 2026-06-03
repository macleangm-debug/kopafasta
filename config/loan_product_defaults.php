<?php

/**
 * Default loan product settings (microfinance / Tanzania BOT-aligned).
 *
 * - default_grace_days: calendar days after a missed instalment before penalties accrue.
 * - penalty_rate_percent: % of overdue balance charged per day (1% = BOT-friendly daily rate).
 * - penalty_cap_percent (global loan rules): cumulative penalty cap (BOT max 30%).
 */
return [
    'default_grace_days' => 7,
    'penalty_rate_percent' => 1.0,
    'penalty_basis' => 'per_day',

    'products' => [
        'EM' => ['default_grace_days' => 3],
        'EMG-06' => ['default_grace_days' => 3],
        'IL' => ['default_grace_days' => 7],
        'GL' => ['default_grace_days' => 7],
        'BP' => ['default_grace_days' => 10],
        'AL' => ['default_grace_days' => 10],
        'AB' => ['default_grace_days' => 10],
        'SAL-12' => ['default_grace_days' => 5],
        'AGR-24' => ['default_grace_days' => 14],
        'AST-36' => ['default_grace_days' => 10],
    ],
];
