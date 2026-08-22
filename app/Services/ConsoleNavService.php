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
     * @return list<array{label: string, items: list<array>, isActive: bool, targetRoute: string}>
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

            $visible[] = [
                'label' => $section['label'],
                'items' => $items,
                'isActive' => in_array($currentRoute, $childRoutes, true),
                'targetRoute' => collect($items)->first(fn ($item) => ($item[1] ?? '') !== '__group__')[1] ?? $items[0][1],
            ];
        }

        return $visible;
    }

    /**
     * @return list<array{label: string, items: list<array>, perms: ?list<string>, hide_from: list<string>}>
     */
    public function catalog(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'items' => [
                    ['Dashboard', 'admin.dashboard'],
                ],
                'perms' => null,
                'hide_from' => [],
            ],
            [
                'label' => 'Lending',
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
                    ['Management queue',      'admin.loan-applications.pipeline.approved'],
                    ['Release queue',         'admin.loan-applications.pipeline.disbursement'],
                    ['Payout queue',          'admin.loans.disbursement'],
                    ['— Applications —', '__group__'],
                    ['All applications',      'admin.loan-applications.index'],
                    ['Incomplete drafts',     'admin.loan-applications.incomplete'],
                    ['— Loans —', '__group__'],
                    ['All loans',           'admin.loans.index'],
                    ['Active loans',        'admin.loans.active'],
                    ['Collection cases',    'admin.arrear-cases.index'],
                    ['Write-off requests',  'admin.write-off-requests.index'],
                    ['Loans in arrears',  'admin.loans.arrears'],
                    ['Restructure requests','admin.restructure-requests.index'],
                    ['Top-up requests',     'admin.top-up-requests.index'],
                    ['Restructuring',       'admin.loans.restructuring'],
                    ['Closed loans',        'admin.loans.closed'],
                    ['Credit teams',        'admin.credit-team.index', 'applications.view'],
                ],
                'perms' => ['applications.view', 'loans.view'],
                'hide_from' => ['partner_support', 'asset_manager'],
            ],
            [
                'label' => 'Customers',
                'items' => [
                    ['All customers', 'admin.customers.index'],
                ],
                'perms' => ['customers.view', 'applications.view'],
                'hide_from' => ['partner_support', 'asset_manager'],
            ],
            [
                'label' => 'Payments',
                'items' => [
                    ['Payments',              'admin.payments.index'],
                    ['Payments ledger',       'admin.payments.ledger'],
                    ['Loan repayments',       'admin.repayments.index'],
                    ['Membership & renewals', 'admin.payments.ledger', null, ['tab' => 'membership']],
                ],
                'perms' => ['finance.operations', 'membership.approve_payments', 'loans.view'],
                'hide_from' => ['partner_support', 'asset_manager'],
            ],
            [
                'label' => 'Field & recovery',
                'items' => [
                    ['Recovery assignments', 'admin.recovery.assignments.index'],
                    ['Partner tasks',        'admin.partners.tasks'],
                ],
                'perms' => ['applications.view', 'loans.view', 'partners.manage'],
                'hide_from' => [],
            ],
            [
                'label' => 'Assets',
                'items' => [
                    ['Asset Marketplace',   'admin.marketplace-assets.index', 'marketplace.view'],
                    ['Asset Requests',      'admin.asset-requests.index', 'marketplace.view'],
                    ['Suppliers',           'admin.partners.suppliers'],
                ],
                'perms' => ['marketplace.view'],
                'hide_from' => [],
            ],
            [
                'label' => 'Partners',
                'items' => [
                    ['Partners hub',            'admin.partners.index'],
                    ['Partner applications',    'admin.partner-applications.index'],
                    ['Partner payout requests', 'admin.partner-payout-requests.index', 'finance.operations'],
                ],
                'perms' => ['partners.manage'],
                'hide_from' => [],
            ],
            [
                'label' => 'Money',
                'items' => [
                    ['— Capital —', '__group__'],
                    ['Capital funding',      'admin.capital-funding.index'],
                    ['Funded loans',         'admin.capital-funding.funded-loans'],
                    ['Withdrawal requests',  'admin.capital-funding.withdrawals'],
                    ['Capital Partners',     'admin.lenders.index'],
                    ['Funding Pools',      'admin.funding-pools.index'],
                    ['Loan allocations',   'admin.lender-investments.index'],
                    ['— Ledgers —', '__group__'],
                    ['Payments ledger',           'admin.payments.ledger',              'finance.operations'],
                    ['Payout ledger',             'admin.payments.ledger',              'finance.operations', ['direction' => 'out']],
                    ['Operational expenses',      'admin.expenses.index',               'finance.operations'],
                    ['Journal Entries',           'admin.journal-entries.index',        'finance.operations'],
                    ['Reconciliations',           'admin.reconciliations.index',        'finance.operations'],
                    ['— Other money ops —', '__group__'],
                    ['Payment gateway settlements', 'admin.settlements.index',       'finance.operations'],
                    ['Borrower refunds',      'admin.borrower-refunds.index',        'finance.operations'],
                    ['Chart of accounts',     'admin.chart-of-accounts.index',       'finance.accounts'],
                ],
                'perms' => ['finance.accounts', 'finance.methods', 'finance.operations'],
                'hide_from' => ['partner_support', 'asset_manager'],
            ],
            [
                'label' => 'Reports',
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
                'label' => 'Ops',
                'items' => [
                    ['— Compliance tools —', '__group__'],
                    ['Suspicious Activity', 'admin.suspicious-activities.index'],
                    ['Blacklist',           'admin.blacklist-entries.index'],
                    ['PEP Flags',           'admin.pep-flags.index'],
                    ['AML Rules',           'admin.aml-rules.index'],
                    ['Risk Scoring',        'admin.risk-scoring-rules.index'],
                    ['Audit Logs',          'admin.audit-logs.index'],
                    ['— Support —', '__group__'],
                    ['Tickets',    'admin.support-tickets.index'],
                    ['Complaints', 'admin.complaints.index'],
                    ['— Marketing —', '__group__'],
                    ['Campaigns', 'admin.promotions.index'],
                    ['— Administration —', '__group__'],
                    ['Credit teams', 'admin.credit-team.index', 'applications.view'],
                    ['Departments', 'admin.departments.index', 'users.view'],
                    ['Users', 'admin.users.index', 'users.view'],
                    ['Roles & Permissions', 'admin.roles.index', 'users.manage'],
                    ['— Settings —', '__group__'],
                    ['Settings hub', 'admin.settings.index', 'settings.manage'],
                ],
                'perms' => ['audit.view', 'users.manage', 'settings.manage'],
                'hide_from' => ['partner_support', 'asset_manager', 'officer', 'credit_analyst', 'credit_committee', 'manager'],
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
