<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRB driver
    |--------------------------------------------------------------------------
    | live  — D&B Tanzania Credit Bureau SOAP (GetLiveCIR)
    | stub  — Local mock for development / sandbox without credentials
    */
    'driver' => env('CRB_DRIVER', 'stub'),

    'endpoint' => env('CRB_ENDPOINT'),

    'email' => env('CRB_EMAIL'),

    'password' => env('CRB_PASSWORD'),

    'timeout' => (int) env('CRB_TIMEOUT', 30),

    /*
    | From crb/Live Request Manual/XML Templates/
    | Consumer: REPORT_ID 14616, SUBJECT_TYPE 1
    | Commercial: REPORT_ID 14618, SUBJECT_TYPE 2
    */
    'consumer_report_id' => env('CRB_CONSUMER_REPORT_ID', '14616'),

    'consumer_subject_type' => env('CRB_CONSUMER_SUBJECT_TYPE', '1'),

    'purpose_of_inquiry' => env('CRB_PURPOSE_OF_INQUIRY', '1'),

    'response_type' => '1',

    'auto_select_min_score' => (int) env('CRB_AUTO_SELECT_MIN_SCORE', 95),

];
