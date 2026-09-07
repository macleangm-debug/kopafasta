<?php

return [
    'attributes' => [
        'business_registration_pages' => 'business registration documents',
        'business_registration_pages.*' => 'business registration document',
        'business_registration_pages.0' => 'business registration document',
        'tin_certificate_pages' => 'TIN certificate pages',
        'tin_certificate_pages.*' => 'TIN certificate page',
        'nida_pages' => 'NIDA document pages',
        'nida_pages.*' => 'NIDA document page',
        'id_front' => 'ID front image',
        'id_back' => 'ID back image',
        'passport_photo' => 'passport photo',
        'proof_of_address' => 'proof of address',
        'files' => 'documents',
        'files.*' => 'document',
        'file' => 'document',
    ],

    'custom' => [
        'business_registration_pages.*.max' => 'Each business registration document must be 5 MB or smaller.',
        'business_registration_pages.max' => 'Each business registration document must be 5 MB or smaller.',
        'tin_certificate_pages.*.max' => 'Each TIN certificate page must be 5 MB or smaller.',
        'nida_pages.*.max' => 'Each NIDA document page must be 5 MB or smaller.',
        'files.*.max' => 'Each document must be 5 MB or smaller.',
        'file.max' => 'Each document must be 5 MB or smaller.',
        'id_front.max' => 'Your ID image must be 5 MB or smaller.',
        'id_back.max' => 'Your ID image must be 5 MB or smaller.',
    ],
];
