<?php

return [
    /*
    | Landing page A/B variants. Set via ?landing=a|b or persisted in session.
    | Variant A: split hero + steps strip (default)
    | Variant B: centered hero + product-first layout
    */
    'default_variant' => env('SITE_LANDING_VARIANT', 'a'),

    'variants' => [
        'a' => [
            'label' => 'Split hero',
            'hero_partial' => 'site.home._hero-a',
            'products_first' => false,
        ],
        'b' => [
            'label' => 'Product-first',
            'hero_partial' => 'site.home._hero-b',
            'products_first' => true,
        ],
    ],
];
