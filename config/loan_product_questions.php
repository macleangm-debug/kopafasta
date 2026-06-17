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
            ['key' => 'craft_type', 'label' => 'Craft / trade type', 'type' => 'select', 'required' => true, 'options' => [
                'tailor'        => 'Tailor',
                'barber'        => 'Barber',
                'hair_stylist'  => 'Hair stylist',
                'painter'       => 'Painter',
                'potter'        => 'Potter',
                'sculptor'      => 'Sculptor',
                'carpenter'     => 'Carpenter',
                'welder'        => 'Welder',
                'mason'         => 'Mason',
                'mechanic'      => 'Mechanic',
                'electrician'   => 'Electrician',
                'other'         => 'Other',
            ]],
            ['key' => 'workshop_location', 'label' => 'Workshop location', 'type' => 'tz_address', 'prefix' => 'workshop', 'required' => true],
        ],
    ],
];
