<?php

/**
 * Sample credit bureau data for stub / sandbox mode.
 * Never shown to borrowers — underwriting and admin review only.
 */
return [
    'default' => [
        'score'               => 612,
        'risk_grade'          => 'B',
        'recommendation'      => 'refer',
        'existing_loans'      => 1,
        'outstanding_balance' => 850000,
        'delinquencies'       => 0,
        'loan_history'        => [
            ['lender' => 'Sample MFI', 'status' => 'performing', 'balance' => 850000],
        ],
    ],
];
