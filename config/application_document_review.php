<?php

return [
    /*
    | Application-scoped document review (does not change the profile document forever).
    | A new loan application starts every profile document as Pending review again.
    */
    'fail_reasons' => [
        'unclear_image' => 'Image / scan unclear or cropped',
        'wrong_document' => 'Wrong document type uploaded',
        'expired' => 'Document expired or out of date',
        'name_mismatch' => 'Name / ID details do not match the profile',
        'incomplete' => 'Document incomplete or pages missing',
        'altered' => 'Appears altered or not authentic',
        'unreadable' => 'Text / figures not readable',
        'custom' => 'Other (write reason)',
    ],

    'remedies' => [
        'request_again' => 'Request a new upload',
        'none' => 'Mark failed only (no new request)',
    ],
];
