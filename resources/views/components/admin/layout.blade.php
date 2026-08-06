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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/alpine-init.js'])
    @livewireStyles
    <style>
        [x-cloak]{display:none!important}
        .admin-menu details > summary{list-style:none;cursor:pointer}
        .admin-menu details > summary::-webkit-details-marker{display:none}
        dialog{margin:auto;border:0;padding:0;max-width:calc(100vw - 2rem)}
        dialog::backdrop{background:rgba(0,0,0,.4)}
    </style>
</head>
<body class="h-full bg-[#f4f7f5] text-gray-900 antialiased">

@php
    $sections = [
        ['Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10', [
            ['Dashboard', 'admin.dashboard'],
        ], null],
        ['Lending', 'M3 10h18M3 14h18M5 6h14M5 18h14', [
            ['— Credit screening —', '__group__'],
            ['Screening home',        'admin.teams.screening'],
            ['Credit screening',      'admin.loan-applications.pipeline.under-review'],
            ['— Credit committee —', '__group__'],
            ['Committee home',        'admin.teams.committee'],
            ['Credit committee',      'admin.loan-applications.pre-approvals'],
            ['— Credit management —', '__group__'],
            ['Management home',       'admin.teams.management'],
            ['Management queue',      'admin.loan-applications.pipeline.approved'],
            ['Release queue',         'admin.loan-applications.pipeline.disbursement'],
            ['Payout queue',          'admin.loans.disbursement'],
            ['— Applications —', '__group__'],
            ['All applications',      'admin.loan-applications.index'],
            ['Incomplete drafts',     'admin.loan-applications.incomplete'],
            ['— Customers —', '__group__'],
            ['All Customers', 'admin.customers.index'],
            ['— Payments —', '__group__'],
            ['Payments ledger',       'admin.payments.ledger'],
            ['Membership & renewals', 'admin.membership-payments.index'],
            ['Loan repayments',       'admin.repayments.index'],
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
            ['— Recovery ops —', '__group__'],
            ['Recovery assignments','admin.recovery.assignments.index'],
        ], ['applications.view', 'customers.view', 'membership.approve_payments', 'loans.view', 'finance.operations']],
        ['Assets', 'M3 7h18M3 12h18M3 17h18', [
            ['Asset Marketplace',   'admin.marketplace-assets.index', 'marketplace.view'],
            ['Asset Requests',      'admin.asset-requests.index', 'marketplace.view'],
            ['Suppliers',           'admin.partners.suppliers'],
        ], ['marketplace.view']],
        ['Partners', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5a4 4 0 11-8 0 4 4 0 018 0z', [
            ['Partners hub',            'admin.partners.index'],
            ['Enrollment applications', 'admin.partner-applications.index'],
            ['Partner Applications',    'admin.partners.applications'],
            ['Partner Tasks',           'admin.partners.tasks'],
            ['Recovery partners',       'admin.recovery.partners.index'],
            ['Partner payout requests', 'admin.partner-payout-requests.index', 'finance.operations'],
        ], null],
        ['Money', 'M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8v8m0 0v2m-9-5a9 9 0 1018 0 9 9 0 00-18 0z', [
            ['— Capital —', '__group__'],
            ['Capital funding',      'admin.capital-funding.index'],
            ['Funded loans',         'admin.capital-funding.funded-loans'],
            ['Withdrawal requests',  'admin.capital-funding.withdrawals'],
            ['Capital Partners',     'admin.lenders.index'],
            ['Funding Pools',      'admin.funding-pools.index'],
            ['Loan allocations',   'admin.lender-investments.index'],
            ['— Ledgers —', '__group__'],
            ['Payments ledger',           'admin.payments.ledger',              'finance.operations'],
            ['Payout ledger',             'admin.payouts.ledger',               'finance.operations'],
            ['Operational expenses',      'admin.expenses.index',               'finance.operations'],
            ['Journal Entries',           'admin.journal-entries.index',        'finance.operations'],
            ['Reconciliations',           'admin.reconciliations.index',        'finance.operations'],
            ['— Other money ops —', '__group__'],
            ['Payment gateway settlements', 'admin.settlements.index',       'finance.operations'],
            ['Borrower refunds',      'admin.borrower-refunds.index',        'finance.operations'],
            ['Chart of accounts',     'admin.chart-of-accounts.index',       'finance.accounts'],
        ], ['finance.accounts', 'finance.methods', 'finance.operations']],
        ['Reports', 'M3 3v18h18M7 17V9m4 8V5m4 12v-7m4 7V11', [
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
        ], ['reports.view', 'finance.reports']],
        ['Ops', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z', [
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
        ], ['audit.view', 'users.view', 'users.manage', 'settings.manage', 'applications.view']],
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
                    <div class="absolute right-0 top-full mt-2 w-96 rounded-2xl bg-white/95 shadow-xl ring-1 ring-brand/10 overflow-hidden z-50 backdrop-blur">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">Admin alerts</p>
                        </div>
                        @forelse ($adminAlertItems as $alert)
                            <a href="{{ $alert['url'] }}" class="block px-4 py-3 hover:bg-brand-muted/30 border-b border-gray-50">
                                <p class="text-[11px] font-bold uppercase tracking-widest text-brand">{{ $alert['group'] ?? 'Queue' }}</p>
                                <p class="text-sm text-gray-800 mt-0.5">{{ $alert['label'] }}</p>
                                <p class="text-xs text-brand font-semibold mt-0.5">{{ $alert['count'] }} pending</p>
                            </a>
                        @empty
                            <p class="px-4 py-8 text-sm text-gray-500 text-center">No pending alerts.</p>
                        @endforelse
                    </div>
                </details>

                <details class="relative">
                    <summary class="flex items-center gap-2 rounded-full pl-2 pr-1.5 py-1 border border-transparent hover:border-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand/30 transition">
                        <span class="text-sm font-medium text-gray-700 hidden md:block max-w-[8rem] truncate">
                            {{ auth()->user()?->name }}
                        </span>
                        <div class="size-8 rounded-full bg-gradient-to-br from-brand-gold to-brand grid place-items-center text-white text-sm font-semibold shadow-sm ring-2 ring-white">
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
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold tracking-wide bg-brand-muted text-brand">
                                    {{ auth()->user()?->roleLabel() }}
                                </span>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 py-1">
                            <a href="{{ route('admin.settings.account-security') }}"
                               class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Account security
                            </a>
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
                                                       ? 'bg-brand-muted text-brand font-semibold'
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
                <h1 class="text-xl font-semibold text-brand">{{ $heading ?? ($title ?? 'Dashboard') }}</h1>
                @isset($subheading)
                    <p class="text-sm text-gray-600 mt-0.5">{{ $subheading }}</p>
                @endisset
            @endif
        </div>

        {{-- Flash + validation feedback is shown via premium modal (below). --}}

        {{ $slot }}
    </main>
