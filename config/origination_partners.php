<?php

return [
    /** Origination / underwriting partners (not in recovery escalation chain). */
    'partner_types' => [
        'valuation_partner' => [
            'label'           => 'Valuation Partner',
            'vendor_category' => 'valuer',
            'default_sla_days'=> 5,
        ],
    ],

    'vendor_categories' => [
        'valuer',
    ],
];
