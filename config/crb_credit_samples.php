<?php

/**
 * Sample credit bureau data for stub / sandbox mode.
 * Never shown to borrowers — underwriting and admin review only.
 */
return [
    'default' => [
        'report_meta' => [
            'cir_number' => 'W-SAMPLE/2026',
            'ruid' => '110113010000272193',
            'ordered_at' => '08-Aug-2026',
            'institution_name' => 'Kopafasta (stub)',
            'search_score' => '100%',
        ],
        'personal' => [
            'full_name' => 'Amina Juma Mwinyi',
            'surname' => 'Mwinyi',
            'first_name' => 'Amina',
            'middle_names' => 'Juma',
            'gender' => 'Female',
            'date_of_birth' => '12-Mar-1990',
            'nationality' => 'Tanzania, United Republic Of',
            'country_of_birth' => 'Tanzania, United Republic Of',
            'district_of_birth' => 'Kinondoni',
            'marital_status' => 'Married',
            'number_of_spouses' => 1,
            'spouses' => [
                ['name' => 'Hassan Ali Mwinyi'],
            ],
            'number_of_children' => 2,
            'education' => 'University',
            'profession' => 'Trader',
            'employer' => 'Self employed',
            'mobile' => '+255712345678',
            'address' => 'Mikocheni, Kinondoni, Dar es Salaam',
            'ids' => [
                ['id_number' => '19900312-12345-00001-23', 'id_type' => 'National ID'],
            ],
            'address_history' => [
                ['type' => 'Physical', 'address' => 'Mikocheni, Kinondoni, Dar es Salaam', 'date_reported' => '15-Jan-2024'],
                ['type' => 'Physical', 'address' => 'Temeke, Dar es Salaam', 'date_reported' => '30-Jun-2019'],
            ],
            'contact_history' => [
                ['type' => 'Mobile Telephone', 'detail' => '+255712345678', 'date_reported' => '15-Jan-2024'],
            ],
            'employment_history' => [
                ['employer' => 'Self employed', 'profession' => 'Trader', 'date_reported' => '15-Jan-2024'],
            ],
            'related_persons' => [
                ['name' => 'Hassan Ali Mwinyi', 'relation' => 'Spouse'],
            ],
        ],
        'credit' => [
            'score'               => 612,
            'risk_grade'          => 'B',
            'recommendation'      => 'refer',
            'existing_loans'      => 1,
            'outstanding_balance' => 850000,
            'delinquencies'       => 0,
            'loan_history'        => [
                ['lender' => 'Sample MFI', 'status' => 'open', 'product' => 'Instalment Loans', 'balance' => 850000, 'overdue' => 0],
            ],
            'overview' => [
                'accounts' => 1,
                'creditors' => 1,
                'collateral_count' => 0,
                'unpaid_instal_30' => 0,
                'unpaid_instal_60' => 0,
                'unpaid_instal_360' => 0,
                'inquiries_by_fa' => 2,
                'loans_guaranteed' => 0,
                'most_negative_status' => 'No negative status',
            ],
            'balances_by_currency' => [
                'TZS' => ['balance' => 850000, 'past_due' => 0],
            ],
            'overdue_buckets' => [
                ['bucket' => '1-30', 'amount' => 0],
                ['bucket' => '31-60', 'amount' => 0],
                ['bucket' => '61-90', 'amount' => 0],
                ['bucket' => '91-120', 'amount' => 0],
                ['bucket' => '121-150', 'amount' => 0],
                ['bucket' => '151-180', 'amount' => 0],
                ['bucket' => '180+', 'amount' => 0],
            ],
            'exposure_by_product' => [
                ['product' => 'Instalment Loans', 'currency' => 'TZS', 'amount_overdue' => 0, 'not_overdue' => 850000, 'active_facilities' => 1],
            ],
            'exposure_by_credit' => [
                ['product' => 'Instalment Loans', 'currency' => 'TZS', 'liability' => 'Borrower', 'total_balance' => 850000],
            ],
            'open_accounts' => [
                [
                    'lender' => 'Sample MFI',
                    'product' => 'Instalment Loans',
                    'purpose' => 'Working capital',
                    'currency' => 'TZS',
                    'approval_amount' => 1200000,
                    'outstanding' => 850000,
                    'overdue' => 0,
                    'installment_amount' => 120000,
                    'installments_total' => 12,
                    'installments_left' => 7,
                    'overdue_installments' => 0,
                    'activated_date' => '01-Jan-2026',
                    'maturity_date' => '01-Jan-2027',
                    'negative_status' => 'No negative status',
                    'status' => 'open',
                    'balance' => 850000,
                ],
            ],
            'closed_accounts' => [
                [
                    'lender' => 'Sample Bank',
                    'product' => 'Instalment Loans',
                    'sanction_amount' => 500000,
                    'overdue' => 0,
                    'activated_date' => '01-Jan-2023',
                    'closure_date' => '01-Jan-2024',
                    'phase' => 'Terminated according the contract',
                    'status' => 'closed',
                    'balance' => 0,
                ],
            ],
            'guaranteed_loans' => [],
            'insurance_accounts' => [],
            'inquiries_summary' => [
                ['institution_type' => 'Micro Finance Institutions', 'count' => 2],
            ],
            'inquiries' => [
                ['date' => '01-Jul-2026', 'purpose' => 'New Credit Application', 'institution_type' => 'Micro Finance Institutions', 'amount' => 1000000, 'currency' => 'TZS'],
                ['date' => '15-Mar-2026', 'purpose' => 'Account Review', 'institution_type' => 'Commercial Banks', 'amount' => null, 'currency' => null],
            ],
            'overdue_graph' => [],
            'disputes' => [],
            'most_negative_status' => 'No negative status',
        ],
    ],
];
