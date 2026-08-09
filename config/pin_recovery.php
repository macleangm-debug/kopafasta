<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PIN recovery security questions
    |--------------------------------------------------------------------------
    |
    | The system picks three at random during PIN setup. Members write their own
    | answers (hashed). Forgot PIN compares against those answers only — never
    | CRB or live profile matching.
    |
    */
    'questions_to_ask' => 3,
    'required_correct' => 2,
    'max_attempts' => 5,
    'session_minutes' => (int) env('PIN_RECOVERY_SESSION_MINUTES', 15),

    'bank' => [
        'mother_first_name' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.mother_first_name',
            'input' => 'text',
        ],
        'birth_village' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.birth_village',
            'input' => 'text',
        ],
        'primary_school' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.primary_school',
            'input' => 'text',
        ],
        'childhood_friend' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.childhood_friend',
            'input' => 'text',
        ],
        'first_employer' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.first_employer',
            'input' => 'text',
        ],
        'spouse_middle_name' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.spouse_middle_name',
            'input' => 'text',
        ],
        'nida_middle4' => [
            // Not first 7 (DOB) and not last 4 — member enters middle digits they will remember.
            'prompt_key' => 'site.auth.pin_recovery.bank.nida_middle4',
            'input' => 'digits',
            'digits' => 4,
        ],
        'district_of_birth' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.district_of_birth',
            'input' => 'text',
        ],
        'favourite_teacher' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.favourite_teacher',
            'input' => 'text',
        ],
        'grandfather_name' => [
            'prompt_key' => 'site.auth.pin_recovery.bank.grandfather_name',
            'input' => 'text',
        ],
    ],
];
