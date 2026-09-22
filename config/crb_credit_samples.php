<?php

/**
 * Stub / sandbox CRB credit fixtures for Gate 3 testing.
 * Source on stored CreditHistory remains `crb_stub` — never present as live D&B.
 *
 * Scenarios (selected via customer KYC payload crb_stub_scenario, NIDA map, or installStubReport):
 *   clean          — correct person, approve, no disqualifying findings
 *   refer          — correct person, bureau REFER + reviewable soft gaps
 *   hard_fail      — correct person, non-overridable bureau failure (delinquencies / reject)
 *   wrong_subject  — fresh report for a different person (Amina fixture)
 *   stale          — correct person; age set via CreditHistory.checked_at (install helper)
 *   no_record      — bureau no-hit / no usable record
 */
return [

    /**
     * Optional NIDA → scenario overrides for deterministic seeded test IDs.
     * Keys are formatted NIDA (XXXXXXXX-XXXXX-XXXXX-XX).
     */
    'by_nida' => [
        // Reserved for seeded walkthrough NIDs when assigned.
    ],

    /** Legacy universal sample — used only for wrong_subject. */
    'wrong_subject_personal' => [
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

    'credit_templates' => [

        'clean' => [
            'score' => 710,
            'risk_grade' => 'A',
            'recommendation' => 'approve',
            'existing_loans' => 0,
            'outstanding_balance' => 0,
            'delinquencies' => 0,
            'loan_history' => [],
            'overview' => [
                'accounts' => 0,
                'creditors' => 0,
                'collateral_count' => 0,
                'unpaid_instal_30' => 0,
                'unpaid_instal_60' => 0,
                'unpaid_instal_360' => 0,
                'inquiries_by_fa' => 1,
                'loans_guaranteed' => 0,
                'most_negative_status' => 'No negative status',
            ],
            'balances_by_currency' => [
                'TZS' => ['balance' => 0, 'past_due' => 0],
            ],
            'overdue_buckets' => [],
            'exposure_by_product' => [],
            'exposure_by_credit' => [],
            'open_accounts' => [],
            'closed_accounts' => [],
            'guaranteed_loans' => [],
            'insurance_accounts' => [],
            'inquiries_summary' => [
                ['institution_type' => 'Micro Finance Institutions', 'count' => 1],
            ],
            'inquiries' => [
                ['date' => '01-Jul-2026', 'purpose' => 'New Credit Application', 'institution_type' => 'Micro Finance Institutions', 'amount' => 500000, 'currency' => 'TZS'],
            ],
            'overdue_graph' => [],
            'disputes' => [],
            'most_negative_status' => 'No negative status',
        ],

        'refer' => [
            'score' => 612,
            'risk_grade' => 'B',
            'recommendation' => 'refer',
            'existing_loans' => 1,
            'outstanding_balance' => 850000,
            'delinquencies' => 0,
            'loan_history' => [
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
            'closed_accounts' => [],
            'guaranteed_loans' => [],
            'insurance_accounts' => [],
            'inquiries_summary' => [
                ['institution_type' => 'Micro Finance Institutions', 'count' => 2],
            ],
            'inquiries' => [
                ['date' => '01-Jul-2026', 'purpose' => 'New Credit Application', 'institution_type' => 'Micro Finance Institutions', 'amount' => 1000000, 'currency' => 'TZS'],
            ],
            'overdue_graph' => [],
            'disputes' => [],
            'most_negative_status' => 'No negative status',
        ],

        'hard_fail' => [
            'score' => 380,
            'risk_grade' => 'E',
            'recommendation' => 'reject',
            'existing_loans' => 2,
            'outstanding_balance' => 2400000,
            'delinquencies' => 2,
            'loan_history' => [
                ['lender' => 'Sample Bank', 'status' => 'open', 'product' => 'Instalment Loans', 'balance' => 1400000, 'overdue' => 320000],
                ['lender' => 'Sample MFI', 'status' => 'open', 'product' => 'Instalment Loans', 'balance' => 1000000, 'overdue' => 180000],
            ],
            'overview' => [
                'accounts' => 2,
                'creditors' => 2,
                'collateral_count' => 0,
                'unpaid_instal_30' => 1,
                'unpaid_instal_60' => 1,
                'unpaid_instal_360' => 0,
                'inquiries_by_fa' => 4,
                'loans_guaranteed' => 0,
                'most_negative_status' => 'Past due',
            ],
            'balances_by_currency' => [
                'TZS' => ['balance' => 2400000, 'past_due' => 500000],
            ],
            'overdue_buckets' => [
                ['bucket' => '1-30', 'amount' => 200000],
                ['bucket' => '31-60', 'amount' => 300000],
                ['bucket' => '61-90', 'amount' => 0],
                ['bucket' => '91-120', 'amount' => 0],
                ['bucket' => '121-150', 'amount' => 0],
                ['bucket' => '151-180', 'amount' => 0],
                ['bucket' => '180+', 'amount' => 0],
            ],
            'exposure_by_product' => [
                ['product' => 'Instalment Loans', 'currency' => 'TZS', 'amount_overdue' => 500000, 'not_overdue' => 1900000, 'active_facilities' => 2],
            ],
            'exposure_by_credit' => [
                ['product' => 'Instalment Loans', 'currency' => 'TZS', 'liability' => 'Borrower', 'total_balance' => 2400000],
            ],
            'open_accounts' => [],
            'closed_accounts' => [],
            'guaranteed_loans' => [],
            'insurance_accounts' => [],
            'inquiries_summary' => [],
            'inquiries' => [],
            'overdue_graph' => [],
            'disputes' => [],
            'most_negative_status' => 'Past due',
        ],
    ],

];
