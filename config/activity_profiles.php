<?php

return [
    'types' => [
        'business_owner'      => 'Business Owner',
        'farmer'              => 'Farmer',
        'artisan'             => 'Artisan',
        'trader'              => 'Trader',
        'employed'            => 'Employed',
        'student'             => 'Student',
        'casual_worker'       => 'Casual Worker',
        'transport_operator'  => 'Transport Operator',
        'freelancer'          => 'Freelancer',
        'unemployed'          => 'Unemployed',
    ],

    'fields' => [
        'business_owner' => [
            ['key' => 'business_name', 'label' => 'Business name', 'type' => 'text', 'required' => true],
            ['key' => 'monthly_revenue', 'label' => 'Monthly revenue range', 'type' => 'select', 'options' => 'income_ranges', 'required' => true],
        ],
        'farmer' => [
            ['key' => 'farm_type', 'label' => 'Farm type', 'type' => 'text', 'required' => true],
            ['key' => 'farm_size', 'label' => 'Farm size (acres)', 'type' => 'text', 'required' => false],
        ],
        'student' => [
            ['key' => 'school_name', 'label' => 'School name', 'type' => 'text', 'required' => true],
        ],
        'employed' => [
            ['key' => 'employer_name', 'label' => 'Employer name', 'type' => 'text', 'required' => true],
            ['key' => 'job_title', 'label' => 'Job title', 'type' => 'text', 'required' => false],
        ],
        'trader' => [
            ['key' => 'trade_type', 'label' => 'What do you trade?', 'type' => 'text', 'required' => true],
        ],
        'transport_operator' => [
            ['key' => 'vehicle_type', 'label' => 'Vehicle type', 'type' => 'text', 'required' => true],
        ],
        'artisan' => [
            ['key' => 'craft_type', 'label' => 'Craft / skill', 'type' => 'text', 'required' => true],
        ],
        'freelancer' => [
            ['key' => 'service_type', 'label' => 'Service offered', 'type' => 'text', 'required' => true],
        ],
    ],
];
