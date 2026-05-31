<?php

return [
    'EM' => [
        'title' => 'Emergency details',
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
        'title' => 'Education details',
        'fields' => [
            ['key' => 'school_name', 'label' => 'School / institution name', 'type' => 'text', 'required' => true],
            ['key' => 'admission_letter', 'label' => 'Admission or fee letter reference', 'type' => 'text', 'required' => true, 'placeholder' => 'Letter number or summary'],
        ],
    ],
    'FC' => [
        'title' => 'Artisan / workshop details',
        'fields' => [
            ['key' => 'craft_type', 'label' => 'Craft / trade type', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Fundi wa umeme, tailor, carpenter'],
            ['key' => 'workshop_info', 'label' => 'Workshop information', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Location, tools owned, typical customers'],
        ],
    ],
];
