<?php

/**
 * Integration catalog — Settings → Integrations.
 *
 * Built-in partners merge with admin-added custom partners
 * (Setting key: integrations.custom_partners).
 * Payment partners declare supported channels: mobile_money, bank, or both.
 *
 * GPS tracking URLs are per-device on the loan (entered by the GPS installer),
 * not configured here.
 */
return [
    'categories' => [
        'payment' => [
            'label' => 'Payments',
            'description' => 'Mobile money collections, disbursements, and bank rails.',
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

    'channel_options' => [
        'mobile_money' => 'Mobile money',
        'bank' => 'Bank transfer',
    ],

    'partners' => [
        'payin' => [
            'label' => 'PayIn',
            'category' => 'payment',
            'description' => 'Tanzania mobile money collections & payouts (M-Pesa, Airtel, Tigo, Halo).',
            'settings_route' => 'admin.settings.integrations.partner',
            'health_route' => 'admin.settings.payin.health',
            'docs_url' => 'https://docs.payin.co.tz/',
            'status' => 'available',
            'channels' => ['mobile_money', 'bank'],
            'builtin' => true,
        ],
        'selcom' => [
            'label' => 'Selcom',
            'category' => 'payment',
            'description' => 'Alternative Tanzania payment partner — configure rails and billing when ready.',
            'settings_route' => 'admin.settings.integrations.partner',
            'health_route' => null,
            'docs_url' => null,
            'status' => 'available',
            'channels' => ['mobile_money', 'bank'],
            'builtin' => true,
        ],
        'unitxt' => [
            'label' => 'Unitxt SMS',
            'category' => 'messaging',
            'description' => 'Primary SMS gateway for OTP and transactional reminders.',
            'settings_route' => 'admin.settings.integrations.partner',
            'health_route' => 'admin.settings.gateways.health',
            'docs_url' => null,
            'status' => 'available',
            'channels' => [],
            'builtin' => true,
        ],
        'email_smtp' => [
            'label' => 'Email (SMTP)',
            'category' => 'messaging',
            'description' => 'Outbound email via SMTP / SES / Mailgun credentials.',
            'settings_route' => 'admin.settings.integrations.partner',
            'health_route' => null,
            'docs_url' => null,
            'status' => 'available',
            'channels' => [],
            'builtin' => true,
        ],
        'crb' => [
            'label' => 'CRB (D&B)',
            'category' => 'compliance',
            'description' => 'Tanzania credit bureau consumer checks.',
            'settings_route' => 'admin.settings.integrations.partner',
            'health_route' => 'admin.settings.crb.test',
            'docs_url' => null,
            'status' => 'available',
            'channels' => [],
            'builtin' => true,
        ],
    ],
];