</div>

<x-site.feedback-modal name="admin" title="Console" />
<x-site.confirm-modal name="admin" />
<script>
    window.showAdminFeedback = (detail = {}) => {
        window.dispatchEvent(new CustomEvent('open-feedback-admin', {
            detail: typeof detail === 'string' ? { message: detail } : detail,
        }));
    };
    window.confirmForm = (form, detail = {}) => {
        const tone = detail.tone
            || (String(detail.confirmClass || '').includes('red') ? 'warning' : 'confirm');
        window.dispatchEvent(new CustomEvent('open-confirm-admin', {
            detail: { form: form || null, tone, ...detail },
        }));
    };
    window.confirmAction = (detail = {}) => window.confirmForm(null, detail);
    document.addEventListener('DOMContentLoaded', () => {
        @if (session('feedback'))
            @php $feedback = session('feedback'); @endphp
            window.showAdminFeedback({
                tone: @js($feedback['tone'] ?? 'info'),
                title: @js($feedback['title'] ?? 'Console'),
                message: @js($feedback['message'] ?? ''),
                lines: @js($feedback['lines'] ?? []),
            });
        @elseif (session('status'))
            @php
                $statusMessage = (string) session('status');
                $statusTone = str_contains(strtolower($statusMessage), 'fail')
                    || str_contains(strtolower($statusMessage), 'error')
                    ? 'error'
                    : 'success';
            @endphp
            window.showAdminFeedback({
                tone: @js($statusTone),
                title: @js($statusTone === 'error' ? __('borrower.feedback.tones.error') : __('borrower.feedback.tones.success')),
                message: @js($statusMessage),
            });
        @endif
        @if (session('error'))
            window.showAdminFeedback({
                tone: 'error',
                title: @js(__('borrower.feedback.tones.error')),
                message: @js(session('error')),
            });
        @endif
        @if ($errors instanceof \Illuminate\Support\ViewErrorBag && $errors->any())
            window.showAdminFeedback({
                tone: 'error',
                title: @js(__('borrower.layout.form_errors')),
                message: '',
                lines: @js($errors->all()),
            });
        @endif
    });
