<?php

/**
 * Integration catalog — Settings → Integrations.
 *
 * Multiple partners can share a category (e.g. payment). One partner per
 * category can be marked primary via Setting key integrations.primary.{category}.
 */
return [
    'categories' => [
        'payment' => [
            'label' => 'Payments',
            'description' => 'Mobile money collections, disbursements, and PSP rails.',
        ],
        'messaging' => [
            'label' => 'SMS & Email',
            'description' => 'OTP, repayment reminders, and outbound email.',
        ],
        'compliance' => [
            'label' => 'Compliance',
            'description' => 'Credit bureau and identity data partners.',
        ],
    ],

    'partners' => [
        'payin' => [
            'label' => 'PayIn',
            'category' => 'payment',
            'description' => 'Tanzania mobile money collections & payouts (M-Pesa, Airtel, Tigo, Halo).',
            'settings_route' => 'admin.settings.payin',
            'health_route' => 'admin.settings.payin.health',
            'docs_url' => 'https://docs.payin.co.tz/',
            'status' => 'available',
        ],
        // Placeholders — configure later without changing the hub layout.
        'selcom' => [
            'label' => 'Selcom',
            'category' => 'payment',
            'description' => 'Alternative Tanzania payment partner (coming soon).',
            'settings_route' => null,
            'health_route' => null,
            'docs_url' => null,
            'status' => 'coming_soon',
        ],
        'unitxt' => [
            'label' => 'Unitxt SMS',
            'category' => 'messaging',
            'description' => 'Primary SMS gateway for OTP and transactional alerts.',
            'settings_route' => 'admin.settings.gateways',
            'health_route' => 'admin.settings.gateways.health',
            'docs_url' => null,
            'status' => 'available',
        ],
        'email_smtp' => [
            'label' => 'Email (SMTP)',
            'category' => 'messaging',
            'description' => 'Outbound email via SMTP / SES / Mailgun credentials.',
            'settings_route' => 'admin.settings.gateways',
            'health_route' => null,
            'docs_url' => null,
            'status' => 'available',
        ],
        'crb' => [
            'label' => 'CRB (D&B)',
            'category' => 'compliance',
            'description' => 'Tanzania credit bureau consumer checks.',
            'settings_route' => 'admin.settings.crb',
            'health_route' => 'admin.settings.crb.test',
            'docs_url' => null,
            'status' => 'available',
        ],
    ],
];
