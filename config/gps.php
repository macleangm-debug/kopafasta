<?php

/**
 * GPS devices are per-loan: the GPS installer enters serial + tracking URL on install.
 * Platform map viewing is toggled via Setting gps.map_enabled (Recovery Policy → Timeline).
 */
return [
    'default_provider' => 'generic',

    'providers' => [
        'generic' => ['label' => 'Generic / other provider'],
        'cartrack' => ['label' => 'Cartrack'],
        'gridtrack' => ['label' => 'Gridtrack'],
        'miway' => ['label' => 'MiWay / other TZ provider'],
    ],
];
