<?php

return [
    /**
     * Deposit markup calculation base.
     * deposit — markup applied to supplier deposit (launch default)
     * asset_price — markup applied to full asset value
     */
    'markup_base' => env('ASSET_LENDING_MARKUP_BASE', 'deposit'),

    'supplier_types' => [
        'managed_loan'        => 'Service / Collection',
        'upfront_settlement'  => 'Capital-funded purchase',
    ],

    /** Default supplier type for new suppliers. */
    'default_supplier_type' => 'managed_loan',

    /** Monthly rate used when auto-calculating marketplace weekly instalments. */
    'default_monthly_rate' => 0.12,

    /**
     * Deposit tiers use asset purchase price; financing tiers use financed balance after deposit.
     * Product Configuration is the active editor. Settings storage is the shared read source.
     */
    'deposit_tiers' => [
        ['from' => 0, 'to' => 5_000_000, 'percent' => 10, 'active' => true],
        ['from' => 5_000_001, 'to' => 15_000_000, 'percent' => 8, 'active' => true],
        ['from' => 15_000_001, 'to' => 30_000_000, 'percent' => 6, 'active' => true],
        ['from' => 30_000_001, 'to' => 50_000_000, 'percent' => 4, 'active' => true],
        ['from' => 50_000_001, 'to' => 100_000_000, 'percent' => 3, 'active' => true],
        ['from' => 100_000_001, 'to' => null, 'percent' => 2, 'active' => true],
    ],

    'financing_tiers' => [
        ['from' => 0, 'to' => 10_000_000, 'monthly_rate_percent' => 3.00, 'method' => 'reducing_balance', 'max_tenure_months' => 6, 'active' => true],
        ['from' => 10_000_001, 'to' => 25_000_000, 'monthly_rate_percent' => 2.50, 'method' => 'reducing_balance', 'max_tenure_months' => 6, 'active' => true],
        ['from' => 25_000_001, 'to' => 50_000_000, 'monthly_rate_percent' => 2.00, 'method' => 'reducing_balance', 'max_tenure_months' => 6, 'active' => true],
        ['from' => 50_000_001, 'to' => 100_000_000, 'monthly_rate_percent' => 1.50, 'method' => 'reducing_balance', 'max_tenure_months' => 6, 'active' => true],
        ['from' => 100_000_001, 'to' => null, 'monthly_rate_percent' => 1.25, 'method' => 'reducing_balance', 'max_tenure_months' => 6, 'active' => true],
    ],

    /**
     * Asset categories with workflow requirements.
     * Keys are stored on marketplace_assets.category.
     */
    'categories' => [
        'vehicle' => [
            'label'                    => 'Vehicle',
            'gps_required'             => true,
            'insurance_required'       => true,
            'valuation_required'       => false,
            'ownership_transfer_required' => true,
        ],
        'motorcycle' => [
            'label'                    => 'Motorcycle',
            'gps_required'             => true,
            'insurance_required'       => true,
            'valuation_required'       => false,
            'ownership_transfer_required' => true,
        ],
        'truck' => [
            'label'                    => 'Truck',
            'gps_required'             => true,
            'insurance_required'       => true,
            'valuation_required'       => false,
            'ownership_transfer_required' => true,
        ],
        'machinery' => [
            'label'                    => 'Machinery',
            'gps_required'             => true,
            'insurance_required'       => true,
            'valuation_required'       => false,
            'ownership_transfer_required' => true,
        ],
        'house' => [
            'label'                    => 'House',
            'gps_required'             => false,
            'insurance_required'       => true,
            'valuation_required'       => true,
            'ownership_transfer_required' => true,
        ],
        'land' => [
            'label'                    => 'Land',
            'gps_required'             => false,
            'insurance_required'       => false,
            'valuation_required'       => true,
            'ownership_transfer_required' => true,
        ],
        'other' => [
            'label'                    => 'Other',
            'gps_required'             => false,
            'insurance_required'       => false,
            'valuation_required'       => false,
            'ownership_transfer_required' => false,
        ],
    ],

    /** Legacy category map (old marketplace keys → new keys). */
    'legacy_category_map' => [
        'vehicles'    => 'vehicle',
        'motorcycles' => 'motorcycle',
        'equipment'   => 'machinery',
        'machinery'   => 'machinery',
    ],

    'max_asset_photos' => (int) env('ASSET_LENDING_MAX_PHOTOS', 4),

    /**
     * Oldest acceptable vehicle manufacture year = current year − this value.
     * Example: 10 in 2026 → vehicles from 2016 through 2026.
     */
    'vehicle_max_age_years' => (int) env('ASSET_LENDING_VEHICLE_MAX_AGE_YEARS', 10),

    'asset_request_statuses' => [
        'sourcing'  => 'Asset sourcing request',
        'reviewing' => 'Under review',
        'matched'   => 'Matched to supplier',
        'closed'    => 'Closed',
    ],

    'handover_milestones' => [
        'asset_ready'       => 'Asset ready',
        'gps_installed'     => 'GPS installed',
        'insurance_active'  => 'Insurance active',
        'asset_handed_over' => 'Asset handed over',
    ],
];
