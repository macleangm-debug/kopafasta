<?php

/**
 * Staging-only payment lab. Production must ignore this file's runtime settings.
 * Never point these overrides at live PayIn or real merchant accounts.
 */
return [
    'mode' => 'simulator', // simulator | psp_sandbox
    'default_test_fee' => 500,
    'allow_success' => true,
    'allow_pending' => true,
    'allow_failure' => true,
    'allow_reversal' => true,
    'overrides' => [
        'application_fee' => 500,
        'group_application_fee' => 500,
        'asset_backed_application_fee' => 500,
        'valuation_fee' => 1000,
        'plus' => 1000,
        'membership' => 500,
        'partner_membership' => 1000,
        'other' => 500,
    ],
];
