<?php

/**
 * Department codes map to admin nav route name prefixes they may access.
 * Admin / super_admin / users without a department see everything their role allows.
 */
return [
    'modules' => [
        'CS'  => ['customers', 'support-tickets', 'complaints', 'membership-payments', 'face-verifications'],
        'UND' => ['loan-applications', 'customer-kycs', 'face-verifications', 'guarantors'],
        'COL' => ['loans.arrears', 'arrear-cases', 'write-off-requests', 'restructure-requests', 'top-up-requests', 'reports.arrears', 'reports.par', 'recovery'],
        'FIN' => ['finance', 'payments', 'expenses', 'settlements', 'journal-entries', 'chart-of-accounts', 'reports.financial', 'reports.trial-balance', 'reports.income-statement', 'reports.balance-sheet', 'reports.cash-flow', 'reports.npl', 'write-off-rules', 'write-off-requests'],
        'OPS' => ['loans', 'repayments', 'loan-products', 'disbursement', 'partners', 'vendors', 'asset-requests', 'marketplace-assets'],
        'MGT' => ['loans', 'loan-applications', 'reports', 'compliance', 'audit-logs', 'write-off-requests'],
        'MKT' => ['promotions', 'vendors.affiliates', 'reports.customers'],
        'REC' => ['arrear-cases', 'loans.arrears', 'loans.closed', 'write-off-requests', 'reports.arrears', 'reports.npl', 'recovery', 'settings.recovery'],
        'SYS' => ['settings', 'users', 'roles', 'branches', 'departments', 'audit-logs'],
    ],
];
