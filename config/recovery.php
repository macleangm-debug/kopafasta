<?php

return [
    'partner_types' => [
        'call_center' => [
            'label' => 'Call Center Partner',
            'vendor_category' => 'call_center',
            'default_priority' => 1,
            'default_sla_days' => 7,
            'default_commission_percent' => 10,
            'default_markup_percent' => 3,
            'default_fee_type' => 'percentage',
            'default_fixed_amount' => null,
            'default_loan_types' => 'all',
            'default_collateral_scope' => 'all',
            'default_auto_escalate' => true,
        ],
        'debt_collector' => [
            'label' => 'Debt Collection Partner',
            'vendor_category' => 'debt_collector',
            'default_priority' => 2,
            'default_sla_days' => 10,
            'default_commission_percent' => 15,
            'default_markup_percent' => 3,
            'default_fee_type' => 'percentage',
            'default_fixed_amount' => null,
            'default_loan_types' => 'all',
            'default_collateral_scope' => 'all',
            'default_auto_escalate' => true,
        ],
        'auctioneer' => [
            'label' => 'Auction Partner',
            'vendor_category' => 'auctioneer',
            'default_priority' => 3,
            'default_sla_days' => 11,
            'default_commission_percent' => 8,
            'default_markup_percent' => 2,
            'default_fee_type' => 'percentage',
            'default_fixed_amount' => null,
            'default_loan_types' => 'all',
            'default_collateral_scope' => 'secured',
            'default_auto_escalate' => true,
        ],
        'legal_partner' => [
            'label' => 'Legal Partner',
            'vendor_category' => 'legal_partner',
            'default_priority' => 4,
            'default_sla_days' => 21,
            'default_commission_percent' => 0,
            // 0% is the current Settings value, not a permanent special rule — markup remains configurable.
            'default_markup_percent' => 0,
            'default_fee_type' => 'fixed',
            'default_fixed_amount' => 100_000,
            'charges_borrower' => true,
            'default_loan_types' => 'all',
            'default_collateral_scope' => 'all',
            'default_auto_escalate' => true,
        ],
        'towing' => [
            'label' => 'Towing Partner',
            'vendor_category' => 'towing',
            'default_priority' => 6,
            'default_sla_days' => 3,
            'default_commission_percent' => 0,
            'default_markup_percent' => 10,
            'default_fee_type' => 'fixed',
            'default_fixed_amount' => null, // unconfigured until owner sets commercial tariff
            'charges_borrower' => true,
            'default_loan_types' => 'all',
            'default_collateral_scope' => 'secured',
            'default_auto_escalate' => false,
            'pricing_notes' => 'Partner towing fee + Kopafasta markup = borrower towing charge. May vary by asset class in Settings.',
        ],
        'yard' => [
            'label' => 'Yard / Storage Partner',
            'vendor_category' => 'yard',
            'default_priority' => 7,
            'default_sla_days' => 30,
            'default_commission_percent' => 0,
            'default_markup_percent' => 10,
            'default_fee_type' => 'fixed',
            'default_fixed_amount' => null, // daily storage uses yard_storage.* below
            'charges_borrower' => true,
            'default_loan_types' => 'all',
            'default_collateral_scope' => 'secured',
            'default_auto_escalate' => false,
            'pricing_notes' => 'Daily storage: partner_daily_rate + platform markup = borrower daily yard charge.',
        ],
        'gps_partner' => [
            'label' => 'GPS Partner',
            'vendor_category' => 'gps_installer',
            'default_priority' => 5,
            'default_sla_days' => 5,
            'default_commission_percent' => 0,
            'default_markup_percent' => 0,
            'default_fee_type' => 'fixed',
            'default_fixed_amount' => null,
            'charges_borrower' => false,
            'default_loan_types' => 'all',
            'default_collateral_scope' => 'secured',
            'default_auto_escalate' => false,
        ],
    ],

    'vendor_categories' => [
        'call_center',
        'debt_collector',
        'auctioneer',
        'legal_partner',
        'gps_installer',
        'towing',
        'yard',
    ],

    /**
     * Yard / storage daily commercial model (Settings-owned; accrual engine is backlog).
     * borrower_daily_charge = partner_daily_rate + platform markup (percent or fixed).
     */
    'yard_storage' => [
        'partner_daily_rate' => null,
        'markup_type' => 'percent', // percent | fixed
        'markup_percent' => 10,
        'markup_fixed' => null,
        'has_markup' => true,
    ],

    /**
     * Towing may optionally use an asset-class matrix later (same pattern as repossession).
     * Until configured, use recovery.fixed_amount.towing + markup.
     */
    'towing_charges' => [
        'default_partner_fee' => null,
        'default_markup_percent' => 10,
        'has_markup' => true,
    ],

    /** Ordered recovery workflow — max 30 days total (2 grace + 7 + 10 + 11). Repossession is under debt collector. */
    'escalation_chain' => [
        'call_center',
        'debt_collector',
        'auctioneer',
        'legal_partner',
    ],

    /** Days after repossession before auto-assigning an auctioneer (borrower redemption window). */
    'default_auction_hold_days' => 4,

    /** Days before sla_due_at to remind the assigned recovery partner. Not the origination 12h/4h schedule. */
    'default_remind_days' => '3,1',

    /** Outcomes counted as successful recovery in partner KPIs. */
    'recovered_outcomes' => [
        'resolved',
        'sold',
        'gps_removed',
        'recovered',
        'full_payment',
    ],

    /** principal | outstanding */
    'default_fee_base' => 'principal',

    'portal_actions' => [
        'call_center' => [
            'called' => [
                'label' => 'Called',
                'collection_type' => 'phone_call',
                'result' => 'contacted',
                'notes' => 'optional',
                'requires_contact' => true,
            ],
            'promise_to_pay' => [
                'label' => 'Promise to pay',
                'collection_type' => 'promise_to_pay',
                'result' => 'promised_payment',
                'notes' => 'optional',
                'requires_contact' => true,
            ],
            'unreachable' => [
                'label' => 'Unreachable',
                'collection_type' => 'phone_call',
                'result' => 'no_answer',
                'notes' => 'optional',
                'requires_contact' => true,
            ],
            'resolved' => [
                'label' => 'Resolved',
                'collection_type' => 'other',
                'result' => 'resolved',
                'completes' => true,
                'outcome' => 'resolved',
                'notes' => 'optional',
            ],
        ],
        'debt_collector' => [
            'called' => [
                'label' => 'Called',
                'collection_type' => 'phone_call',
                'result' => 'contacted',
                'notes' => 'optional',
                'requires_contact' => true,
            ],
            'visit_scheduled' => [
                'label' => 'Visit scheduled',
                'collection_type' => 'field_visit',
                'result' => 'contacted',
                'notes' => 'required',
                'requires_contact' => true,
            ],
            'collateral' => [
                'label' => 'Collateral noted',
                'collection_type' => 'field_visit',
                'result' => 'contacted',
                'notes' => 'required',
            ],
            'repossession_scheduled' => [
                'label' => 'Repossession scheduled',
                'collection_type' => 'field_visit',
                'notes' => 'optional',
            ],
            'repossession_complete' => [
                'label' => 'Repossession complete',
                'collection_type' => 'field_visit',
                'accepts_file' => true,
                'file_label' => 'Repossession photo',
                'notes' => 'optional',
                'completes' => true,
                'outcome' => 'repossessed',
                'starts_auction_hold' => true,
            ],
            'gps_check' => [
                'label' => 'GPS checked',
                'collection_type' => 'field_visit',
                'result' => 'contacted',
                'notes' => 'optional',
            ],
            'photo' => [
                'label' => 'Photo uploaded',
                'collection_type' => 'field_visit',
                'result' => 'contacted',
                'accepts_file' => true,
                'file_label' => 'Field visit photo',
                'notes' => 'optional',
            ],
            'note' => [
                'label' => 'Field note',
                'collection_type' => 'other',
                'notes' => 'required',
            ],
            'resolved' => [
                'label' => 'Resolved',
                'collection_type' => 'other',
                'result' => 'resolved',
                'completes' => true,
                'outcome' => 'resolved',
                'notes' => 'optional',
            ],
        ],
        'auctioneer' => [
            'listed' => [
                'label' => 'Asset listed',
                'collection_type' => 'other',
                'notes' => 'optional',
                'marks_auction_listed' => true,
            ],
            'sold' => [
                'label' => 'Asset sold',
                'collection_type' => 'other',
                'completes' => true,
                'outcome' => 'sold',
                'requires_auction_proceeds' => true,
                'notes' => 'optional',
                'marks_auction_sold' => true,
            ],
            'note' => [
                'label' => 'Auction note',
                'collection_type' => 'other',
                'notes' => 'required',
            ],
        ],
        'legal_partner' => [
            'called' => [
                'label' => 'Called',
                'collection_type' => 'phone_call',
                'result' => 'contacted',
                'notes' => 'optional',
                'requires_contact' => true,
            ],
            'notice_sent' => [
                'label' => 'Legal notice sent',
                'collection_type' => 'escalation',
                'notes' => 'optional',
            ],
            'filed' => [
                'label' => 'Case filed',
                'collection_type' => 'escalation',
                'notes' => 'optional',
            ],
            'note' => [
                'label' => 'Legal note',
                'collection_type' => 'other',
                'notes' => 'required',
            ],
            'resolved' => [
                'label' => 'Resolved',
                'collection_type' => 'other',
                'completes' => true,
                'outcome' => 'resolved',
                'notes' => 'optional',
            ],
        ],
        'gps_partner' => [
            'located' => [
                'label' => 'Asset located',
                'collection_type' => 'field_visit',
                'notes' => 'optional',
            ],
            'removed' => [
                'label' => 'GPS removed',
                'collection_type' => 'other',
                'completes' => true,
                'outcome' => 'gps_removed',
                'notes' => 'optional',
            ],
            'note' => [
                'label' => 'GPS note',
                'collection_type' => 'other',
                'notes' => 'required',
            ],
        ],
    ],
];