</script>

{{-- Centered document preview popup (underwriting) --}}
<div id="kf-doc-drawer" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" onclick="window.kfCloseDocumentPreview()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-8 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-3xl max-h-[90vh] bg-white rounded-2xl shadow-2xl ring-1 ring-black/10 flex flex-col overflow-hidden"
             role="dialog" aria-modal="true" aria-labelledby="kf-doc-drawer-title">
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50 shrink-0">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Document preview</p>
                    <p id="kf-doc-drawer-title" class="text-sm font-semibold text-gray-900 truncate"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a id="kf-doc-drawer-open-tab" href="#" target="_blank" rel="noopener"
                       class="text-xs font-semibold text-brand hover:text-brand-light px-3 py-1.5 rounded-lg ring-1 ring-brand/15 bg-white">
                        Open in tab
                    </a>
                    <button type="button" onclick="window.kfCloseDocumentPreview()"
                            class="text-gray-500 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100" aria-label="Close">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div class="flex-1 min-h-0 bg-gray-100 p-3 overflow-auto" style="min-height: 280px; max-height: calc(90vh - 4rem);">
                <iframe id="kf-doc-drawer-frame" class="hidden w-full rounded-lg bg-white ring-1 ring-gray-200" style="height: min(70vh, 640px);" title="Document preview"></iframe>
                <div id="kf-doc-drawer-image-wrap" class="hidden w-full flex items-center justify-center">
                    <img id="kf-doc-drawer-image" alt="" class="max-w-full max-h-[70vh] rounded-lg shadow-sm ring-1 ring-gray-200 object-contain">
                </div>
            </div>
        </div>
    </div>
</div>

@livewireScripts
<script>
window.kfLockBodyScroll = function () {
    if (document.body.dataset.kfScrollLocked === '1') return;
    var y = window.scrollY || window.pageYOffset || 0;
    document.body.dataset.kfScrollY = String(y);
    document.body.dataset.kfScrollLocked = '1';
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + y + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
};

window.kfUnlockBodyScroll = function () {
    if (document.body.dataset.kfScrollLocked !== '1') {
        document.body.classList.remove('overflow-hidden');
        return;
    }
    var y = parseInt(document.body.dataset.kfScrollY || '0', 10) || 0;
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    delete document.body.dataset.kfScrollLocked;
    delete document.body.dataset.kfScrollY;
    document.body.classList.remove('overflow-hidden');
    window.scrollTo(0, y);
};

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
    window.kfLockBodyScroll();
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
    window.kfUnlockBodyScroll();
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

    function resetSubmitter(submitter) {
        if (!(submitter instanceof HTMLElement)) {
            return;
        }
        if (submitter.dataset.originalHtml != null) {
            submitter.innerHTML = submitter.dataset.originalHtml;
            delete submitter.dataset.originalHtml;
        } else if (submitter.dataset.originalValue != null) {
            submitter.value = submitter.dataset.originalValue;
            delete submitter.dataset.originalValue;
        }
        submitter.disabled = false;
        submitter.classList.remove('opacity-70', 'cursor-wait');
    }

    function resetFormLoading(form) {
        if (!(form instanceof HTMLFormElement) || form.dataset.loadingBound !== '1') {
            return;
        }
        delete form.dataset.loadingBound;
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(resetSubmitter);
    }

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) {
            return;
        }

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

        if (submitter.tagName === 'BUTTON') {
            submitter.dataset.originalHtml = submitter.innerHTML;
        } else {
            submitter.dataset.originalValue = submitter.value;
        }

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
    });

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-loading-bound="1"]').forEach(resetFormLoading);
    });
})();
</script>
<x-admin.number-format-script />
@stack('scripts')
</body>
</html>
