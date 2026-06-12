<?php

/**
 * Underwriter guidance shown on the application review page per document type.
 * Keys match LoanProductRequirement.code, DocumentType.code, or normalized name slugs.
 */
return [
    'defaults' => [
        'title' => 'What to verify',
        'items' => [
            'Document is legible and complete',
            'Belongs to the borrower on this application',
            'Not older than the product requirement allows',
        ],
    ],

    'bank_statement' => [
        'title' => 'Bank statement',
        'items' => [
            'Consistent income over the required period',
            'No suspicious or unexplained large transfers',
            'Statement not older than 90 days',
            'Account name matches borrower identity',
        ],
    ],

    'mobile_money_statement' => [
        'title' => 'Mobile money statement',
        'items' => [
            'Regular inflows matching declared activity',
            'No suspicious activity or unexplained spikes',
            'Statement covers the last 6 months',
            'Registered phone matches borrower profile',
        ],
    ],

    'residence_verification_letter' => [
        'title' => 'Residence letter',
        'items' => [
            'Address matches borrower residence on profile',
            'Official stamp or authority signature present',
            'Letter date is recent and valid',
        ],
    ],

    'employment_contract' => [
        'title' => 'Employment contract',
        'items' => [
            'Employer name matches activity information',
            'Contract is current and signed',
            'Salary or role supports declared income',
        ],
    ],

    'salary_slip' => [
        'title' => 'Salary slip',
        'items' => [
            'Recent pay period (within last 3 months)',
            'Employer matches declared employment',
            'Net pay aligns with affordability inputs',
        ],
    ],

    'national_id' => [
        'title' => 'National ID (NIDA)',
        'items' => [
            'Photo and number match verified NIDA record',
            'Document is not expired or damaged beyond use',
        ],
    ],

    'business_registration' => [
        'title' => 'Business registration',
        'items' => [
            'Business name matches activity information',
            'Registration is valid and not expired',
            'Owner or director matches borrower where applicable',
        ],
    ],
];
