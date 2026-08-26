<?php

/**
 * Permission catalog — enforced via Gate + PermissionService.
 * Keys are stored in roles.permissions JSON.
 */
return [
    'modules' => [
        'applications' => 'Loan applications',
        'loans'        => 'Loans & disbursement',
        'customers'    => 'Customers & KYC',
        'support'      => 'Support',
        'finance'      => 'Finance',
        'reports'      => 'Reports & analytics',
        'settings'     => 'Settings & admin',
        'marketplace'  => 'Asset marketplace',
        'partners'     => 'Partners',
        'marketing'    => 'Growth & marketing',
        'communications' => 'Communications',
        'content'      => 'Content',
    ],

    'permissions' => [
        // Applications workflow
        'applications.view'              => ['label' => 'View applications', 'module' => 'applications'],
        'applications.acknowledge'       => ['label' => 'Acknowledge new applications', 'module' => 'applications'],
        'applications.review'            => ['label' => 'Screen & move to credit review', 'module' => 'applications'],
        'applications.pre_approve'       => ['label' => 'Pre-approve applications', 'module' => 'applications'],
        'applications.approve'           => ['label' => 'Final approval', 'module' => 'applications'],
        'applications.reject'            => ['label' => 'Reject applications', 'module' => 'applications'],
        'applications.disburse'          => ['label' => 'Move to disbursement stage', 'module' => 'applications'],
        'applications.request_documents' => ['label' => 'Request borrower documents', 'module' => 'applications'],
        'applications.edit'              => ['label' => 'Edit application details', 'module' => 'applications'],

        // Customers & KYC
        'customers.view'                 => ['label' => 'View customers', 'module' => 'customers'],
        'customers.edit'                 => ['label' => 'Edit customers', 'module' => 'customers'],
        'kyc.review'                     => ['label' => 'Review KYC & face verification', 'module' => 'customers'],
        'membership.approve_payments'    => ['label' => 'Approve membership payments', 'module' => 'customers'],
        'grades.override'                => ['label' => 'Override customer grade (reason + expiry required)', 'module' => 'customers'],

        // Loans
        'loans.view'                     => ['label' => 'View loans', 'module' => 'loans'],
        'loans.disburse'                 => ['label' => 'Disburse loans', 'module' => 'loans'],

        // Finance — configuration
        'finance.accounts'               => ['label' => 'Chart of accounts & bank accounts', 'module' => 'finance'],
        'finance.methods'                => ['label' => 'Disbursement/repayment methods, charges & write-offs', 'module' => 'finance'],
        'finance.operations'             => ['label' => 'Expenses, settlements, reconciliations & journals', 'module' => 'finance'],
        'finance.reports'                => ['label' => 'Financial statements & trial balance', 'module' => 'finance'],

        // Reports — portfolio & operations
        'reports.view'                   => ['label' => 'Portfolio, PAR, disbursements & operational reports', 'module' => 'reports'],

        // Settings
        'settings.manage'                => ['label' => 'Manage settings & roles', 'module' => 'settings'],
        'users.view'                     => ['label' => 'View users', 'module' => 'settings'],
        'users.manage'                   => ['label' => 'Manage users (edit, lock, deactivate)', 'module' => 'settings'],
        'audit.view'                     => ['label' => 'View audit logs', 'module' => 'settings'],

        // Support
        'support.tickets'                => ['label' => 'Manage support tickets', 'module' => 'support'],

        // Asset marketplace
        'marketplace.view'               => ['label' => 'View marketplace assets & requests', 'module' => 'marketplace'],
        'marketplace.manage'             => ['label' => 'Create & edit marketplace assets', 'module' => 'marketplace'],
        'partners.manage'                => ['label' => 'Enroll partners, edit coverage, and activate portal access', 'module' => 'partners'],

        // Growth / marketing operations (not Settings Hub)
        'marketing.view'                 => ['label' => 'View Growth workspace', 'module' => 'marketing'],
        'marketing.campaigns.create'     => ['label' => 'Create campaigns', 'module' => 'marketing'],
        'marketing.campaigns.edit'       => ['label' => 'Edit campaigns', 'module' => 'marketing'],
        'marketing.campaigns.publish'    => ['label' => 'Publish / launch campaigns', 'module' => 'marketing'],
        'marketing.audiences.manage'     => ['label' => 'Manage saved audiences', 'module' => 'marketing'],
        'marketing.personas.manage'      => ['label' => 'Manage marketing personas', 'module' => 'marketing'],
        'marketing.demos.create'         => ['label' => 'Create marketing demo accounts', 'module' => 'marketing'],
        'marketing.demos.end'            => ['label' => 'End marketing demo sessions', 'module' => 'marketing'],
        'marketing.demos.unrestricted'   => ['label' => 'Unrestricted demo customization (trusted admins)', 'module' => 'marketing'],
        'marketing.offers.manage'        => ['label' => 'Create and publish Plus offers', 'module' => 'marketing'],
        'marketing.performance.view'     => ['label' => 'View marketing performance', 'module' => 'marketing'],

        // Communications operations (templates / chatbot copy — not gateways)
        'communications.view'            => ['label' => 'View Communications workspace', 'module' => 'communications'],
        'communications.templates.manage'=> ['label' => 'Edit notification templates', 'module' => 'communications'],
        'communications.chatbot.manage'  => ['label' => 'Edit chatbot FAQ content', 'module' => 'communications'],

        // Plus learning content operations
        'content.plus.manage'            => ['label' => 'Manage Plus Learning content', 'module' => 'content'],
    ],

    /** Fallback when roles table has no row for users.role */
    'defaults' => [
        'officer' => [
            'applications.view', 'applications.edit', 'applications.request_documents',
            'applications.acknowledge', 'applications.review', 'applications.pre_approve',
            'customers.view', 'kyc.review', 'membership.approve_payments',
            'loans.view',
            'reports.view',
        ],
        'manager' => [
            'applications.view', 'applications.acknowledge', 'applications.review',
            'applications.pre_approve', 'applications.approve', 'applications.reject',
            'applications.disburse', 'applications.request_documents', 'applications.edit',
            'customers.view', 'customers.edit', 'kyc.review', 'membership.approve_payments', 'grades.override',
            'loans.view', 'loans.disburse', 'users.view', 'settings.manage',
            'finance.accounts', 'finance.methods', 'finance.operations', 'finance.reports',
            'marketplace.view', 'marketplace.manage',
            'reports.view',
            'marketing.view', 'marketing.campaigns.create', 'marketing.campaigns.edit', 'marketing.campaigns.publish',
            'marketing.audiences.manage', 'marketing.personas.manage', 'marketing.demos.create', 'marketing.demos.end',
            'marketing.offers.manage', 'marketing.performance.view',
            'communications.view', 'communications.templates.manage', 'communications.chatbot.manage',
            'content.plus.manage',
        ],
        'marketer' => [
            'marketing.view',
            'marketing.campaigns.create', 'marketing.campaigns.edit', 'marketing.campaigns.publish',
            'marketing.audiences.manage', 'marketing.personas.manage',
            'marketing.demos.create', 'marketing.demos.end',
            'marketing.offers.manage', 'marketing.performance.view',
            'customers.view',
            'reports.view',
            'communications.view',
            'communications.templates.manage',
            'communications.chatbot.manage',
        ],
        'credit_analyst' => [
            'applications.view', 'applications.review', 'applications.request_documents',
            'applications.acknowledge', 'applications.edit',
            'customers.view', 'kyc.review',
            'loans.view',
            'reports.view',
        ],
        'credit_committee' => [
            'applications.view', 'applications.pre_approve', 'applications.approve',
            'applications.reject', 'applications.request_documents',
            'customers.view', 'kyc.review',
            'loans.view',
            'reports.view',
        ],
        'collector' => [
            'loans.view', 'customers.view',
            'reports.view',
        ],
        'agent' => [
            'support.tickets',
            'communications.view',
            'communications.templates.manage',
            'communications.chatbot.manage',
        ],
        'auditor' => [
            'audit.view', 'finance.reports', 'reports.view',
        ],
        'asset_manager' => [
            'marketplace.view', 'marketplace.manage',
        ],
        'partner_support' => [
            'partners.manage',
            'applications.view',
            'customers.view',
            'marketplace.view',
            'marketplace.manage',
        ],
        'borrower' => [],
        'customer' => [],
        'vendor' => [],
        'investor' => [],
    ],
];
