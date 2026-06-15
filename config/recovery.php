<?php

return [
    'partner_types' => [
        'call_center' => [
            'label'                      => 'Call Center Partner',
            'vendor_category'            => 'call_center',
            'default_sla_days'           => 7,
            'default_commission_percent' => 10,
            'default_markup_percent'     => 3,
            'default_fee_type'           => 'percentage',
            'default_fixed_amount'       => null,
        ],
        'debt_collector' => [
            'label'                      => 'Debt Collection Partner',
            'vendor_category'            => 'debt_collector',
            'default_sla_days'           => 10,
            'default_commission_percent' => 15,
            'default_markup_percent'     => 3,
            'default_fee_type'           => 'percentage',
            'default_fixed_amount'       => null,
        ],
        'auctioneer' => [
            'label'                      => 'Auction Partner',
            'vendor_category'            => 'auctioneer',
            'default_sla_days'           => 11,
            'default_commission_percent' => 8,
            'default_markup_percent'     => 2,
            'default_fee_type'           => 'percentage',
            'default_fixed_amount'       => null,
        ],
        'legal_partner' => [
            'label'                      => 'Legal Partner',
            'vendor_category'            => 'legal_partner',
            'default_sla_days'           => 21,
            'default_commission_percent' => 10,
            'default_markup_percent'     => 5,
            'default_fee_type'           => 'percentage',
            'default_fixed_amount'       => null,
        ],
        'gps_partner' => [
            'label'                      => 'GPS Partner',
            'vendor_category'            => 'gps_installer',
            'default_sla_days'           => 5,
            'default_commission_percent' => 5,
            'default_markup_percent'     => 2,
            'default_fee_type'           => 'percentage',
            'default_fixed_amount'       => null,
        ],
    ],

    'vendor_categories' => [
        'call_center',
        'debt_collector',
        'auctioneer',
        'legal_partner',
        'gps_installer',
    ],

    /** Ordered recovery workflow — max 30 days total (2 grace + 7 + 10 + 11). Repossession is under debt collector. */
    'escalation_chain' => [
        'call_center',
        'debt_collector',
        'auctioneer',
        'legal_partner',
    ],

    /** principal | outstanding */
    'default_fee_base' => 'principal',

    'portal_actions' => [
        'call_center' => [
            'called' => [
                'label'           => 'Called',
                'collection_type' => 'phone_call',
                'result'          => 'contacted',
                'notes'           => 'optional',
            ],
            'promise_to_pay' => [
                'label'           => 'Promise to pay',
                'collection_type' => 'promise_to_pay',
                'result'          => 'promised_payment',
                'notes'           => 'optional',
            ],
            'unreachable' => [
                'label'           => 'Unreachable',
                'collection_type' => 'phone_call',
                'result'          => 'no_answer',
                'notes'           => 'optional',
            ],
            'resolved' => [
                'label'           => 'Resolved',
                'collection_type' => 'other',
                'result'          => 'resolved',
                'completes'       => true,
                'outcome'         => 'resolved',
                'notes'           => 'optional',
            ],
        ],
        'debt_collector' => [
            'visit_scheduled' => [
                'label'           => 'Visit scheduled',
                'collection_type' => 'field_visit',
                'result'          => 'contacted',
                'notes'           => 'required',
            ],
            'collateral' => [
                'label'           => 'Collateral noted',
                'collection_type' => 'field_visit',
                'result'          => 'contacted',
                'notes'           => 'required',
            ],
            'repossession_scheduled' => [
                'label'           => 'Repossession scheduled',
                'collection_type' => 'field_visit',
                'notes'           => 'optional',
            ],
            'repossession_complete' => [
                'label'           => 'Repossession complete',
                'collection_type' => 'field_visit',
                'accepts_file'    => true,
                'file_label'      => 'Repossession photo',
                'notes'           => 'optional',
            ],
            'gps_check' => [
                'label'           => 'GPS checked',
                'collection_type' => 'field_visit',
                'result'          => 'contacted',
                'notes'           => 'optional',
            ],
            'photo' => [
                'label'           => 'Photo uploaded',
                'collection_type' => 'field_visit',
                'result'          => 'contacted',
                'accepts_file'    => true,
                'file_label'      => 'Field visit photo',
                'notes'           => 'optional',
            ],
            'note' => [
                'label'           => 'Field note',
                'collection_type' => 'other',
                'notes'           => 'required',
            ],
            'resolved' => [
                'label'           => 'Resolved',
                'collection_type' => 'other',
                'result'          => 'resolved',
                'completes'       => true,
                'outcome'         => 'resolved',
                'notes'           => 'optional',
            ],
        ],
        'auctioneer' => [
            'listed' => [
                'label'           => 'Asset listed',
                'collection_type' => 'other',
                'notes'           => 'optional',
            ],
            'sold' => [
                'label'                   => 'Asset sold',
                'collection_type'         => 'other',
                'completes'               => true,
                'outcome'                 => 'sold',
                'requires_auction_proceeds' => true,
                'notes'                   => 'optional',
            ],
            'note' => [
                'label'           => 'Auction note',
                'collection_type' => 'other',
                'notes'           => 'required',
            ],
        ],
        'legal_partner' => [
            'notice_sent' => [
                'label'           => 'Legal notice sent',
                'collection_type' => 'escalation',
                'notes'           => 'optional',
            ],
            'filed' => [
                'label'           => 'Case filed',
                'collection_type' => 'escalation',
                'notes'           => 'optional',
            ],
            'note' => [
                'label'           => 'Legal note',
                'collection_type' => 'other',
                'notes'           => 'required',
            ],
            'resolved' => [
                'label'           => 'Resolved',
                'collection_type' => 'other',
                'completes'       => true,
                'outcome'         => 'resolved',
                'notes'           => 'optional',
            ],
        ],
        'gps_partner' => [
            'located' => [
                'label'           => 'Asset located',
                'collection_type' => 'field_visit',
                'notes'           => 'optional',
            ],
            'removed' => [
                'label'           => 'GPS removed',
                'collection_type' => 'other',
                'completes'       => true,
                'outcome'         => 'gps_removed',
                'notes'           => 'optional',
            ],
            'note' => [
                'label'           => 'GPS note',
                'collection_type' => 'other',
                'notes'           => 'required',
            ],
        ],
    ],
];
