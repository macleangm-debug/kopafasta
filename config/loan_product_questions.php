<?php

return [
    'EM' => [
        'title_key' => 'borrower.apply.emergency_details.title',
        'fold_into' => 'emergency_details',
        'fields' => [
            [
                'key' => 'emergency_type',
                'label_key' => 'borrower.apply.emergency_details.emergency_type',
                'type' => 'select',
                'required' => true,
                'options_key' => 'borrower.apply.emergency_details.types',
            ],
            [
                'key' => 'supporting_evidence',
                'label_key' => 'borrower.apply.emergency.supporting_evidence',
                'type' => 'document',
                'document_code' => 'supporting_evidence',
                'required' => false,
                'hint_key' => 'borrower.apply.emergency.supporting_evidence_hint',
            ],
        ],
    ],
    'EL' => [
        'title_key' => 'borrower.apply.education_details.title',
        'fold_into' => 'education_details',
        'fields' => [
            [
                'key' => 'school_name',
                'label_key' => 'borrower.apply.education_details.institution',
                'type' => 'text',
                'required' => true,
            ],
            [
                'key' => 'admission_letter',
                'label_key' => 'borrower.apply.education_details.admission_letter',
                'type' => 'document',
                'document_code' => 'admission_fee_letter',
                'required' => true,
            ],
        ],
    ],
    'AG' => [
        'title_key' => 'borrower.apply.agriculture_details.title',
        'fold_into' => 'agriculture_details',
        'fields' => [
            [
                'key' => 'farming_activity_type',
                'label_key' => 'borrower.apply.agriculture_details.activity_type',
                'type' => 'select',
                'required' => true,
                'options_key' => 'borrower.apply.agriculture_details.activity_types',
            ],
            [
                'key' => 'farming_location',
                'label_key' => 'borrower.apply.agriculture_details.location',
                'type' => 'location',
                'required' => true,
            ],
            [
                'key' => 'production_stage',
                'label_key' => 'borrower.apply.agriculture_details.production_stage',
                'type' => 'select',
                'required' => true,
                'options_key' => 'borrower.apply.agriculture_details.production_stages',
            ],
            [
                'key' => 'cycle_end_date',
                'label_key' => 'borrower.apply.agriculture_details.cycle_end_date',
                'type' => 'date',
                'required' => true,
            ],
            [
                'key' => 'activity_budget',
                'label_key' => 'borrower.apply.agriculture_details.activity_budget',
                'type' => 'budget_range',
                'required' => true,
            ],
            [
                'key' => 'expected_revenue',
                'label_key' => 'borrower.apply.agriculture_details.expected_revenue',
                'type' => 'sales_range',
                'required' => true,
            ],
            [
                'key' => 'farm_photos',
                'label_key' => 'borrower.apply.agriculture_details.farm_photos',
                'type' => 'document',
                'document_code' => 'farm_activity_photos',
                'required' => true,
                'hint_key' => 'borrower.apply.agriculture_details.farm_photos_hint',
                'capture' => 'images',
            ],
            [
                'key' => 'land_evidence',
                'label_key' => 'borrower.apply.agriculture_details.land_evidence',
                'type' => 'document',
                'document_code' => 'land_use_evidence',
                'required' => true,
                'hint_key' => 'borrower.apply.agriculture_details.land_evidence_hint',
                'capture' => 'multi_page',
            ],
            [
                'key' => 'input_quotation',
                'label_key' => 'borrower.apply.agriculture_details.input_quotation',
                'type' => 'document',
                'document_code' => 'input_quotation',
                'required' => false,
                'hint_key' => 'borrower.apply.agriculture_details.input_quotation_hint',
            ],
            [
                'key' => 'buyer_evidence',
                'label_key' => 'borrower.apply.agriculture_details.buyer_evidence',
                'type' => 'document',
                'document_code' => 'buyer_order_evidence',
                'required' => false,
                'hint_key' => 'borrower.apply.agriculture_details.buyer_evidence_hint',
            ],
        ],
    ],
    // FC (Artisans) details live on Profile → Activity (activity_type = artisan).
];
