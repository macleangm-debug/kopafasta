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
            ['key' => 'region', 'label' => 'Region', 'type' => 'region', 'required' => true],
            ['key' => 'district', 'label' => 'District', 'type' => 'district', 'required' => true],
            ['key' => 'street', 'label' => 'Street', 'type' => 'text', 'required' => true],
            ['key' => 'employee_count', 'label' => 'Number of employees', 'type' => 'select', 'options' => [
                '1' => 'Just me',
                '2_5' => '2 – 5',
                '6_10' => '6 – 10',
                '11_plus' => '11+',
            ], 'required' => true],
        ],
        'farmer' => [
            ['key' => 'farm_type', 'label' => 'Farm type', 'type' => 'select', 'options' => [
                'crops' => 'Crops',
                'livestock' => 'Livestock',
                'mixed_farming' => 'Mixed farming',
                'horticulture' => 'Horticulture',
                'other' => 'Other',
            ], 'required' => true],
            ['key' => 'farm_size', 'label' => 'Farm size', 'type' => 'select', 'options' => [
                'small' => 'Small',
                'medium' => 'Medium',
                'large' => 'Large',
            ], 'required' => true],
        ],
        'artisan' => [
            ['key' => 'skill_type', 'label' => 'Skill type', 'type' => 'select', 'options' => [
                'barber' => 'Barber',
                'hair_stylist' => 'Hair stylist',
                'tailor' => 'Tailor',
                'welder' => 'Welder',
                'carpenter' => 'Carpenter',
                'mason' => 'Mason',
                'mechanic' => 'Mechanic',
                'painter' => 'Painter',
                'other' => 'Other',
            ], 'required' => true],
        ],
        'student' => [
            ['key' => 'school_name', 'label' => 'School name', 'type' => 'text', 'required' => true],
        ],
        'employed' => [
            ['key' => 'employer_name', 'label' => 'Employer name', 'type' => 'text', 'required' => true],
            ['key' => 'job_title', 'label' => 'Job title', 'type' => 'text', 'required' => false],
        ],
        'trader' => [
            ['key' => 'trade_type', 'label' => 'What do you trade?', 'type' => 'select', 'options' => [
                'food' => 'Food',
                'clothing' => 'Clothing',
                'electronics' => 'Electronics',
                'hardware' => 'Hardware',
                'agriculture' => 'Agriculture',
                'household_goods' => 'Household goods',
                'other' => 'Other',
            ], 'required' => true],
        ],
        'transport_operator' => [
            ['key' => 'vehicle_type', 'label' => 'Vehicle type', 'type' => 'select', 'options' => [
                'motorcycle' => 'Motorcycle',
                'bajaji' => 'Bajaji',
                'car' => 'Car',
                'bus' => 'Bus',
                'truck' => 'Truck',
                'other' => 'Other',
            ], 'required' => true],
        ],
        'freelancer' => [
            ['key' => 'service_offered', 'label' => 'Service offered', 'type' => 'select', 'options' => [
                'graphic_design' => 'Graphic design',
                'software_development' => 'Software development',
                'photography' => 'Photography',
                'consultancy' => 'Consultancy',
                'marketing' => 'Marketing',
                'writing' => 'Writing',
                'other' => 'Other',
            ], 'required' => true],
        ],
    ],
];
