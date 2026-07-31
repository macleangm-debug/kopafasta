@props([
    'title' => null,
    'heading' => null,
    'subheading' => null,
    'backUrl' => null,
    'backLabel' => 'Back',
])

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-currency" content="{{ currency_code() }}">
    <title>{{ $title ?? 'Console' }} · {{ brand_name() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        [x-cloak]{display:none!important}
        .admin-menu details > summary{list-style:none;cursor:pointer}
        .admin-menu details > summary::-webkit-details-marker{display:none}
        dialog{margin:auto;border:0;padding:0;max-width:calc(100vw - 2rem)}
        dialog::backdrop{background:rgba(0,0,0,.4)}
    </style>
</head>
<body class="h-full bg-[#faf8f5] text-gray-900 antialiased">

@php
    $sections = [
        ['Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10', [
            ['Dashboard', 'admin.dashboard'],
        ], null],
        ['Applications', 'M9 12h6m-6 4h6M5 7h14M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z', [
            ['Credit review',         'admin.loan-applications.pipeline.under-review'],
            ['Credit committee',      'admin.loan-applications.pre-approvals'],
            ['Approved Loans',        'admin.loan-applications.pipeline.approved'],
            ['Disbursement',          'admin.loan-applications.pipeline.disbursement'],
            ['All Applications',      'admin.loan-applications.index'],
            ['New Applications',      'admin.loan-applications.new'],
            ['Incomplete Applications', 'admin.loan-applications.incomplete'],
            ['Rejected Applications', 'admin.loan-applications.rejected'],
            ['Credit team',           'admin.credit-team.index'],
        ], ['applications.view']],
        ['Customers', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5a4 4 0 11-8 0 4 4 0 018 0z', [
            ['All Customers', 'admin.customers.index'],
            ['Membership Payments', 'admin.membership-payments.index'],
            ['Identity & KYC', 'admin.customer-kycs.index'],
            ['Face verification', 'admin.face-verifications.index'],
        ], ['customers.view', 'kyc.review', 'membership.approve_payments']],
        ['Loans', 'M3 10h18M3 14h18M5 6h14M5 18h14', [
            ['All loans',           'admin.loans.index'],
            ['Disbursement queue',  'admin.loans.disbursement'],
            ['Active loans',        'admin.loans.active'],
            ['Repayments',          'admin.repayments.index'],
            ['Collection cases',    'admin.arrear-cases.index'],
            ['Write-off requests',  'admin.write-off-requests.index'],
            ['Loans in arrears',  'admin.loans.arrears'],
            ['Restructure requests','admin.restructure-requests.index'],
            ['Top-up requests',     'admin.top-up-requests.index'],
            ['Restructuring',       'admin.loans.restructuring'],
            ['Closed loans',        'admin.loans.closed'],
        ], ['loans.view']],
        ['Recovery', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z', [
            ['Recovery partners',   'admin.recovery.partners.index'],
            ['Valuation partners',  'admin.origination.valuation-partners'],
            ['Recovery assignments','admin.recovery.assignments.index'],
            ['Recovery policy',     'admin.settings.recovery'],
        ], null],
        ['Partners', 'M3 7h18M3 12h18M3 17h18', [
            ['Partners hub',         'admin.partners.index'],
            ['All Partners',         'admin.partners.all'],
            ['Partner Applications', 'admin.partners.applications'],
            ['Affiliate applications', 'admin.partner-applications.index'],
            ['Suppliers',           'admin.partners.suppliers'],
            ['Asset Marketplace',   'admin.marketplace-assets.index', 'marketplace.view'],
            ['Asset Requests',      'admin.asset-requests.index', 'marketplace.view'],
            ['GPS Installers',      'admin.partners.gps-installers'],
            ['Insurance Providers', 'admin.partners.insurance-providers'],
            ['Valuers',             'admin.partners.valuers'],
            ['Partner Tasks',        'admin.partners.tasks'],
        ], null],
        ['Capital', 'M19 7H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2zM3 11h18', [
            ['Capital funding',      'admin.capital-funding.index'],
            ['Funded loans',         'admin.capital-funding.funded-loans'],
            ['Withdrawal requests',  'admin.capital-funding.withdrawals'],
            ['Capital Partners',     'admin.lenders.index'],
            ['Funding Pools',      'admin.funding-pools.index'],
            ['Lender Investments', 'admin.lender-investments.index'],
        ], null],
        ['Finance', 'M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8v8m0 0v2m-9-5a9 9 0 1018 0 9 9 0 00-18 0z', [
            ['— Setup —', '__group__'],
            ['Chart of Accounts',     'admin.chart-of-accounts.index',     'finance.accounts'],
            ['Bank Accounts',         'admin.bank-accounts.index',         'finance.accounts'],
            ['Mobile Money Accounts', 'admin.mobile-money-accounts.index', 'finance.accounts'],
            ['Disbursement Methods',  'admin.disbursement-methods.index',  'finance.methods'],
            ['Repayment Methods',     'admin.repayment-methods.index',     'finance.methods'],
            ['Charges & Fees',        'admin.charges-fees.index',          'finance.methods'],
            ['Write-off Rules',       'admin.write-off-rules.index',       'finance.methods'],
            ['— Transactions —', '__group__'],
            ['Payments',              'admin.payments.index',              'finance.operations'],
            ['Expenses',              'admin.expenses.index',              'finance.operations'],
            ['Settlements',           'admin.settlements.index',           'finance.operations'],
            ['Partner payments',      'admin.partner-payments.index',        'finance.operations'],
            ['Partner settlements',   'admin.partner-settlements.index',     'finance.operations'],
            ['Borrower refunds',      'admin.borrower-refunds.index',        'finance.operations'],
            ['Reconciliations',       'admin.reconciliations.index',       'finance.operations'],
            ['Journal Entries',       'admin.journal-entries.index',       'finance.operations'],
            ['— Financial reports —', '__group__'],
            ['Finance summary',       'admin.reports.finance-summary',     'finance.reports'],
            ['Trial Balance',         'admin.reports.trial-balance',       'finance.reports'],
            ['Income Statement',      'admin.reports.income-statement',    'finance.reports'],
            ['Balance Sheet',         'admin.reports.balance-sheet',       'finance.reports'],
            ['Cash Flow',             'admin.reports.cash-flow',           'finance.reports'],
            ['NPL',                   'admin.reports.npl',                 'finance.reports'],
        ], ['finance.accounts', 'finance.methods', 'finance.operations', 'finance.reports']],
        ['Reports', 'M3 3v18h18M7 17V9m4 8V5m4 12v-7m4 7V11', [
            ['Portfolio',         'admin.reports.portfolio',          'reports.view'],
            ['Applications',      'admin.reports.applications',       'reports.view'],
            ['Disbursements',     'admin.reports.disbursements',      'reports.view'],
            ['Arrears',           'admin.reports.arrears',            'reports.view'],
            ['Collections',       'admin.reports.collections-performance', 'reports.view'],
            ['Repayments',        'admin.reports.repayments',         'reports.view'],
            ['PAR',               'admin.reports.par',                'reports.view'],
            ['Customers',         'admin.reports.customers',          'reports.view'],
            ['Affiliate Performance','admin.reports.partner-performance', 'reports.view'],
            ['Marketing attribution','admin.reports.affiliate-marketing-attribution', 'reports.view'],
            ['Capital attribution','admin.reports.affiliate-capital-attribution', 'reports.view'],
            ['Affiliate fraud',   'admin.reports.affiliate-fraud',    'reports.view'],
        ], ['reports.view']],
        ['Compliance', 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z', [
            ['BOT Reports',         'admin.compliance.bot-reports'],
            ['AML Reports',         'admin.compliance.aml-reports'],
            ['KYC Reports',         'admin.compliance.kyc-reports'],
            ['Suspicious Activity', 'admin.suspicious-activities.index'],
            ['Blacklist',           'admin.blacklist-entries.index'],
            ['PEP Flags',           'admin.pep-flags.index'],
            ['AML Rules',           'admin.aml-rules.index'],
            ['Risk Scoring',        'admin.risk-scoring-rules.index'],
            ['Audit Logs',          'admin.audit-logs.index'],
            ['Regulatory Exports',  'admin.compliance.exports'],
        ], ['audit.view']],
        ['Support', 'M21 11.5a8.38 8.38 0 01-9 8.5 8.5 8.5 0 01-7.6-4.6L3 21l1.9-5.8A8.38 8.38 0 013 11.5 8.5 8.5 0 0111.5 3 8.38 8.38 0 0121 11.5z', [
            ['Tickets',    'admin.support-tickets.index'],
            ['Complaints', 'admin.complaints.index'],
        ], null],
        ['Marketing', 'M11 5.882V19.24a1.76 1.76 0 01-3.27.87l-4.5-7.79A1.76 1.76 0 015.882 9H4a2 2 0 110-4h1.882a1.76 1.76 0 011.27.87l1.27 2.2', [
            ['Campaigns', 'admin.promotions.index'],
            ['Affiliate Partners', 'admin.partners.affiliates'],
            ['Marketing attribution', 'admin.reports.affiliate-marketing-attribution'],
            ['Capital attribution', 'admin.reports.affiliate-capital-attribution'],
            ['Affiliate fraud', 'admin.reports.affiliate-fraud'],
            ['Affiliate Settings', 'admin.settings.affiliates'],
        ], null],
        ['Administration', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', [
            ['Departments', 'admin.departments.index', 'users.view'],
            ['Users', 'admin.users.index', 'users.view'],
            ['Roles & Permissions', 'admin.roles.index', 'users.manage'],
        ], ['users.view', 'users.manage']],
        ['Settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z', [
            ['Settings hub',            'admin.settings.index'],
            ['— Lending —',             '__group__'],
            ['Loan products',           'admin.loan-products.index'],
            ['Underwriting',            'admin.settings.underwriting'],
            ['Loan rules',              'admin.settings.loan-rules'],
            ['Offer settings',          'admin.settings.offer'],
            ['Asset lending',           'admin.settings.asset-lending'],
            ['— Compliance —',          '__group__'],
            ['KYC rules',               'admin.settings.kyc'],
            ['Identity verification',   'admin.settings.identity'],
            ['Credit policy',           'admin.settings.credit-policy'],
            ['AML thresholds',          'admin.settings.aml'],
            ['Countries',               'admin.settings.countries'],
            ['— Finance —',             '__group__'],
            ['Finance defaults',        'admin.settings.finance'],
            ['Payment accounts',        'admin.settings.payment-accounts'],
            ['Charges & fees',          'admin.charges-fees.index'],
            ['— Partners & marketing —','__group__'],
            ['Affiliate settings',      'admin.settings.affiliates'],
            ['Membership',              'admin.settings.membership'],
            ['Referrals',               'admin.settings.referrals'],
            ['Campaigns',               'admin.promotions.index'],
            ['— Integrations —',        '__group__'],
            ['SMS / Email',             'admin.settings.gateways'],
            ['CRB integration',         'admin.settings.crb'],
            ['Notification templates',  'admin.notification-templates.index'],
            ['— Organization —',        '__group__'],
            ['Company profile',         'admin.settings.company'],
            ['Legal & contracts',       'admin.settings.legal'],
            ['Signatories',             'admin.settings.signatories.index'],
            ['Recovery policy',         'admin.settings.recovery'],
            ['Branches',                'admin.branches.index'],
            ['Approval limits',         'admin.approval-limits.index'],
            ['Document templates',      'admin.document-templates.index'],
        ], ['settings.manage', 'users.view', 'users.manage']],
    ];

    $currentRoute = request()->route()?->getName();
    $permissionService = app(\App\Services\PermissionService::class);
    $canSeeSection = function (?array $perms) use ($permissionService) {
        if ($perms === null || count($perms) === 0) {
            return true;
        }

        return auth()->check() && $permissionService->hasAny(auth()->user(), $perms);
    };

    $filterNavItems = function (array $items) use ($permissionService) {
        $departmentAccess = app(\App\Services\DepartmentAccessService::class);

        return array_values(array_filter($items, function (array $item) use ($permissionService, $departmentAccess) {
            if (($item[1] ?? '') === '__group__') {
                return true;
            }

            $permission = $item[2] ?? null;
            $route = $item[1] ?? '';

            if ($permission !== null && (! auth()->check() || ! $permissionService->has(auth()->user(), $permission))) {
                return false;
            }

            if (auth()->check() && ! $departmentAccess->canAccessRoute(auth()->user(), $route)) {
                return false;
            }

            return true;
        }));
    };

    $visibleSections = [];
    $activeSectionTabs = [];

    foreach ($sections as [$label, $icon, $items, $sectionPerms]) {
        if (! $canSeeSection($sectionPerms)) {
            continue;
        }

        $visibleItems = $filterNavItems($items);

        if (count($visibleItems) === 0) {
            continue;
        }

        $childRoutes = array_values(array_filter(
            array_column($visibleItems, 1),
            fn ($route) => $route !== '__group__',
        ));
        $isActive = in_array($currentRoute, $childRoutes, true);

        $visibleSections[] = [
            'label'       => $label,
            'items'       => $visibleItems,
            'isActive'    => $isActive,
            'targetRoute' => collect($visibleItems)->first(fn ($item) => ($item[1] ?? '') !== '__group__')[1] ?? $visibleItems[0][1],
        ];

        if ($isActive) {
            $activeSectionTabs = $visibleItems;
        }
    }

    $adminAlerts = app(\App\Services\AdminAlertService::class);
    $adminAlertItems = $adminAlerts->alerts();
