<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Console' }} · Kopafasta</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">

@php
    $sections = [
        ['Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10', [
            ['Dashboard', 'admin.dashboard'],
        ], null],
        ['Applications', 'M9 12h6m-6 4h6M5 7h14M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z', [
            ['All Applications',      'admin.loan-applications.index'],
            ['New Applications',      'admin.loan-applications.new'],
            ['Pending Documents',     'admin.loan-applications.pending-documents'],
            ['Under Review',          'admin.loan-applications.under-review'],
            ['Pre-Approvals',         'admin.loan-applications.pre-approvals'],
            ['Final Approvals',       'admin.loan-applications.final-approvals'],
            ['Rejected Applications', 'admin.loan-applications.rejected'],
        ], ['applications.view']],
        ['Customers', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5a4 4 0 11-8 0 4 4 0 018 0z', [
            ['All Customers', 'admin.customers.index'],
            ['Membership Payments', 'admin.membership-payments.index'],
            ['Identity & KYC', 'admin.customer-kycs.index'],
            ['Face verification', 'admin.face-verifications.index'],
        ], ['customers.view', 'kyc.review', 'membership.approve_payments']],
        ['Loans', 'M3 10h18M3 14h18M5 6h14M5 18h14', [
            ['All Loans',     'admin.loans.index'],
            ['Active Loans',  'admin.loans.active'],
            ['Disbursement',  'admin.loans.disbursement'],
            ['Repayments',    'admin.repayments.index'],
            ['Arrears',       'admin.loans.arrears'],
            ['Restructuring', 'admin.loans.restructuring'],
            ['Closed Loans',  'admin.loans.closed'],
        ], ['loans.view']],
        ['Loan Products', 'M20 7L12 3 4 7m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', [
            ['Loan Product Configuration', 'admin.settings.loan-products'],
        ], ['settings.manage']],
        ['Partners', 'M3 7h18M3 12h18M3 17h18', [
            ['All Partners',         'admin.vendors.index'],
            ['Vendor Applications', 'admin.vendors.applications'],
            ['GPS Installers',      'admin.vendors.gps-installers'],
            ['Insurance Providers', 'admin.vendors.insurance-providers'],
            ['Valuers',             'admin.vendors.valuers'],
            ['Vendor Tasks',        'admin.vendors.tasks'],
        ], null],
        ['Capital', 'M19 7H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2zM3 11h18', [
            ['Capital Partners',     'admin.lenders.index'],
            ['Funding Pools',      'admin.funding-pools.index'],
            ['Lender Investments', 'admin.lender-investments.index'],
        ], null],
        ['Finance', 'M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8v8m0 0v2m-9-5a9 9 0 1018 0 9 9 0 00-18 0z', [
            ['Chart of Accounts',     'admin.chart-of-accounts.index',     'finance.accounts'],
            ['Bank Accounts',         'admin.bank-accounts.index',         'finance.accounts'],
            ['Mobile Money Accounts', 'admin.mobile-money-accounts.index', 'finance.accounts'],
            ['Disbursement Methods',  'admin.disbursement-methods.index',  'finance.methods'],
            ['Repayment Methods',     'admin.repayment-methods.index',     'finance.methods'],
            ['Charges & Fees',        'admin.charges-fees.index',          'finance.methods'],
            ['Write-off Rules',       'admin.write-off-rules.index',       'finance.methods'],
            ['Expenses',              'admin.expenses.index',              'finance.operations'],
            ['Settlements',           'admin.settlements.index',           'finance.operations'],
            ['Reconciliations',       'admin.reconciliations.index',       'finance.operations'],
            ['Journal Entries',       'admin.journal-entries.index',       'finance.operations'],
        ], ['finance.accounts', 'finance.methods', 'finance.operations']],
        ['Reports', 'M3 3v18h18M7 17V9m4 8V5m4 12v-7m4 7V11', [
            ['Financial Overview','admin.reports.financial-overview', 'finance.reports'],
            ['Portfolio',         'admin.reports.portfolio',          'reports.view'],
            ['Disbursements',     'admin.reports.disbursements',      'reports.view'],
            ['Repayments',        'admin.reports.repayments',         'reports.view'],
            ['Arrears',           'admin.reports.arrears',            'reports.view'],
            ['PAR',               'admin.reports.par',                'reports.view'],
            ['NPL',               'admin.reports.npl',                'finance.reports'],
            ['Customers',         'admin.reports.customers',          'reports.view'],
            ['Trial Balance',     'admin.reports.trial-balance',      'finance.reports'],
            ['Income Statement',  'admin.reports.income-statement',   'finance.reports'],
            ['Balance Sheet',     'admin.reports.balance-sheet',      'finance.reports'],
            ['Cash Flow',         'admin.reports.cash-flow',          'finance.reports'],
            ['Vendor Performance','admin.reports.vendor-performance', 'reports.view'],
        ], ['reports.view', 'finance.reports']],
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
        ['Settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z', [
            ['Company Profile',         'admin.settings.company'],
            ['SMS / Email Gateways',    'admin.settings.gateways'],
            ['KYC Requirements',        'admin.settings.kyc'],
            ['Loan Rules',              'admin.settings.loan-rules'],
            ['AML Thresholds',          'admin.settings.aml'],
            ['Finance Defaults',        'admin.settings.finance'],
            ['Branches',                'admin.branches.index'],
            ['Departments',             'admin.departments.index'],
            ['Users',                   'admin.users.index'],
            ['Roles & Permissions',     'admin.roles.index'],
            ['Approval Limits',         'admin.approval-limits.index'],
            ['Notification Templates',  'admin.notification-templates.index'],
            ['Document Templates',      'admin.document-templates.index'],
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
        return array_values(array_filter($items, function (array $item) use ($permissionService) {
            $permission = $item[2] ?? null;

            if ($permission === null) {
                return true;
            }

            return auth()->check() && $permissionService->has(auth()->user(), $permission);
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

        $childRoutes = array_column($visibleItems, 1);
        $isActive = in_array($currentRoute, $childRoutes, true);

        $visibleSections[] = [
            'label'       => $label,
            'items'       => $visibleItems,
            'isActive'    => $isActive,
            'targetRoute' => $visibleItems[0][1],
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
    <header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm">
        <div class="flex h-14 items-center justify-between gap-4 px-4 lg:px-6">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 shrink-0">
                <div class="size-9 rounded-lg bg-amber-500 grid place-items-center font-bold text-gray-900">K</div>
                <div class="hidden sm:block">
                    <div class="text-sm font-semibold text-gray-900 leading-tight">Kopafasta</div>
                    <div class="text-[11px] text-gray-500 leading-tight">Console</div>
                </div>
            </a>

            <div class="flex items-center gap-2 sm:gap-3">
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="relative p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
                        @if ($adminAlerts->unreadCount() > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $adminAlerts->unreadCount() > 9 ? '9+' : $adminAlerts->unreadCount() }}</span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak class="absolute right-0 mt-2 w-96 rounded-xl bg-white shadow-xl ring-1 ring-gray-200 overflow-hidden z-50">
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
                </div>

                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            @click.outside="open = false"
                            class="flex items-center gap-2 rounded-full pl-2 pr-1.5 py-1 border border-transparent hover:border-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-amber-500/30 transition">
                        <span class="text-sm font-medium text-gray-700 hidden md:block max-w-[8rem] truncate">
                            {{ auth()->user()?->name }}
                        </span>
                        <div class="size-8 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 grid place-items-center text-white text-sm font-semibold shadow-sm ring-2 ring-white">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <svg class="size-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         class="absolute right-0 mt-2 w-64 origin-top-right z-50 bg-white rounded-xl shadow-xl ring-1 ring-black/5 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()?->name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ auth()->user()?->email }}</div>
                            <div class="mt-1.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-amber-100 text-amber-800">
                                    {{ str_replace('_', ' ', auth()->user()?->role ?? 'user') }}
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
                </div>
            </div>
        </div>

        {{-- Horizontal main navigation --}}
        <nav class="bg-gray-900 border-t border-white/5" aria-label="Main navigation">
            <div class="flex items-stretch gap-0.5 px-2 lg:px-4 overflow-x-auto scrollbar-thin">
                @foreach ($visibleSections as $section)
                    @if (count($section['items']) === 1)
                        <a href="{{ route($section['targetRoute']) }}"
                           class="shrink-0 inline-flex items-center px-3 py-2.5 text-sm font-medium whitespace-nowrap rounded-t-lg transition
                                  {{ $section['isActive']
                                       ? 'bg-gray-50 text-gray-900'
                                       : 'text-gray-300 hover:text-white hover:bg-white/5' }}">
                            {{ $section['label'] }}
                        </a>
                    @else
                        <div x-data="{ open: false }" class="relative shrink-0">
                            <button type="button"
                                    @click="open = !open"
                                    @click.outside="open = false"
                                    class="inline-flex items-center gap-1 px-3 py-2.5 text-sm font-medium whitespace-nowrap rounded-t-lg transition
                                           {{ $section['isActive']
                                                ? 'bg-gray-50 text-gray-900'
                                                : 'text-gray-300 hover:text-white hover:bg-white/5' }}">
                                {{ $section['label'] }}
                                <svg class="size-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute left-0 top-full z-50 mt-0 min-w-[13rem] max-h-80 overflow-y-auto rounded-b-lg rounded-tr-lg bg-white shadow-xl ring-1 ring-gray-200 py-1">
                                @foreach ($section['items'] as $item)
                                    @php $itemActive = $currentRoute === $item[1]; @endphp
                                    <a href="{{ route($item[1]) }}"
                                       class="block px-4 py-2 text-sm transition
                                              {{ $itemActive
                                                   ? 'bg-amber-50 text-amber-800 font-semibold'
                                                   : 'text-gray-700 hover:bg-gray-50' }}">
                                        {{ $item[0] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </nav>
    </header>

    {{-- Page content --}}
    <main class="flex-1 p-4 lg:p-6">
        <div class="mb-5">
            <h1 class="text-xl font-semibold text-gray-900">{{ $heading ?? ($title ?? 'Dashboard') }}</h1>
            @isset($subheading)
                <p class="text-sm text-gray-500 mt-0.5">{{ $subheading }}</p>
            @endisset
        </div>

        @if (count($activeSectionTabs) > 1)
            <x-admin.tabs :items="array_map(fn($t) => ['label' => $t[0], 'route' => $t[1]], $activeSectionTabs)" />
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

        {{ $slot }}
    </main>
</div>

@livewireScripts
</body>
</html>
