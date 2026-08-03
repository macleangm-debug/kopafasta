<?php

return [
    /**
     * Deposit markup calculation base.
     * deposit — markup applied to supplier deposit (launch default)
     * asset_price — markup applied to full asset value
     */
    'markup_base' => env('ASSET_LENDING_MARKUP_BASE', 'deposit'),

    'supplier_types' => [
        'managed_loan'        => 'Direct repayment (supplier receives principal from installments)',
        'upfront_settlement'  => 'Full upfront payment (company pays supplier on approval)',
    ],

    /** Default supplier type for new suppliers. */
    'default_supplier_type' => 'managed_loan',

    /** Monthly rate used when auto-calculating marketplace weekly instalments. */
    'default_monthly_rate' => 0.12,

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
