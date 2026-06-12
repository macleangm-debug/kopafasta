<?php

return [
    'auto_approve_max_amount' => (int) env('PARTNER_AUTO_APPROVE_MAX_AMOUNT', 500_000),

    'auto_approve_source_types' => [
        'supplier_deposit',
    ],
];
