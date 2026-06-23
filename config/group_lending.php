<?php

return [
    'min_members' => 5,
    'max_members' => 10,

    'leader_unlock_repayments' => 2,

    'recovery_stages' => [
        'individual'      => 'Individual recovery',
        'group_liability' => 'Group liability',
        'external'        => 'External recovery partner',
    ],

    'application_fee_per_member' => true,
    'post_approval_fee_per_group' => true,
];
