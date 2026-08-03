<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Screening recommendation rationales (notes for credit committee)
    |--------------------------------------------------------------------------
    */
    'rationales' => [
        'aligns_with_crb' => 'Aligns with the CRB suggestion',
        'differs_affordability' => 'Differs from CRB — affordability / capacity',
        'differs_documents' => 'Differs from CRB — document quality or gaps',
        'differs_guarantor' => 'Differs from CRB — guarantor strength or gaps',
        'differs_income' => 'Differs from CRB — income verification',
        'differs_risk' => 'Differs from CRB — overall risk judgment',
        'counter_capacity' => 'Counter-offer based on repayment capacity',
        'reject_risk' => 'Reject — unacceptable risk',
        'reject_affordability' => 'Reject — cannot afford',
        'reject_documents' => 'Reject — documents insufficient',
        'reject_guarantor' => 'Reject — guarantor not acceptable',
        'other' => 'Other (explain in notes)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Committee final-approval reasons
    |--------------------------------------------------------------------------
    */
    'approval_reasons' => [
        'aligns_with_screening' => 'Confirms the screening recommendation',
        'strong_affordability' => 'Strong affordability / repayment capacity',
        'acceptable_risk' => 'Risk is within policy',
        'adequate_security' => 'Collateral / security is adequate',
        'strong_guarantor' => 'Guarantor strength supports approval',
        'documents_complete' => 'Documents are complete and verified',
        'custom' => 'Custom (write below)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Committee divergence reasons (required when not validating screening)
    |--------------------------------------------------------------------------
    */
    'committee_rationales' => [
        'differs_amount' => 'Different amount / tenure judgment',
        'differs_risk' => 'Different overall risk judgment',
        'differs_affordability' => 'Different view on affordability',
        'differs_guarantor' => 'Different view on guarantor',
        'differs_documents' => 'Different view on documents',
        'new_information' => 'New information since screening',
        'other' => 'Other (explain in notes)',
    ],
];
