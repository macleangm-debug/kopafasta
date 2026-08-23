<?php

return [
    'types' => [
        'registration_fee' => [
            'label'       => 'Membership Fee',
            'credit_gl'   => 'registration_fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'application_fee' => [
            'label'       => 'Application Fee',
            'credit_gl'   => 'application_fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'valuation_fee' => [
            'label'       => 'Valuation Fee',
            'credit_gl'   => 'valuation_revenue_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'asset_reservation_fee' => [
            'label'       => 'Application Fee',
            'credit_gl'   => 'application_fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'asset_deposit' => [
            'label'       => 'Deposit',
            'credit_gl'   => 'customer_gl_account_id',
            'fallback_gl' => 'cash_gl_account_id',
        ],
        'post_approval_fee' => [
            'label'       => 'Post-approval Fee',
            'credit_gl'   => 'application_fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'insurance_premium' => [
            'label'       => 'Insurance Premium',
            'credit_gl'   => 'fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'loan_repayment' => [
            'label'       => 'Loan Repayment',
            'credit_gl'   => 'loan_receivable_gl_account_id',
            'fallback_gl' => 'loan_receivable_gl_account_id',
        ],
        'penalty_payment' => [
            'label'       => 'Penalty Payment',
            'credit_gl'   => 'penalty_income_gl_account_id',
            'fallback_gl' => 'penalty_income_gl_account_id',
        ],
        'restructure_fee' => [
            'label'       => 'Restructure Fee',
            'credit_gl'   => 'fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'top_up_fee' => [
            'label'       => 'Top-Up Fee',
            'credit_gl'   => 'fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
        'partner_membership' => [
            'label'       => 'Partner membership',
            'credit_gl'   => 'fee_income_gl_account_id',
            'fallback_gl' => 'fee_income_gl_account_id',
        ],
    ],

    'methods' => [
        'bank_transfer' => [
            'label'     => 'Bank Transfer',
            'short'     => 'Bank',
            'channel'   => 'bank',
        ],
        'mobile_money' => [
            'label'     => 'Mobile Money',
            'short'     => 'Mobile money',
            'channel'   => 'mobile_money',
        ],
    ],

    'statuses' => [
        'awaiting_payment'          => 'Ready to pay',
        'pending_verification'    => 'Pending Verification',
        'clarification_requested' => 'Clarification Requested',
        'processing'              => 'Awaiting phone confirmation',
        'verified'                  => 'Verified',
        'rejected'                  => 'Rejected',
        'paid'                      => 'Paid',
    ],

    'debit_gl' => 'customer_gl_account_id',
    'debit_gl_fallback' => 'cash_gl_account_id',
];
