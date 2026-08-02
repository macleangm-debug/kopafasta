<?php

/**
 * Canonical admin settings navigation.
 * Used by Settings hub + in-page tabs. Keep Ops nav pointing at the hub only.
 *
 * @return array<string, list<array{0: string, 1: string, 2?: string}>>
 *         group => list of [label, routeName, optional tab key]
 */
return [
    'Organization' => [
        ['Company profile', 'admin.settings.company', 'company'],
        ['Authentication', 'admin.settings.auth-portal', 'auth-portal'],
        ['Branches', 'admin.branches.index', 'branches'],
        ['Departments', 'admin.departments.index', 'departments'],
        ['Users', 'admin.users.index', 'users'],
        ['Roles & permissions', 'admin.roles.index', 'roles'],
        ['Locations', 'admin.settings.locations.index', 'locations'],
        ['Countries', 'admin.settings.countries', 'countries'],
    ],
    'Lending' => [
        ['Loan products', 'admin.loan-products.index', 'loan-products'],
        ['Underwriting', 'admin.settings.underwriting', 'underwriting'],
        ['Loan rules', 'admin.settings.loan-rules', 'loan-rules'],
        ['Offer settings', 'admin.settings.offer', 'offer'],
        ['Credit policy', 'admin.settings.credit-policy', 'credit-policy'],
        ['Approval limits', 'admin.approval-limits.index', 'approval-limits'],
        ['Asset lending', 'admin.settings.asset-lending', 'asset-lending'],
        ['Marketplace assets', 'admin.marketplace-assets.index', 'marketplace-assets'],
    ],
    'Identity & compliance' => [
        ['KYC rules', 'admin.settings.kyc', 'kyc'],
        ['Identity verification', 'admin.settings.identity', 'identity'],
        ['AML thresholds', 'admin.settings.aml', 'aml'],
        ['CRB integration', 'admin.settings.crb', 'crb'],
    ],
    'Legal' => [
        ['Contracts & clauses', 'admin.settings.legal', 'legal'],
        ['Signatories', 'admin.settings.signatories.index', 'signatories'],
        ['Document templates', 'admin.document-templates.index', 'document-templates'],
    ],
    'Finance' => [
        ['Finance defaults', 'admin.settings.finance', 'finance'],
        ['Payment accounts', 'admin.settings.payment-accounts', 'payment-accounts'],
        ['Bank accounts', 'admin.bank-accounts.index', 'bank-accounts'],
        ['Mobile money (PSP)', 'admin.mobile-money-accounts.index', 'mobile-money'],
        ['Charges & fees', 'admin.charges-fees.index', 'fees'],
        ['Chart of accounts', 'admin.chart-of-accounts.index', 'chart-of-accounts'],
        ['Write-off rules', 'admin.write-off-rules.index', 'write-off-rules'],
    ],
    'Growth' => [
        ['Membership', 'admin.settings.membership', 'membership'],
        ['Referrals', 'admin.settings.referrals', 'referrals'],
        ['Engagement', 'admin.settings.engagement', 'engagement'],
        ['Affiliates', 'admin.settings.affiliates', 'affiliates'],
        ['Campaigns', 'admin.promotions.index', 'campaigns'],
    ],
    'Partners & recovery' => [
        ['Partners hub', 'admin.partners.index', 'partners-hub'],
        ['Partner tasks', 'admin.partners.tasks', 'partner-tasks'],
        ['Enrollment applications', 'admin.partner-applications.index', 'partner-applications'],
        ['Partner membership', 'admin.settings.partners', 'partners'],
        ['Recovery policy', 'admin.settings.recovery', 'recovery'],
    ],
    'Communications' => [
        ['SMS / Email', 'admin.settings.gateways', 'gateways'],
        ['Notification templates', 'admin.notification-templates.index', 'notification-templates'],
        ['Chatbot', 'admin.settings.chatbot', 'chatbot'],
    ],
];
