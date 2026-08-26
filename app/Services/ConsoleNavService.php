<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;

class ConsoleNavService
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly DepartmentAccessService $departments,
        private readonly RoleService $roles,
    ) {}

    /**
     * @return list<array{label: string, items: list<array>, isActive: bool, targetRoute: string, workspace: bool, separated: bool}>
     */
    public function visibleSections(?User $user, ?string $currentRoute = null): array
    {
        $currentRoute ??= request()->route()?->getName();
        $visible = [];

        foreach ($this->catalog() as $section) {
            if (! $this->canSeeSection($user, $section)) {
                continue;
            }

            $items = $this->pruneEmptyGroups($this->filterItems($user, $section['items']));
            if ($items === []) {
                continue;
            }

            $childRoutes = array_values(array_filter(
                array_column($items, 1),
                fn ($route) => $route !== '__group__',
            ));

            $isActive = in_array($currentRoute, $childRoutes, true);
            foreach ($section['active_prefixes'] ?? [] as $prefix) {
                if (is_string($currentRoute) && str_starts_with($currentRoute, $prefix)) {
                    $isActive = true;
                    break;
                }
            }

            $visible[] = [
                'label' => $section['label'],
                'items' => $items,
                'isActive' => $isActive,
                'workspace' => (bool) ($section['workspace'] ?? false),
                'separated' => (bool) ($section['separated'] ?? false),
                'targetRoute' => collect($items)->first(fn ($item) => ($item[1] ?? '') !== '__group__')[1] ?? $items[0][1],
            ];
        }

        return $visible;
    }

    /**
     * @return list<array{label: string, items: list<array>, perms: ?list<string>, hide_from: list<string>, workspace?: bool, separated?: bool, active_prefixes?: list<string>}>
     */
    public function catalog(): array
    {
        return [
            [
                'label' => 'Home',
                'workspace' => false,
                'items' => [
                    ['Home', 'admin.dashboard'],
                ],
                'perms' => null,
                'hide_from' => [],
            ],
            [
                'label' => 'Customers',
                'workspace' => true,
                'active_prefixes' => ['admin.customers.', 'admin.profile-sections.'],
                'items' => [
                    ['All', 'admin.customers.index'],
                    ['Grade Watch', 'admin.customers.grade-watch', 'customers.view'],
                    ['Profiles', 'admin.customers.profiles', 'customers.view'],
                    ['Section rules', 'admin.profile-sections.index', 'customers.edit', null, ['nav' => 'more']],
                ],
                'perms' => ['customers.view', 'applications.view'],
                'hide_from' => ['partner_support', 'asset_manager', 'marketer'],
            ],
            [
                'label' => 'Lending',
                'workspace' => true,
                'active_prefixes' => ['admin.loan-applications.', 'admin.loans.', 'admin.recovery.', 'admin.teams.', 'admin.credit-team.', 'admin.repayments.'],
                'items' => [
                    ['— Credit screening —', '__group__'],
                    ['Screening home',        'admin.teams.screening'],
                    ['Credit screening',      'admin.loan-applications.pipeline.under-review'],
                    ['— Credit committee —', '__group__'],
                    ['Committee home',        'admin.teams.committee'],
                    ['Credit committee',      'admin.loan-applications.pre-approvals'],
                    ['System sorted',         'admin.loan-applications.pipeline.system-sorted'],
                    ['— Credit management —', '__group__'],
                    ['Management home',       'admin.teams.management'],
                    ['Management approval',   'admin.loan-applications.pipeline.management-approval'],
                    ['Management queue',      'admin.loan-applications.pipeline.approved'],
                    ['Release queue',         'admin.loan-applications.pipeline.disbursement'],
                    ['Payout queue',          'admin.loans.disbursement'],
                    ['— Applications —', '__group__'],
                    ['All applications',      'admin.loan-applications.index'],
                    ['Incomplete drafts',     'admin.loan-applications.incomplete'],
                    ['— Loans —', '__group__'],
                    ['All loans',           'admin.loans.index'],
                    ['Active loans',        'admin.loans.active'],
                    ['Loan repayments',     'admin.repayments.index'],
                    ['Collection cases',    'admin.arrear-cases.index'],
                    ['Write-off requests',  'admin.write-off-requests.index'],
                    ['Loans in arrears',  'admin.loans.arrears'],
                    ['Restructure requests','admin.restructure-requests.index'],
                    ['Top-up requests',     'admin.top-up-requests.index'],
                    ['Restructuring',       'admin.loans.restructuring'],
                    ['Closed loans',        'admin.loans.closed'],
                    ['— Recovery —', '__group__'],
                    ['Recovery assignments', 'admin.recovery.assignments.index'],
                    ['Credit teams',        'admin.credit-team.index', 'applications.view'],
                ],
                'perms' => ['applications.view', 'loans.view'],
                'hide_from' => ['partner_support', 'asset_manager'],
            ],
            [
                'label' => 'Money',
                'workspace' => true,
                'active_prefixes' => ['admin.payments.', 'admin.capital-funding.', 'admin.lenders.', 'admin.expenses.', 'admin.journal-entries.', 'admin.reconciliations.'],
                'items' => [
                    ['— Payments —', '__group__'],
                    ['Payments',              'admin.payments.index'],
                    ['Payments ledger',       'admin.payments.ledger',              'finance.operations'],
                    ['Membership & renewals', 'admin.payments.ledger',              null, ['tab' => 'membership']],
                    ['— Capital —', '__group__'],
                    ['Capital funding',      'admin.capital-funding.index'],
                    ['Funded loans',         'admin.capital-funding.funded-loans'],
                    ['Withdrawal requests',  'admin.capital-funding.withdrawals'],
                    ['Capital Partners',     'admin.lenders.index'],
                    ['Funding Pools',      'admin.funding-pools.index'],
                    ['Loan allocations',   'admin.lender-investments.index'],
                    ['— Ledgers —', '__group__'],
                    ['Payout ledger',             'admin.payments.ledger',              'finance.operations', ['direction' => 'out']],
                    ['Operational expenses',      'admin.expenses.index',               'finance.operations'],
                    ['Journal Entries',           'admin.journal-entries.index',        'finance.operations'],
                    ['Reconciliations',           'admin.reconciliations.index',        'finance.operations'],
                    ['Payment gateway settlements', 'admin.settlements.index',       'finance.operations'],
                    ['Borrower refunds',      'admin.borrower-refunds.index',        'finance.operations'],
                    ['Chart of accounts',     'admin.chart-of-accounts.index',       'finance.accounts'],
                ],
                'perms' => ['finance.accounts', 'finance.methods', 'finance.operations', 'membership.approve_payments'],
                'hide_from' => ['partner_support', 'asset_manager', 'officer', 'credit_analyst', 'credit_committee'],
            ],
            [
                'label' => 'Partners',
                'workspace' => true,
                'active_prefixes' => ['admin.partners.', 'admin.partner-applications.', 'admin.vendors.', 'admin.marketplace-assets.', 'admin.asset-requests.'],
                'items' => [
                    ['Partners hub',            'admin.partners.index', 'partners.manage'],
                    ['Partner applications',    'admin.partner-applications.index', 'partners.manage'],
                    ['Partner efficiency',      'admin.partners.efficiency', 'partners.manage'],
                    ['Partner tasks',           'admin.partners.tasks', 'partners.manage'],
                    ['Partner payout requests', 'admin.partner-payout-requests.index', 'finance.operations'],
                    ['Suppliers',               'admin.partners.suppliers', 'marketplace.view'],
                    ['Asset Marketplace',       'admin.marketplace-assets.index', 'marketplace.view'],
                    ['Asset Requests',          'admin.asset-requests.index', 'marketplace.view'],
                    ['Recovery assignments',    'admin.recovery.assignments.index', 'partners.manage'],
                ],
                'perms' => ['partners.manage', 'marketplace.view'],
                'hide_from' => [],
            ],
            [
                'label' => 'Growth',
                'workspace' => true,
                'active_prefixes' => ['admin.growth.', 'admin.promotions.'],
                'items' => [
                    ['Overview', 'admin.growth.index', 'marketing.view'],
                    ['Campaigns', 'admin.promotions.index', 'marketing.view'],
                    ['Audiences', 'admin.growth.audiences.index', 'marketing.audiences.manage'],
                    ['Offers', 'admin.growth.offers.index', 'marketing.offers.manage'],
                    ['Affiliates', 'admin.growth.affiliates', 'marketing.view'],
                    ['Personas', 'admin.growth.personas.index', 'marketing.personas.manage', null, ['nav' => 'more']],
                    ['Demo Accounts', 'admin.growth.demos.index', 'marketing.view', null, ['nav' => 'more']],
                    ['Performance', 'admin.growth.performance', 'marketing.performance.view', null, ['nav' => 'more']],
                ],
                'perms' => ['marketing.view'],
                'hide_from' => [],
            ],
            [
                'label' => 'Communications',
                'workspace' => true,
                'active_prefixes' => ['admin.communications.', 'admin.notification-templates.', 'admin.support-tickets.', 'admin.complaints.'],
                'items' => [
                    ['Overview', 'admin.communications.index', 'communications.view'],
                    ['Tickets', 'admin.support-tickets.index', 'support.tickets'],
                    ['Templates', 'admin.notification-templates.index', 'communications.templates.manage'],
                    ['Chatbot', 'admin.communications.chatbot', 'communications.chatbot.manage'],
                    ['Complaints', 'admin.complaints.index', 'support.tickets', null, ['nav' => 'more']],
                ],
                'perms' => ['communications.view', 'support.tickets', 'communications.templates.manage', 'communications.chatbot.manage'],
                'hide_from' => [],
            ],
            [
                'label' => 'Reports',
                'workspace' => true,
                'active_prefixes' => ['admin.reports.', 'admin.compliance.'],
                'items' => [
                    ['— Lending —', '__group__'],
                    ['Portfolio',         'admin.reports.portfolio',          'reports.view'],
                    ['Applications',      'admin.reports.applications',       'reports.view'],
                    ['Disbursements',     'admin.reports.disbursements',      'reports.view'],
                    ['Arrears',           'admin.reports.arrears',            'reports.view'],
                    ['Collections',       'admin.reports.collections-performance', 'reports.view'],
                    ['Repayments report', 'admin.reports.repayments',         'reports.view'],
                    ['PAR',               'admin.reports.par',                'reports.view'],
                    ['Customers report',  'admin.reports.customers',          'reports.view'],
                    ['— Finance —', '__group__'],
                    ['Finance summary',       'admin.reports.finance-summary',     'finance.reports'],
                    ['Trial Balance',         'admin.reports.trial-balance',       'finance.reports'],
                    ['Income Statement',      'admin.reports.income-statement',    'finance.reports'],
                    ['Balance Sheet',         'admin.reports.balance-sheet',       'finance.reports'],
                    ['Cash Flow',             'admin.reports.cash-flow',           'finance.reports'],
                    ['NPL',                   'admin.reports.npl',                 'finance.reports'],
                    ['— Partners —', '__group__'],
                    ['Affiliate Performance','admin.reports.partner-performance', 'reports.view'],
                    ['Marketing attribution','admin.reports.affiliate-marketing-attribution', 'reports.view'],
                    ['Capital attribution','admin.reports.affiliate-capital-attribution', 'reports.view'],
                    ['Affiliate fraud',   'admin.reports.affiliate-fraud',    'reports.view'],
                    ['— Regulatory —', '__group__'],
                    ['BOT Reports',         'admin.compliance.bot-reports', 'reports.view'],
                    ['AML Reports',         'admin.compliance.aml-reports', 'reports.view'],
                    ['KYC Reports',         'admin.compliance.kyc-reports', 'reports.view'],
                    ['Regulatory Exports',  'admin.compliance.exports', 'reports.view'],
                ],
                'perms' => ['reports.view', 'finance.reports'],
                'hide_from' => ['partner_support', 'asset_manager'],
            ],
            [
                'label' => 'More',
                'workspace' => true,
                'active_prefixes' => ['admin.content.', 'admin.users.', 'admin.departments.', 'admin.roles.', 'admin.audit-logs.', 'admin.suspicious-activities.', 'admin.blacklist-entries.', 'admin.aml-rules.', 'admin.risk-scoring-rules.', 'admin.pep-flags.'],
                'items' => [
                    ['Plus Learning', 'admin.content.plus-learning', 'content.plus.manage'],
                    ['Credit teams', 'admin.credit-team.index', 'applications.view'],
                    ['Departments', 'admin.departments.index', 'users.view'],
                    ['Users', 'admin.users.index', 'users.view'],
                    ['Roles & Permissions', 'admin.roles.index', 'users.manage'],
                    ['— Compliance —', '__group__'],
                    ['Suspicious Activity', 'admin.suspicious-activities.index', 'audit.view'],
                    ['Blacklist',           'admin.blacklist-entries.index', 'audit.view'],
                    ['PEP Flags',           'admin.pep-flags.index', 'audit.view'],
                    ['AML Rules',           'admin.aml-rules.index', 'audit.view'],
                    ['Risk Scoring',        'admin.risk-scoring-rules.index', 'audit.view'],
                    ['Audit Logs',          'admin.audit-logs.index', 'audit.view'],
                ],
                'perms' => ['audit.view', 'users.view', 'users.manage', 'content.plus.manage', 'applications.view'],
                'hide_from' => ['partner_support', 'asset_manager', 'officer', 'credit_analyst', 'credit_committee'],
            ],
            [
                'label' => 'Settings',
                'workspace' => false,
                'separated' => true,
                'active_prefixes' => ['admin.settings.'],
                'items' => [
                    ['Settings hub', 'admin.settings.index', 'settings.manage'],
                ],
                'perms' => ['settings.manage'],
                'hide_from' => ['partner_support', 'asset_manager', 'officer', 'credit_analyst', 'credit_committee'],
            ],
        ];
    }

    /**
     * @param  array{perms: ?list<string>, hide_from: list<string>}  $section
     */
    private function canSeeSection(?User $user, array $section): bool
    {
        if (! $user) {
            return false;
        }

        $role = (string) $user->role;
        if (in_array($role, $section['hide_from'] ?? [], true) && ! $this->roles->hasPermissionBypass($user)) {
            return false;
        }

        $perms = $section['perms'] ?? null;
        if ($perms === null || $perms === []) {
            return true;
        }

        return $this->permissions->hasAny($user, $perms);
    }

    /**
     * @param  list<array>  $items
     * @return list<array>
     */
    private function filterItems(?User $user, array $items): array
    {
        if (! $user) {
            return [];
        }

        $role = (string) $user->role;

        return array_values(array_filter($items, function (array $item) use ($user, $role) {
            if (($item[1] ?? '') === '__group__') {
                return true;
            }

            $permission = $item[2] ?? null;
            $route = $item[1] ?? '';

            if ($permission !== null && ! $this->permissions->has($user, $permission)) {
                return false;
            }

            if (! $this->departments->canAccessRoute($user, $route)) {
                return false;
            }

            if ($role === 'manager' && $route === 'admin.loan-applications.pipeline.system-sorted') {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array>  $items
     * @return list<array>
     */
    private function pruneEmptyGroups(array $items): array
    {
        $result = [];
        $pendingGroup = null;

        foreach ($items as $item) {
            if (($item[1] ?? '') === '__group__') {
                $pendingGroup = $item;
                continue;
            }

            if ($pendingGroup !== null) {
                $result[] = $pendingGroup;
                $pendingGroup = null;
            }

            $result[] = $item;
        }

        return $result;
    }

    public function canManagePartners(?User $user): bool
    {
        return (bool) $user?->can('create', Vendor::class);
    }
}