@endphp

<div class="min-h-screen flex flex-col">

    {{-- Top bar: brand + utilities --}}
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-brand/10 shadow-sm">
        <div class="flex h-14 items-center justify-between gap-4 px-4 lg:px-6">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 shrink-0">
                <x-site.brand-mark size="sm" />
                <div class="hidden sm:block">
                    <div class="text-sm font-semibold text-gray-900 leading-tight">{{ brand_name() }}</div>
                    <div class="text-[11px] text-brand leading-tight font-semibold">Console</div>
                </div>
            </a>

            <div class="admin-menu flex items-center gap-2 sm:gap-3">
                <details class="relative">
                    <summary class="relative p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
                        @if ($adminAlerts->unreadCount() > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $adminAlerts->unreadCount() > 9 ? '9+' : $adminAlerts->unreadCount() }}</span>
                        @endif
                    </summary>
                    <div class="absolute right-0 top-full mt-2 w-96 rounded-xl bg-white shadow-xl ring-1 ring-gray-200 overflow-hidden z-50">
                        <div class="px-4 py-3 border-b border-gray-100"><p class="text-sm font-semibold">Admin alerts</p></div>
                        @forelse ($adminAlertItems as $alert)
                            <a href="{{ $alert['url'] }}" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-50">
                                <p class="text-sm text-gray-800">{{ $alert['label'] }}</p>
                                <p class="text-xs text-amber-700 font-semibold mt-0.5">{{ $alert['count'] }} pending</p>
                            </a>
                        @empty
                            <p class="px-4 py-8 text-sm text-gray-500 text-center">No pending alerts.</p>
                        @endforelse
                    </div>
                </details>

                <details class="relative">
                    <summary class="flex items-center gap-2 rounded-full pl-2 pr-1.5 py-1 border border-transparent hover:border-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-amber-500/30 transition">
                        <span class="text-sm font-medium text-gray-700 hidden md:block max-w-[8rem] truncate">
                            {{ auth()->user()?->name }}
                        </span>
                        <div class="size-8 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 grid place-items-center text-white text-sm font-semibold shadow-sm ring-2 ring-white">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <svg class="size-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl ring-1 ring-black/5 overflow-hidden z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()?->name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ auth()->user()?->email }}</div>
                            <div class="mt-1.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold tracking-wide bg-amber-100 text-amber-800">
                                    {{ auth()->user()?->roleLabel() }}
                                </span>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 py-1">
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </div>

        {{-- Horizontal main navigation --}}
        <nav class="admin-menu bg-brand border-t border-white/10" aria-label="Main navigation">
            <div class="flex flex-wrap items-stretch gap-0.5 px-2 lg:px-4">
                @foreach ($visibleSections as $section)
                    @if (count($section['items']) === 1)
                        <a href="{{ route($section['targetRoute']) }}"
                           class="shrink-0 inline-flex items-center px-3 py-2.5 text-sm font-medium whitespace-nowrap rounded-t-lg transition
                                  {{ $section['isActive']
                                       ? 'bg-brand-gold text-brand font-bold'
                                       : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                            {{ $section['label'] }}
                        </a>
                    @else
                        <details class="relative shrink-0">
                            <summary class="inline-flex items-center gap-1 px-3 py-2.5 text-sm font-medium whitespace-nowrap rounded-t-lg transition
                                           {{ $section['isActive']
                                                ? 'bg-brand-gold text-brand font-bold'
                                                : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                                {{ $section['label'] }}
                                <svg class="size-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <div class="absolute left-0 top-full z-50 min-w-[13rem] max-h-80 overflow-y-auto rounded-b-lg rounded-tr-lg bg-white shadow-xl ring-1 ring-gray-200 py-1">
                                @foreach ($section['items'] as $item)
                                    @if (($item[1] ?? '') === '__group__')
                                        <div class="px-4 pt-2.5 pb-1 text-[10px] font-bold uppercase tracking-widest text-gray-400 first:pt-1">
                                            {{ trim($item[0], ' —') }}
                                        </div>
                                    @else
                                        @php $itemActive = $currentRoute === $item[1]; @endphp
                                        <a href="{{ route($item[1]) }}"
                                           class="block px-4 py-2 text-sm transition
                                                  {{ $itemActive
                                                       ? 'bg-amber-50 text-amber-800 font-semibold'
                                                       : 'text-gray-700 hover:bg-gray-50' }}">
                                            {{ $item[0] }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach
            </div>
        </nav>
    </header>

    {{-- Page content --}}
    <main class="flex-1 p-4 lg:p-6">
        <div class="mb-5">
            @if ($backUrl)
                <a href="{{ $backUrl }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 mb-3">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $backLabel }}
                </a>
            @endif
            @if ($heading !== '')
                <h1 class="text-xl font-semibold text-gray-900">{{ $heading ?? ($title ?? 'Dashboard') }}</h1>
                @isset($subheading)
                    <p class="text-sm text-gray-500 mt-0.5">{{ $subheading }}</p>
                @endisset
            @endif
        </div>

        @if (count($activeSectionTabs) > 1)
            <x-admin.tabs :items="array_map(
                fn ($t) => ['label' => $t[0], 'route' => $t[1]],
                array_values(array_filter($activeSectionTabs, fn ($t) => ($t[1] ?? '') !== '__group__'))
            )" />
        @endif

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors instanceof \Illuminate\Support\ViewErrorBag && $errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</div>

{{-- Embedded document preview drawer (underwriting) --}}
<div id="kf-doc-drawer" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" onclick="window.kfCloseDocumentPreview()"></div>
    <aside class="absolute top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl flex flex-col">
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Document preview</p>
                <p id="kf-doc-drawer-title" class="text-sm font-semibold text-gray-900 truncate"></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a id="kf-doc-drawer-open-tab" href="#" target="_blank" rel="noopener"
                   class="text-xs font-semibold text-amber-700 hover:text-amber-800 px-3 py-1.5 rounded-lg ring-1 ring-gray-200 bg-white">
                    Open in tab
                </a>
                <button type="button" onclick="window.kfCloseDocumentPreview()"
                        class="text-gray-500 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100" aria-label="Close">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div class="flex-1 min-h-0 bg-gray-100 p-2">
            <iframe id="kf-doc-drawer-frame" class="hidden w-full h-full rounded-lg bg-white ring-1 ring-gray-200" title="Document preview"></iframe>
            <div id="kf-doc-drawer-image-wrap" class="hidden h-full overflow-auto flex items-start justify-center p-2">
                <img id="kf-doc-drawer-image" alt="" class="max-w-full rounded-lg shadow-sm ring-1 ring-gray-200">
            </div>
        </div>
    </aside>
</div>

@livewireScripts
<script>
window.kfOpenDocumentPreview = function (url, title, type) {
    var drawer = document.getElementById('kf-doc-drawer');
    var frame = document.getElementById('kf-doc-drawer-frame');
    var imageWrap = document.getElementById('kf-doc-drawer-image-wrap');
    var image = document.getElementById('kf-doc-drawer-image');
    var titleEl = document.getElementById('kf-doc-drawer-title');
    var openTab = document.getElementById('kf-doc-drawer-open-tab');

    if (! drawer) return;

    titleEl.textContent = title || 'Document';
    openTab.href = url;

    if (type === 'pdf' || url.toLowerCase().indexOf('.pdf') !== -1) {
        frame.classList.remove('hidden');
        imageWrap.classList.add('hidden');
        frame.src = url;
    } else {
        frame.classList.add('hidden');
        frame.removeAttribute('src');
        imageWrap.classList.remove('hidden');
        image.src = url;
        image.alt = title || 'Document';
    }

    drawer.classList.remove('hidden');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
};

window.kfCloseDocumentPreview = function () {
    var drawer = document.getElementById('kf-doc-drawer');
    var frame = document.getElementById('kf-doc-drawer-frame');
    if (! drawer) return;
    drawer.classList.add('hidden');
    drawer.setAttribute('aria-hidden', 'true');
    if (frame) {
        frame.removeAttribute('src');
    }
    document.body.classList.remove('overflow-hidden');
};

document.addEventListener('click', function (event) {
    var openBtn = event.target.closest('[data-open-dialog]');
    if (openBtn) {
        var dialog = document.getElementById(openBtn.getAttribute('data-open-dialog'));
        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
        return;
    }
    var closeBtn = event.target.closest('[data-close-dialog]');
    if (closeBtn) {
        var closeDialog = document.getElementById(closeBtn.getAttribute('data-close-dialog'));
        if (closeDialog && typeof closeDialog.close === 'function') {
            closeDialog.close();
        }
        return;
    }
    document.querySelectorAll('.admin-menu details[open]').forEach(function (details) {
        if (! details.contains(event.target)) {
            details.removeAttribute('open');
        }
    });
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        window.kfCloseDocumentPreview();
        document.querySelectorAll('.admin-menu details[open]').forEach(function (details) {
            details.removeAttribute('open');
        });
        document.querySelectorAll('dialog[open]').forEach(function (dialog) {
            dialog.close();
        });
    }
});
</script>
<script>
(function () {
    function spinnerHtml(label) {
        return '<svg class="size-4 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>' +
            '</svg><span>' + label + '</span>';
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.skipLoading === '1' || form.dataset.loadingBound === '1') {
            return;
        }
        // Wizard forms already manage their own submit loading UI.
        if (form.querySelector('.admin-wizard') || form.dataset.submitGuard === '1') {
            return;
        }

        const submitter = event.submitter instanceof HTMLButtonElement
            ? event.submitter
            : form.querySelector('button[type="submit"], input[type="submit"]');
        if (! submitter || submitter.disabled) {
            return;
        }

        form.dataset.loadingBound = '1';
        const label = (submitter.dataset.loadingLabel
            || submitter.getAttribute('data-submit-label')
            || submitter.textContent
            || 'Saving').trim().replace(/\s+/g, ' ');
        const loadingLabel = /…$|\.\.\.$/.test(label) ? label : (label + '…');

        submitter.disabled = true;
        submitter.classList.add('opacity-70', 'cursor-wait');
        if (submitter.tagName === 'BUTTON') {
            submitter.innerHTML = spinnerHtml(loadingLabel);
        } else {
            submitter.value = loadingLabel;
        }

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
            if (btn !== submitter) btn.disabled = true;
        });
    }, true);
})();
</script>
<x-admin.number-format-script />
@stack('scripts')
</body>
</html>
