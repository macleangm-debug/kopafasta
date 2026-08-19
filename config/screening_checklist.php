<?php

/**
 * Assisted Review Desk — evidence-backed Pass / Fail checks for screening.
 * State: screening_payload.screening_checklist.by_subject.{subjectKey}
 *
 * subjects: borrower | guarantor | member
 */
return [
    'identity' => [
        'label' => 'Identity & KYC',
        'phase' => 'person',
        'phase_label' => '1 · Personal in place',
        'subjects' => ['borrower', 'guarantor', 'member'],
        'items' => [
            'nida_vs_dob' => [
                'label' => 'Compare NIDA number to date of birth',
                'evidence' => 'nida_dob',
                'risk' => 'critical',
                'fail_reasons' => [
                    'nida_dob_mismatch' => 'NIDA does not match date of birth',
                    'nida_incomplete' => 'NIDA or date of birth missing / incomplete',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'name_vs_crb' => [
                'label' => 'Map name to CRB report',
                'evidence' => 'name_crb',
                'risk' => 'critical',
                'fail_reasons' => [
                    'name_mismatch' => 'Name does not match CRB',
                    'crb_missing' => 'CRB report not available',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'marital_vs_crb' => [
                'label' => 'Compare marital / family status to CRB',
                'evidence' => 'marital_crb',
                'risk' => 'elevated',
                'fail_reasons' => [
                    'marital_mismatch' => 'Marital status differs from CRB',
                    'spouse_mismatch' => 'Spouse name differs from CRB',
                    'children_mismatch' => 'Number of children differs from CRB',
                    'crb_missing' => 'CRB personal data not available',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'face_vs_nida' => [
                'label' => 'Compare face capture to uploaded ID',
                'evidence' => 'face_nida',
                'risk' => 'critical',
                'document_bundle' => 'id_quality',
                'fail_reasons' => [
                    'face_mismatch' => 'Face does not match uploaded ID',
                    'photos_missing' => 'Face or ID photo missing',
                    'poor_quality' => 'Photo quality too poor to verify',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'phone_ownership' => [
                'label' => 'Confirm mobile number ownership',
                'evidence' => 'phone',
                'risk' => 'elevated',
                'fail_reasons' => [
                    'phone_not_owned' => 'Number not owned by the customer',
                    'phone_unreachable' => 'Could not reach the number',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'id_document_quality' => [
                'label' => 'Confirm ID documents are clear (via Documents)',
                'evidence' => 'id_docs',
                'risk' => 'critical',
                'document_bundle' => 'id_quality',
                'fail_reasons' => [
                    'poor_quality' => 'Document unclear or incomplete',
                    'suspected_tamper' => 'Suspected tampering / falsified ID',
                    'proof_missing' => 'ID document missing',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'residence' => [
        'label' => 'Residence',
        'phase' => 'person',
        'phase_label' => '1 · Personal in place',
        'subjects' => ['borrower', 'guarantor', 'member'],
        'items' => [
            'address_consistency' => [
                'label' => 'Confirm residence details are complete (address + LGO)',
                'evidence' => 'residence',
                'risk' => 'elevated',
                'fail_reasons' => [
                    'address_mismatch' => 'Address details inconsistent with proof / LGO',
                    'incomplete' => 'Residence details incomplete',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'local_government' => [
                'label' => 'Check with Local Government Officer',
                'evidence' => 'residence',
                'fail_reasons' => [
                    'lgo_not_confirmed' => 'LGO could not confirm residence',
                    'lgo_unreachable' => 'Could not reach LGO',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'landlord_or_owner' => [
                'label' => 'Verify landlord / property ownership where applicable',
                'evidence' => 'residence',
                'fail_reasons' => [
                    'not_verified' => 'Landlord / ownership not verified',
                    'not_applicable_fail' => 'Claimed ownership cannot be confirmed',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'utility_or_proof' => [
                'label' => 'Confirm residence proof (via Documents)',
                'evidence' => 'residence_proof',
                'document_bundle' => 'residence_proof',
                'fail_reasons' => [
                    'proof_missing' => 'Residence proof missing',
                    'proof_invalid' => 'Residence proof invalid or outdated',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'contacts' => [
        'label' => 'Contacts & references',
        'phase' => 'capacity',
        'phase_label' => '2 · Capacity and evidence',
        'subjects' => ['borrower'],
        'items' => [
            'call_guarantor' => [
                'label' => 'Check with guarantor',
                'evidence' => 'guarantor_contact',
                'fail_reasons' => [
                    'unreachable' => 'Guarantor unreachable',
                    'does_not_confirm' => 'Guarantor does not confirm the loan',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'call_next_of_kin' => [
                'label' => 'Check with next of kin',
                'evidence' => 'nok_contact',
                'fail_reasons' => [
                    'unreachable' => 'Next of kin unreachable',
                    'does_not_confirm' => 'Next of kin does not confirm details',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'call_references' => [
                'label' => 'Call listed references / contacts',
                'evidence' => 'generic',
                'fail_reasons' => [
                    'unreachable' => 'References unreachable',
                    'negative_feedback' => 'Negative feedback from references',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'guarantor_capacity' => [
                'label' => 'Assess guarantor capacity to carry the loan',
                'evidence' => 'guarantor_capacity',
                'fail_reasons' => [
                    'insufficient_capacity' => 'Guarantor cannot carry the loan',
                    'profile_incomplete' => 'Guarantor profile incomplete',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'activity_income' => [
        'label' => 'Activity & income',
        'phase' => 'capacity',
        'phase_label' => '2 · Capacity and evidence',
        'subjects' => ['borrower', 'guarantor', 'member'],
        'items' => [
            // Gate 2 (after capacity auto-reject): screening keys deposits + months; the system decides pass/fail.
            'income_evidence' => [
                'label' => 'Match statements to profile revenue — key total deposits',
                'evidence' => 'income_statements',
                'document_bundle' => 'income_statements',
                'risk' => 'critical',
                'gate' => 'statements_vs_declared',
                'fail_reasons' => [
                    'statements_missing' => 'Bank / mobile-money statements missing',
                    'revenue_mismatch' => 'Statement cashflow does not support declared monthly revenue',
                    'income_insufficient' => 'Income evidence insufficient for the claimed revenue',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'activity_plausible' => [
                'label' => 'Does the stated job / business look plausible?',
                'evidence' => 'activity',
                'document_bundle' => 'activity_proof',
                'fail_reasons' => [
                    'implausible' => 'Activity not plausible for this loan',
                    'unverified' => 'Could not verify activity from profile / documents',
                    'docs_missing' => 'Activity proof documents missing',
                    'inconsistent' => 'Activity details inconsistent with documents',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'bank_or_mobile_money' => [
                'label' => 'Any concerning patterns on the statements?',
                'evidence' => 'income_statements',
                'document_bundle' => 'income_statements',
                'risk' => 'critical',
                'fail_reasons' => [
                    'gambling_betting' => 'Gambling, betting, or lottery activity',
                    'round_tripping' => 'Circular / same-day in-and-out transfers',
                    'third_party_dumping' => 'Large unexplained third-party deposits',
                    'salary_inconsistent' => 'Inflows inconsistent with stated job / business',
                    'high_cash_out' => 'Heavy cash-out pattern vs declared income',
                    'overdraft_bounce' => 'Frequent overdrafts, unpaid charges, or bounced items',
                    'debt_stacking' => 'Multiple concurrent loan / microfinance repayments',
                    'dormant_spike' => 'Long dormancy then sudden large spikes',
                    'low_turnover' => 'Turnover too thin for declared monthly revenue',
                    'statements_missing' => 'Statements missing or unreadable',
                    'irregular_pattern' => 'Other irregular or concerning pattern',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'documents' => [
        'label' => 'Documents',
        'phase' => 'capacity',
        'phase_label' => '2 · Capacity and evidence',
        'subjects' => ['borrower', 'guarantor', 'member'],
        'items' => [
            'required_docs_complete' => [
                'label' => 'Confirm required documents are complete',
                'evidence' => 'documents',
                'risk' => 'critical',
                'fail_reasons' => [
                    'docs_missing' => 'Required documents missing',
                    'docs_rejected' => 'Documents rejected / not verified',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'doc_authenticity' => [
                'label' => 'Confirm document authenticity (via Documents reviews)',
                'evidence' => 'documents',
                'document_bundle' => 'profile_all',
                'fail_reasons' => [
                    'falsified' => 'Falsified documentation',
                    'inconsistent' => 'Documents inconsistent with profile',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'requested_docs_reviewed' => [
                'label' => 'Review any requested follow-up documents',
                'evidence' => 'doc_requests',
                'fail_reasons' => [
                    'still_open' => 'Document requests still open',
                    'unsatisfactory' => 'Uploaded follow-up docs unsatisfactory',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'falsified_docs' => [
                'label' => 'Flag falsified / mismatched documentation',
                'evidence' => 'documents',
                'document_bundle' => 'profile_all',
                'fail_reasons' => [
                    'falsified_documentation' => 'Falsified documentation',
                    'inconsistent' => 'Documents inconsistent with profile',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'collateral' => [
        'label' => 'Collateral & assets',
        'phase' => 'security',
        'phase_label' => '3 · Security and close',
        'subjects' => ['borrower', 'guarantor'],
        'items' => [
            'asset_identity' => [
                'label' => 'Confirm asset identity (registration / serial / title)',
                'evidence' => 'collateral_assets',
                'fail_reasons' => [
                    'identity_mismatch' => 'Asset identity does not match documents',
                    'missing_ids' => 'Registration / serial / title missing',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'insurance_type' => [
                'label' => 'Confirm vehicle insurance type (Third Party vs Comprehensive)',
                'evidence' => 'insurance',
                'system' => true,
                'fail_reasons' => [
                    'insurance_type_mismatch' => 'Insurance type mismatch',
                    'type_missing' => 'Insurance type not recorded',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'insurance_cover' => [
                'label' => 'Check insurance cover and expiry deadline',
                'evidence' => 'insurance',
                'system' => true,
                'fail_reasons' => [
                    'expired' => 'Cover expired or too short for tenure',
                    'missing' => 'No valid insurance on file',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'valuation_or_photos' => [
                'label' => 'Match valuer photos to the pledged asset (front, back, left, right, owner with asset)',
                'evidence' => 'collateral_assets',
                'system' => true,
                'fail_reasons' => [
                    'photos_poor' => 'Valuer photos do not cover the same angles as the asset profile',
                    'valuation_missing' => 'Valuation photos missing where required',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'valuation_fee' => [
                'label' => 'Confirm valuation fee paid by the borrower / group leader',
                'evidence' => 'valuer',
                'system' => true,
                'fail_reasons' => [
                    'fee_unpaid' => 'Valuation fee not paid',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'valuation_report' => [
                'label' => 'Review valuation report / forced sale value',
                'evidence' => 'valuer',
                'system' => true,
                'fail_reasons' => [
                    'report_missing' => 'Valuation report / FSV not on file',
                    'value_insufficient' => 'Forced sale value is too low',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'ltv_covers' => [
                'label' => 'Confirm FSV × LTV covers the requested amount',
                'evidence' => 'valuer',
                'system' => true,
                'fail_reasons' => [
                    'ltv_shortfall' => 'Collateral does not cover the requested amount',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'gps_or_location' => [
                'label' => 'Confirm GPS / location requirements if applicable',
                'evidence' => 'gps',
                'system' => true,
                'fail_reasons' => [
                    'gps_missing' => 'GPS / location requirement not met',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'credit_file' => [
        'label' => 'Credit file wrap-up',
        'phase' => 'security',
        'phase_label' => '3 · Security and close',
        'subjects' => ['borrower'],
        'items' => [
            'crb_reviewed' => [
                'label' => 'CRB report reviewed — other institutions / loans',
                'evidence' => 'crb_loans',
                'risk' => 'critical',
                'fail_reasons' => [
                    'high_exposure' => 'Too much exposure at other institutions',
                    'delinquencies' => 'Active delinquencies on CRB',
                    'cannot_repay_with_external' => 'Cannot service this loan plus other-institution debt',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'risk_flags_addressed' => [
                'label' => 'Risk flags / anomalies addressed in notes',
                'evidence' => 'anomalies',
                'risk' => 'critical',
                'fail_reasons' => [
                    'flags_unaddressed' => 'Critical flags not addressed',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'recommendation_ready' => [
                'label' => 'Screening recommendation ready for committee',
                'evidence' => 'recommendation_gate',
                'fail_reasons' => [
                    'not_ready' => 'File not ready for committee',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'guarantor_wrap' => [
        'label' => 'Guarantor wrap-up',
        'phase' => 'security',
        'phase_label' => '3 · Security and close',
        'subjects' => ['guarantor'],
        'items' => [
            'crb_reviewed' => [
                'label' => 'Guarantor CRB — other institutions / loans',
                'evidence' => 'crb_loans',
                'risk' => 'critical',
                'fail_reasons' => [
                    'high_exposure' => 'Guarantor exposure too high',
                    'delinquencies' => 'Guarantor has delinquencies',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'capacity_confirmed' => [
                'label' => 'Guarantor capacity confirmed to carry the loan',
                'evidence' => 'affordability',
                'fail_reasons' => [
                    'insufficient_capacity' => 'Cannot carry the loan',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'file_ready' => [
                'label' => 'Guarantor file ready for committee',
                'evidence' => 'generic',
                'fail_reasons' => [
                    'not_ready' => 'Guarantor file not ready',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
    'member_wrap' => [
        'label' => 'Member wrap-up',
        'phase' => 'security',
        'phase_label' => '3 · Security and close',
        'subjects' => ['member'],
        'items' => [
            'crb_reviewed' => [
                'label' => 'Member CRB reviewed — other institutions / loans',
                'evidence' => 'crb_loans',
                'risk' => 'critical',
                'fail_reasons' => [
                    'high_exposure' => 'Member exposure too high',
                    'delinquencies' => 'Member has delinquencies',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'docs_ok' => [
                'label' => 'Member identity / income documents acceptable',
                'evidence' => 'documents',
                'fail_reasons' => [
                    'docs_weak' => 'Documents unclear or incomplete',
                    'custom' => 'Other (write reason)',
                ],
            ],
            'file_ready' => [
                'label' => 'Member file ready for group decision',
                'evidence' => 'generic',
                'fail_reasons' => [
                    'not_ready' => 'Member file not ready',
                    'custom' => 'Other (write reason)',
                ],
            ],
        ],
    ],
];
