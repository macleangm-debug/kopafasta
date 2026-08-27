<?php

/**
 * Required valuation evidence by asset family.
 * Settings Hub may override via Setting group `valuation_evidence`.
 * Borrower profile photos stay on CustomerAsset::photoAngleLabels()
 * (front/back/left/right/owner). Extra valuer angles are inspection evidence.
 */
return [
    'vehicle' => [
        'required' => ['front', 'back', 'left', 'right', 'dashboard', 'engine', 'vin', 'owner'],
        'optional' => ['damage'],
    ],
    'land' => [
        'required' => ['front', 'left', 'right', 'access', 'landmark', 'survey', 'owner'],
        'optional' => ['damage'],
    ],
    'building' => [
        'required' => ['front', 'back', 'interior', 'utilities', 'access', 'owner'],
        'optional' => ['damage'],
    ],
];
