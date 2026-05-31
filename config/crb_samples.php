<?php

/**
 * Sample NIDA numbers for CRB stub / sandbox testing.
 * Used when CRB_DRIVER=stub or Admin → KYC → “CRB sandbox / stub mode” is enabled.
 *
 * Format: XXXXXXXX-XXXXX-XXXXX-XX (20 digits)
 */
return [

    'enabled' => env('CRB_SAMPLES_ENABLED', true),

    'scenarios' => [

        'verified' => [
            'label'       => 'Single hit (verified)',
            'nida'        => '19810713-00001-23456-78',
            'description' => 'Returns a successful match (based on D&B sample persona).',
            'full_name'   => 'Gaspari Malim Shiliba Shiliba',
            'first_name'  => 'Gaspari Malim Shiliba',
            'last_name'   => 'Shiliba',
            'date_of_birth' => '1981-07-13',
            'gender'      => 'male',
            'search_score'=> '100%',
            'crb_ruid'    => 'stub-hit-11011301',
        ],

        'multihit' => [
            'label'       => 'Multiple matches',
            'nida'        => '19890304-00001-56789-01',
            'description' => 'Returns two CRB candidates — pick “This is me” on profile.',
            'search_request_id' => 'stub-search-9001',
            'candidates'  => [
                [
                    'entity_key' => '44934',
                    'name'       => 'Joashi Yuda Kinyamogoha',
                    'dob'        => '4-Mar-1989',
                    'gender'     => 'Male',
                    'identifier' => '19890304-00001-56789-01',
                    'score'      => 92,
                    'first_name' => 'Joashi Yuda',
                    'last_name'  => 'Kinyamogoha',
                    'date_of_birth' => '1989-03-04',
                ],
                [
                    'entity_key' => '44935',
                    'name'       => 'Joashi Yuda Kinyamogoha Jr',
                    'dob'        => '4-Mar-1991',
                    'gender'     => 'Male',
                    'identifier' => '19890304-00001-56789-01',
                    'score'      => 78,
                    'first_name' => 'Joashi Yuda',
                    'last_name'  => 'Kinyamogoha Jr',
                    'date_of_birth' => '1991-03-04',
                ],
            ],
        ],

        'no_hit' => [
            'label'       => 'No bureau match',
            'nida'        => '20000101-99999-99999-99',
            'description' => 'Simulates CRB “no matching identity record”.',
        ],

    ],

];
