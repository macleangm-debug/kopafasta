<?php

/**
 * Department codes map to admin nav route name prefixes they may access.
 * Admin / super_admin / users without a department see everything their role allows.
 * Multi-team users get the union of modules from all assigned departments.
 */
return [
    'modules' => [
        'CS'  => ['customers', 'support-tickets', 'complaints', 'membership-payments', 'face-verifications', 'communications', 'notification-templates', 'profile-sections'],
        'CRD' => ['loan-applications', 'customer-kycs', 'face-verifications', 'guarantors', 'loans', 'reports.customers', 'customers', 'profile-sections'],
        'UND' => ['loan-applications', 'customer-kycs', 'face-verifications', 'guarantors'],
        'CRC' => ['loan-applications', 'loans', 'reports'],
        'COL' => ['loans.arrears', 'arrear-cases', 'write-off-requests', 'restructure-requests', 'top-up-requests', 'reports.arrears', 'reports.par', 'recovery'],
        'CMP' => ['compliance', 'audit-logs', 'customers', 'loan-applications'],
        'FIN' => ['finance', 'payments', 'expenses', 'settlements', 'journal-entries', 'chart-of-accounts', 'reports.financial', 'reports.trial-balance', 'reports.income-statement', 'reports.balance-sheet', 'reports.cash-flow', 'reports.npl', 'write-off-rules', 'write-off-requests'],
        'OPS' => ['loans', 'repayments', 'loan-products', 'disbursement', 'partners', 'vendors', 'asset-requests', 'marketplace-assets', 'content'],
        'PRT' => ['teams.partners', 'partner-applications', 'partners', 'vendors', 'recovery', 'asset-requests', 'marketplace-assets', 'loan-applications'],
        'CRM' => ['loan-applications', 'loans', 'customers', 'repayments', 'reports'],
        'IT'  => ['settings', 'users', 'roles', 'branches', 'departments', 'audit-logs', 'support-tickets', 'content', 'communications'],
        'MGT' => ['loans', 'loan-applications', 'reports', 'compliance', 'audit-logs', 'write-off-requests'],
        'MKT' => ['promotions', 'growth', 'vendors.affiliates', 'reports.customers', 'customers', 'reports.affiliate', 'communications'],
        'REC' => ['arrear-cases', 'loans.arrears', 'loans.closed', 'write-off-requests', 'reports.arrears', 'reports.npl', 'recovery', 'settings.recovery'],
        'SYS' => ['settings', 'users', 'roles', 'branches', 'departments', 'audit-logs'],
    ],
];
