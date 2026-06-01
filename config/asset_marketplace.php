<?php

return [
    'reservation_fee' => (float) env('ASSET_RESERVATION_FEE', 50000),

    'ownership_note' => 'KopaFasta remains legal owner during financing. Ownership transfers after full repayment, no outstanding charges, and transfer fee payment.',

    'asset_loan_product_code' => env('ASSET_LOAN_PRODUCT_CODE', 'AL'),

    'categories' => [
        'vehicles'    => 'Vehicles',
        'motorcycles' => 'Motorcycles',
        'equipment'   => 'Equipment',
        'machinery'   => 'Machinery',
    ],
];
