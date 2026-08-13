<?php

return [
    'processing_time' => [
        'default' => '24 – 48 Hours',
        'EM'      => '4 – 24 Hours',
        'EMG'     => '4 – 24 Hours',
    ],

    'specific' => [
        'EM' => [
            ['label' => 'Emergency type', 'detail' => 'Provided during application'],
            ['label' => 'Supporting evidence', 'detail' => 'Hospital bill, fee letter, etc.'],
        ],
        'EMG' => [
            ['label' => 'Emergency type', 'detail' => 'Provided during application'],
            ['label' => 'Supporting evidence', 'detail' => 'Hospital bill, fee letter, etc.'],
        ],
        'EL' => [
            ['label' => 'School / institution name', 'detail' => 'Provided during application'],
            ['label' => 'Admission or fee letter', 'detail' => 'Upload or reference during application'],
        ],
        'ED' => [
            ['label' => 'School / institution name', 'detail' => 'Provided during application'],
            ['label' => 'Admission or fee letter', 'detail' => 'Upload or reference during application'],
        ],
        'FC' => [
            ['label' => 'Craft / trade type', 'detail' => 'From Profile → Activity (Artisan / Craftsman)'],
            ['label' => 'Workshop location', 'detail' => 'From Profile → Activity'],
        ],
        'AL' => [
            ['label' => 'Deposit confirmation', 'detail' => 'Collected before asset release'],
            ['label' => 'Selected asset details', 'detail' => 'Pro-forma invoice or supplier quote'],
        ],
        'AST' => [
            ['label' => 'Deposit confirmation', 'detail' => 'Collected before asset release'],
        ],
        'AB' => [
            ['label' => 'Asset photos', 'detail' => 'Multiple angles of the collateral'],
            ['label' => 'Ownership documents', 'detail' => 'Logbook or title deed'],
            ['label' => 'Insurance cover', 'detail' => 'Comprehensive insurance certificate'],
        ],
        'GL' => [
            ['label' => 'Group members', 'detail' => 'Member roster submitted digitally during application'],
            ['label' => 'Group approval', 'detail' => 'Group sign-off during application (paper constitution optional if enabled on the product)'],
            ['label' => 'External guarantor', 'detail' => 'Not required for group loans'],
        ],
    ],
];
