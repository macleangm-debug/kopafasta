<?php

return [
    'EM' => [
        'title' => 'Emergency details',
        'fold_into' => 'quote',
        'fields' => [
            ['key' => 'emergency_type', 'label' => 'Emergency type', 'type' => 'select', 'required' => true, 'options' => [
                'medical'   => 'Medical emergency',
                'funeral'   => 'Funeral / bereavement',
                'accident'  => 'Accident',
                'education' => 'Urgent school fees',
                'other'     => 'Other urgent need',
            ]],
            ['key' => 'supporting_evidence', 'label' => 'Supporting evidence (optional)', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Describe hospital bill, fee letter, etc.'],
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
    // FC (Artisans) details live on Profile → Activity (activity_type = artisan).
];
