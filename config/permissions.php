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
        'finance'      => 'Finance',
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

        // Settings
        'settings.manage'                => ['label' => 'Manage settings & roles', 'module' => 'settings'],
        'audit.view'                     => ['label' => 'View audit logs', 'module' => 'settings'],
    ],

    /** Fallback when roles table has no row for users.role */
    'defaults' => [
        'officer' => [
            'applications.view', 'applications.acknowledge', 'applications.review',
            'applications.request_documents', 'customers.view', 'kyc.review',
            'membership.approve_payments',
        ],
        'manager' => [
            'applications.view', 'applications.acknowledge', 'applications.review',
            'applications.pre_approve', 'applications.approve', 'applications.reject',
            'applications.disburse', 'applications.request_documents', 'applications.edit',
            'customers.view', 'customers.edit', 'kyc.review', 'membership.approve_payments',
            'loans.view', 'loans.disburse',
        ],
        'credit_analyst' => [
            'applications.view', 'applications.review', 'applications.request_documents',
            'customers.view', 'kyc.review',
        ],
        'collector' => [
            'loans.view', 'customers.view',
        ],
    ],
];
