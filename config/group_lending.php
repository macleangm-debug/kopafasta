<?php

return [
            'min_members' => 3,
            'max_members' => 10,

            /** Minimum loan amount each group member may request (TZS). */
            'min_amount_per_member' => 200_000,

    'repayment_cadence' => 'weekly',

    'leader_unlock_repayments' => 2,

    /** Extra waiting days after gatekeeper disbursement before next unlock (0 = repayments only). */
    'unlock_days' => 0,

    'payout_order' => 'leader_first',

    'recovery_stages' => [
        'individual'      => 'Individual recovery',
        'group_liability' => 'Group liability',
        'external'        => 'External recovery partner',
    ],

    'application_fee_per_member' => true,
    'post_approval_fee_per_group' => true,
];
