<?php

/**
 * Settings-oriented agriculture amount / sales bands.
 * Prefer configuring via Settings later; defaults live here so Blade/JS never hardcode policy.
 * Labels use numeric forms (no English words like Above/Below) so EN/SW shells stay clean.
 */
return [
    'budget' => [
        'below_1m' => ['label' => '< TZS 1,000,000', 'midpoint' => 750000],
        '1m_5m' => ['label' => 'TZS 1,000,000 – 5,000,000', 'midpoint' => 3000000],
        '5m_10m' => ['label' => 'TZS 5,000,000 – 10,000,000', 'midpoint' => 7500000],
        '10m_20m' => ['label' => 'TZS 10,000,000 – 20,000,000', 'midpoint' => 15000000],
        '20m_50m' => ['label' => 'TZS 20,000,000 – 50,000,000', 'midpoint' => 35000000],
        'above_50m' => ['label' => 'TZS 50,000,000+', 'midpoint' => 75000000],
    ],

    'sales' => [
        '5m_10m' => ['label' => 'TZS 5,000,000 – 10,000,000', 'midpoint' => 7500000],
        '10m_20m' => ['label' => 'TZS 10,000,000 – 20,000,000', 'midpoint' => 15000000],
        '20m_50m' => ['label' => 'TZS 20,000,000 – 50,000,000', 'midpoint' => 35000000],
        '50m_100m' => ['label' => 'TZS 50,000,000 – 100,000,000', 'midpoint' => 75000000],
        'above_100m' => ['label' => 'TZS 100,000,000+', 'midpoint' => 150000000],
    ],
];
