<?php

/**
 * Tier generation for loan products.
 *
 * Bands span each product's min_amount → max_amount. The smallest band uses
 * loan_products.interest_rate as the maximum monthly rate; larger bands step down.
 */
return [
    /** Default number of amount bands (capped when the product range is narrow). */
    'tier_count' => 4,

    /**
     * Largest-amount band rate = interest_rate × (1 − discount).
     * e.g. 0.30 → top tier 19%, bottom tier ~13.3%.
     */
    'rate_discount_fraction' => 0.30,

    /** Per-product overrides (tier_count, rate_discount_fraction). */
    'products' => [
        'EM' => ['tier_count' => 3, 'rate_discount_fraction' => 0.10],
        'EMG-06' => ['tier_count' => 3, 'rate_discount_fraction' => 0.15],
        'FC' => ['tier_count' => 2, 'rate_discount_fraction' => 0.12],
        'BIZ-30' => ['tier_count' => 2, 'rate_discount_fraction' => 0.25],
    ],
];
