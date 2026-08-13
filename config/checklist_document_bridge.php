<?php

/**
 * Maps screening checklist items → profile document type codes.
 * Reviewing those files in Documents drives the checklist so screeners
 * do not re-do the same file check twice.
 */
return [
    'bundles' => [
        'id_quality' => [
            'national_id_front',
            'national_id_back',
            'national_id',
            'nida',
            'passport',
            'passport_bio',
            'driving_license',
        ],
        'residence_proof' => [
            'residence_letter',
            'residence_verification',
            'lga_letter',
            'lgo_letter',
            'utility_bill',
            'tenancy_agreement',
        ],
        'income_statements' => [
            'bank_statement',
            'mobile_money_statement',
            'mpesa_statement',
            'salary_slip',
        ],
        'activity_proof' => [
            'employment_contract',
            'business_license',
            'business_registration',
            'business_photos',
            'tin_certificate',
            'salary_slip',
        ],
    ],

    /** checklist full key => bundle key (or 'profile_all' for authenticity roll-up) */
    'item_bundles' => [
        'identity.face_vs_nida' => 'id_quality',
        'identity.id_document_quality' => 'id_quality',
        'residence.utility_or_proof' => 'residence_proof',
        'activity_income.activity_plausible' => 'activity_proof',
        'activity_income.income_evidence' => 'income_statements',
        'activity_income.bank_or_mobile_money' => 'income_statements',
        'documents.doc_authenticity' => 'profile_all',
        'documents.falsified_docs' => 'profile_all',
    ],

    /**
     * Items where Documents verify/fail is enough to set the checklist verdict.
     * Judgment items (face likeness, statement patterns, revenue match) stay human
     * but still show Documents status as supporting evidence.
     */
    'auto_from_documents' => [
        'identity.id_document_quality',
        'residence.utility_or_proof',
        'documents.doc_authenticity',
        'documents.falsified_docs',
    ],

    /**
     * When these checklist keys are all Pass for a subject, pending Documents in that
     * bundle are auto-verified (Documents ← checklist). Excludes profile_all roll-ups
     * so authenticity does not have to pass before the first file can be cleared.
     */
    'reverse_auto_verify' => [
        'id_quality' => [
            'identity.face_vs_nida',
            'identity.id_document_quality',
        ],
        'residence_proof' => [
            'residence.utility_or_proof',
        ],
        'income_statements' => [
            'activity_income.income_evidence',
            'activity_income.bank_or_mobile_money',
        ],
        'activity_proof' => [
            'activity_income.activity_plausible',
        ],
    ],
];
