<?php

return [
    'employed_type' => 'employed',

    'informal_types' => [
        'business_owner',
        'trader',
        'freelancer',
        'casual_worker',
        'farmer',
        'artisan',
        'transport_operator',
        'student',
        'unemployed',
    ],

    'business_owner_types' => [
        'business_owner',
        'trader',
    ],

    'self_employed_types' => [
        'freelancer',
        'casual_worker',
        'farmer',
        'transport_operator',
    ],

    'artisan_types' => [
        'artisan',
    ],

    'business_photo_types' => [
        'business_owner',
        'trader',
        'freelancer',
        'casual_worker',
        'farmer',
        'artisan',
        'transport_operator',
    ],

    'employed_required_codes' => [
        'employment_contract',
        'salary_slip',
        'bank_statement',
    ],

    'informal_required_any_codes' => [
        'bank_statement',
        'mobile_money_statement',
        'mpesa_statement',
    ],

    'business_registration_codes' => [
        'business_registration',
        'business_license',
        'tin_certificate',
        'vat_certificate',
    ],

    'informal_optional_codes' => [
        'business_photos',
        'workshop_photos',
    ],
];
