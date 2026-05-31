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
    ],

    /** Fallback when roles table has no row for users.role */
    'defaults' => [
        'officer' => [
            'applications.view', 'applications.edit', 'applications.request_documents',
            'customers.view', 'kyc.review', 'membership.approve_payments',
            'reports.view',
        ],
        'manager' => [
            'applications.view', 'applications.acknowledge', 'applications.review',
            'applications.pre_approve', 'applications.approve', 'applications.reject',
            'applications.disburse', 'applications.request_documents', 'applications.edit',
            'customers.view', 'customers.edit', 'kyc.review', 'membership.approve_payments',
            'loans.view', 'loans.disburse', 'users.view',
            'finance.accounts', 'finance.methods', 'finance.operations', 'finance.reports',
            'reports.view',
        ],
        'credit_analyst' => [
            'applications.view', 'applications.review', 'applications.request_documents',
            'customers.view', 'kyc.review',
            'reports.view',
        ],
        'collector' => [
            'loans.view', 'customers.view',
            'reports.view',
        ],
        'agent' => [
            'support.tickets',
        ],
        'auditor' => [
            'audit.view', 'finance.reports', 'reports.view',
        ],
        'borrower' => [],
        'customer' => [],
        'vendor' => [],
        'investor' => [],
    ],
];
