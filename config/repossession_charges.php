<?php

return [
    /** Default repossession charge matrix by asset type (partner cost + company markup %). */
    'asset_types' => [
        'motorcycle' => [
            'label'          => 'Motorcycle',
            'partner_cost'   => 50_000,
            'markup_percent' => 10,
            'manual_quote'   => false,
        ],
        'saloon_car' => [
            'label'          => 'Saloon car',
            'partner_cost'   => 150_000,
            'markup_percent' => 10,
            'manual_quote'   => false,
        ],
        'suv' => [
            'label'          => 'SUV',
            'partner_cost'   => 250_000,
            'markup_percent' => 10,
            'manual_quote'   => false,
        ],
        'truck' => [
            'label'          => 'Truck',
            'partner_cost'   => 500_000,
            'markup_percent' => 10,
            'manual_quote'   => false,
        ],
        'heavy_machinery' => [
            'label'          => 'Heavy machinery',
            'partner_cost'   => null,
            'markup_percent' => 10,
            'manual_quote'   => true,
        ],
    ],

    /** Default LTV caps (% of forced sale value) by asset type for asset-backed loans. */
    'ltv_percent' => [
        'motorcycle'      => 60,
        'saloon_car'      => 60,
        'suv'             => 60,
        'truck'           => 50,
        'heavy_machinery' => 50,
        'property'        => 70,
        'default'         => 60,
    ],
];
