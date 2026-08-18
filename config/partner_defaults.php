<?php

/**
 * Default pricing for origination / service partners.
 * Edited under Admin → Settings → Recovery policy.
 * Per-partner overrides live on the partner record (Add Partner → Rates).
 */
return [
    'categories' => [
        'insurance' => [
            'label' => 'Insurance partner',
            'pricing_mode' => 'percent_of_value',
            'add_category' => 'insurance',
            'default_rate_percent' => 3.5,
            'default_has_markup' => false,
            'default_markup_percent' => 0.0,
            'help' => 'Borrower premium = insured value × (cover rate% + markup%). Partner earns cover rate% only; markup% is platform share of insured value.',
        ],
        'gps_installer' => [
            'label' => 'GPS partner',
            'pricing_mode' => 'fixed_plus_recurring',
            'add_category' => 'gps_installer',
            'default_base_cost' => 50_000,
            'default_monitoring_monthly' => 20_000,
            'default_has_markup' => false,
            'default_markup_percent' => 0.0,
            'help' => 'Post-approval: installation + monthly monitoring × loan tenure. Changing these figures updates new contracts immediately. Deactivation during recovery has no extra borrower charge.',
        ],
        'valuer' => [
            'label' => 'Valuation partner',
            'pricing_mode' => 'fixed',
            'add_category' => 'valuer',
            'default_base_cost' => 300,
            'default_has_markup' => true,
            'default_markup_percent' => 10.0,
            'help' => 'Borrower pays base × (1 + markup%). Valuer earns base only; markup is platform valuation revenue.',
        ],
    ],
];
