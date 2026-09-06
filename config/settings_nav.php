<?php

/**
 * Canonical admin settings navigation.
 * Used by Settings hub + in-page tabs. Keep Ops nav pointing at the hub only.
 *
 * @return array<string, list<array{0: string, 1: string, 2?: string}>>
 *                                                                      group => list of [label, routeName, optional tab key]
 */
return [
    'Website' => [
        ['SEO', 'admin.settings.seo', 'seo', 'meta robots sitemap canonical social schema google'],
        ['System', 'admin.settings.system', 'system', 'environment commit version deploy staging production'],
    ],
    'Organization' => [
        ['Company profile', 'admin.settings.company', 'company'],
        ['Working hours', 'admin.settings.working-hours', 'working-hours', 'sla holiday calendar office'],
        ['Account security', 'admin.settings.account-security', 'account-security', '2fa authenticator totp password'],
        ['Authentication', 'admin.settings.auth-portal', 'auth-portal', '2fa pin reset timer recovery turnstile session forgot'],
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
    ],
    'Integrations' => [
        ['Integrations hub', 'admin.settings.integrations', 'integrations'],
        ['PayIn', 'admin.settings.payin', 'payin'],
        ['SMS / Email', 'admin.settings.gateways', 'gateways'],
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
    'Customers' => [
        ['Grades & Trust', 'admin.settings.grades', 'grades'],
        ['Kopafasta Plus', 'admin.settings.plus', 'plus'],
    ],
    'Growth' => [
        ['Membership', 'admin.settings.membership', 'membership'],
        ['Referrals', 'admin.settings.referrals', 'referrals'],
        ['Rewards', 'admin.settings.engagement.loyalty-points', 'rewards'],
        ['Engagement', 'admin.settings.engagement', 'engagement'],
        ['Affiliates', 'admin.settings.affiliates', 'affiliates'],
    ],
    'Partners & recovery' => [
        ['Partners hub', 'admin.partners.index', 'partners-hub'],
        ['Partner applications', 'admin.partner-applications.index', 'partner-applications'],
        ['Partner tasks', 'admin.partners.tasks', 'partner-tasks'],
        ['Partner membership', 'admin.settings.partners', 'partners'],
        ['Partner performance', 'admin.settings.partner-performance', 'partner-performance'],
        ['Recovery policy', 'admin.settings.recovery', 'recovery'],
    ],
    'Communications' => [
        ['Notifications', 'admin.settings.notifications', 'notifications', 'digest operational assignment'],
        ['Transactional messaging', 'admin.settings.messaging', 'messaging'],
        ['Group notifications', 'admin.settings.group-notifications', 'group-notifications', 'group lending consent contract signature'],
    ],
];
