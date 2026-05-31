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

<div class="flex min-h-screen">

    {{-- ============ SIDEBAR ============ --}}
    <aside class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-gray-900 text-gray-200">
        <div class="flex h-16 items-center gap-3 px-6 border-b border-white/10">
            <div class="size-9 rounded-lg bg-amber-500 grid place-items-center font-bold text-gray-900">K</div>
            <div>
                <div class="text-sm font-semibold text-white">Kopafasta</div>
                <div class="text-xs text-gray-400">Console</div>
            </div>
        </div>

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
                ], ['settings.manage'                ], null],
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
                    ['Chart of Accounts',     'admin.chart-of-accounts.index'],
                    ['Bank Accounts',         'admin.bank-accounts.index'],
                    ['Mobile Money Accounts', 'admin.mobile-money-accounts.index'],
                    ['Disbursement Methods',  'admin.disbursement-methods.index'],
                    ['Repayment Methods',     'admin.repayment-methods.index'],
                    ['Charges & Fees',        'admin.charges-fees.index'],
                    ['Write-off Rules',       'admin.write-off-rules.index'],
                    ['Expenses',              'admin.expenses.index'],
                    ['Settlements',           'admin.settlements.index'],
                    ['Reconciliations',       'admin.reconciliations.index'],
                    ['Journal Entries',       'admin.journal-entries.index'],
                ], null],
                ['Reports', 'M3 3v18h18M7 17V9m4 8V5m4 12v-7m4 7V11', [
                    ['Financial Overview','admin.reports.financial-overview'],
                    ['Portfolio',         'admin.reports.portfolio'],
                    ['Disbursements',     'admin.reports.disbursements'],
                    ['Repayments',        'admin.reports.repayments'],
                    ['Arrears',           'admin.reports.arrears'],
                    ['PAR',               'admin.reports.par'],
                    ['NPL',               'admin.reports.npl'],
                    ['Customers',         'admin.reports.customers'],
                    ['Trial Balance',     'admin.reports.trial-balance'],
                    ['Income Statement',  'admin.reports.income-statement'],
                    ['Balance Sheet',     'admin.reports.balance-sheet'],
                    ['Cash Flow',         'admin.reports.cash-flow'],
                    ['Vendor Performance','admin.reports.vendor-performance'],
                ], null],
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
                ], ['settings.manage']],
            ];

            $currentRoute = request()->route()?->getName();
            $permissionService = app(\App\Services\PermissionService::class);
            $canSeeSection = function (?array $perms) use ($permissionService) {
                if ($perms === null || count($perms) === 0) {
                    return true;
                }

                return auth()->check() && $permissionService->hasAny(auth()->user(), $perms);
            };

            // Resolve the tab items belonging to the section that owns the current route.
            $activeSectionTabs = [];
            foreach ($sections as [$__l, $__ic, $__items, $__perms]) {
                if (! $canSeeSection($__perms)) {
                    continue;
                }
                if (in_array($currentRoute, array_column($__items, 1), true)) {
                    $activeSectionTabs = $__items;
                    break;
                }
            }
        @endphp

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 text-sm">
            @foreach ($sections as [$label, $icon, $items, $sectionPerms])
                @if (! $canSeeSection($sectionPerms))
                    @continue
                @endif
                @php
                    $childRoutes    = array_column($items, 1);
                    $isActiveBranch = in_array($currentRoute, $childRoutes, true);
                    $targetRoute    = $items[0][1];
                @endphp
                <a href="{{ route($targetRoute) }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg transition
                          {{ $isActiveBranch ? 'bg-amber-500 text-gray-900 font-semibold' : 'hover:bg-white/5 text-gray-300' }}">
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                    </svg>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="w-full text-left flex items-center gap-3 px-3 py-2 rounded-lg text-gray-300 hover:bg-white/5 text-sm">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span>Sign out</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- ============ MAIN ============ --}}
    <div class="flex-1 md:ml-64 flex flex-col">

        <header class="sticky top-0 z-30 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">{{ $heading ?? ($title ?? 'Dashboard') }}</h1>
                @isset($subheading)
                    <p class="text-xs text-gray-500">{{ $subheading }}</p>
                @endisset
            </div>
            <div class="flex items-center gap-3">
                @php $adminAlerts = app(\App\Services\AdminAlertService::class); $adminAlertItems = $adminAlerts->alerts(); @endphp
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
                {{-- Profile dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            @click.outside="open = false"
                            class="flex items-center gap-2.5 rounded-full pl-3 pr-1.5 py-1
                                   border border-transparent hover:border-gray-200 hover:bg-gray-50
                                   focus:outline-none focus:ring-2 focus:ring-amber-500/30 transition">
                        <span class="text-sm font-medium text-gray-700 hidden sm:block">
                            {{ auth()->user()?->name }}
                        </span>
                        <div class="size-9 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 grid place-items-center text-white font-semibold shadow-sm ring-2 ring-white">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <svg class="size-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="absolute right-0 mt-2 w-64 origin-top-right z-40
                                bg-white rounded-xl shadow-xl ring-1 ring-black/5 overflow-hidden">

                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="text-sm font-semibold text-gray-900 truncate">
                                {{ auth()->user()?->name }}
                            </div>
                            <div class="text-xs text-gray-500 truncate">
                                {{ auth()->user()?->email }}
                            </div>
                            <div class="mt-1.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-amber-100 text-amber-800">
                                    {{ str_replace('_', ' ', auth()->user()?->role ?? 'user') }}
                                </span>
                            </div>
                        </div>

                        <div class="py-1">
                            <a href="#" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Your profile
                            </a>
                            <a href="#" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Settings
                            </a>
                        </div>

                        <div class="border-t border-gray-100 py-1">
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6">
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
</div>

@livewireScripts
</body>
</html>
