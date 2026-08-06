<?php

/**
 * Manual screening desk checklist — grouped work items for credit analysts.
 * State is stored on loan_applications.screening_payload.screening_checklist.
 */
return [
    'identity' => [
        'label' => 'Identity & KYC',
        'items' => [
            'nida_vs_dob' => 'Compare NIDA number to date of birth',
            'name_vs_crb' => 'Map customer name to CRB report',
            'face_vs_nida' => 'Compare face verification to NIDA photo',
            'phone_ownership' => 'Confirm mobile number ownership',
            'id_document_quality' => 'Review ID / NIDA document quality',
        ],
    ],
    'residence' => [
        'label' => 'Residence',
        'items' => [
            'address_consistency' => 'Confirm residence details are consistent',
            'local_government' => 'Check with Local Government Officer',
            'landlord_or_owner' => 'Verify landlord / property ownership where applicable',
            'utility_or_proof' => 'Review residence proof / utility evidence',
        ],
    ],
    'contacts' => [
        'label' => 'Contacts & references',
        'items' => [
            'call_guarantor' => 'Check with guarantor',
            'call_next_of_kin' => 'Check with next of kin',
            'call_references' => 'Call listed references / contacts',
            'guarantor_capacity' => 'Assess guarantor capacity to carry the loan',
        ],
    ],
    'activity_income' => [
        'label' => 'Activity & income',
        'items' => [
            'activity_plausible' => 'Review stated business / employment activity',
            'income_evidence' => 'Verify income evidence against affordability',
            'bank_or_mobile_money' => 'Review bank / mobile-money statement patterns',
        ],
    ],
    'documents' => [
        'label' => 'Documents',
        'items' => [
            'required_docs_complete' => 'Confirm required documents are complete',
            'doc_authenticity' => 'Spot-check document authenticity / consistency',
            'requested_docs_reviewed' => 'Review any requested follow-up documents',
        ],
    ],
    'collateral' => [
        'label' => 'Collateral & assets',
        'items' => [
            'asset_identity' => 'Confirm asset identity (registration / serial / title)',
            'insurance_cover' => 'Check insurance cover and expiry deadline',
            'ownership_docs' => 'Review ownership / transfer documents',
            'valuation_or_photos' => 'Review valuation / asset photos where available',
            'gps_or_location' => 'Confirm GPS / location requirements if applicable',
        ],
    ],
    'credit_file' => [
        'label' => 'Credit file wrap-up',
        'items' => [
            'crb_reviewed' => 'CRB report reviewed and annotated',
            'risk_flags_addressed' => 'Risk flags / anomalies addressed in notes',
            'recommendation_ready' => 'Screening recommendation ready for committee',
        ],
    ],
];
