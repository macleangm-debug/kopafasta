<?php

return [
    /**
     * Deposit markup calculation base.
     * deposit — markup applied to supplier deposit (launch default)
     * asset_price — markup applied to full asset value
     */
    'markup_base' => env('ASSET_LENDING_MARKUP_BASE', 'deposit'),

    'supplier_types' => [
        'managed_loan'        => 'Managed loan (supplier owns asset, receives installments)',
        'upfront_settlement'  => 'Upfront settlement (company pays supplier on approval)',
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
