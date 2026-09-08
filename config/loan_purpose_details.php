<?php

/**
 * Purpose → post-fee details step mapping for the borrower apply wizard.
 * Used by product-fixed purposes (EL/EM/KB) and by free-purpose products (IL/GL/AB)
 * when the borrower selects Education / Emergency / Agriculture.
 */
return [
    'education' => [
        'step_key' => 'education_details',
        'aliases' => ['school_fees'],
        'questions_key' => 'EL',
    ],
    'emergency' => [
        'step_key' => 'emergency_details',
        'aliases' => ['medical_emergency'],
        'questions_key' => 'EM',
    ],
    'agriculture' => [
        'step_key' => 'agriculture_details',
        'aliases' => [],
        'questions_key' => 'AG',
    ],
];
