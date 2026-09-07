<?php

/**
 * Staging-only payment lab. Production must ignore this file's runtime settings.
 * Never point these overrides at live PayIn or real merchant accounts.
 *
 * When use_price_overrides is true, fee kinds below replace Settings Hub amounts
 * for staging UAT only (TZS 1,000 test amounts). Loan product principal minima
 * remain product-configured unless Admin sets staging product fixtures separately.
 */
return [
    'mode' => 'simulator', // simulator | psp_sandbox
    'use_price_overrides' => true,
    'default_test_fee' => 1000,
    'allow_success' => true,
    'allow_pending' => true,
    'allow_failure' => true,
    'allow_reversal' => true,
    'overrides' => [
        'application_fee' => 1000,
        'group_application_fee' => 1000,
        'asset_backed_application_fee' => 1000,
        'valuation_fee' => 1000,
        'plus' => 1000,
        'membership' => 1000,
        'partner_membership' => 1000,
        'other' => 1000,
    ],
];
