<x-site.vendor-layout title="Partner dashboard" active="dashboard">
    @php
        $catLabels = [
            'gps_installer'      => 'GPS Installer',
            'insurance'          => 'Insurance Provider',
            'valuer'             => 'Valuer',
            'towing'             => 'Towing',
            'yard'               => 'Yard',
            'auctioneer'         => 'Auctioneer',
            'affiliate'          => 'Affiliate Partner',
        ];
        $isValuer = ($vendor->category ?? null) === 'valuer';
        $isRecoveryFocused = in_array($vendor->category ?? null, ['debt_collector', 'call_center', 'legal_partner', 'auctioneer', 'gps_installer'], true)
            || array_intersect($vendor->partnerRoles(), ['debt_collector', 'call_center', 'legal_partner', 'auctioneer', 'gps_installer']) !== [];
        $primaryCtaRoute = $isRecoveryFocused ? 'site.partner.recovery-cases' : 'site.partner.tasks';
        $primaryCtaLabel = $isValuer ? 'Open jobs' : ($isRecoveryFocused ? 'Open recovery cases' : 'View tasks');
    @endphp

    @if ($vendor->category === 'affiliate' && $affiliateStats)
        @php
            $affiliateKycApproved = in_array($vendor->affiliate_kyc_status, ['verified', 'approved'], true);
            $affiliateKycSubmitted = in_array($vendor->affiliate_kyc_status, ['submitted', 'verified', 'approved'], true);
        @endphp
        <div class="mb-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-6">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Affiliate program</h2>
                    <p class="text-sm text-gray-600 mt-1">Share your code and earn commission on referred customer fees.</p>
                    <p class="mt-3 font-mono text-sm font-semibold text-amber-800">{{ $affiliateLinks['affiliate_code'] ?? $vendor->affiliate_code }}</p>
                    <p class="text-xs text-gray-500 mt-1">KYC: {{ ucfirst($vendor->affiliate_kyc_status ?? 'pending') }}</p>
                </div>
                <a href="{{ route('site.partner.profile') }}" class="text-sm font-semibold text-brand hover:underline shrink-0">Manage profile & KYC →</a>
            </div>

            @if (! $affiliateKycSubmitted)
                <div class="mt-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">
                    <p class="font-semibold">Complete affiliate KYC to activate sharing</p>
                    <p class="mt-1 text-xs">Upload your selfie and national ID on your profile. Public verification stays disabled until our team approves your documents.</p>
                </div>
            @elseif (! $affiliateKycApproved)
                <div class="mt-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">KYC under review</p>
                    <p class="mt-1 text-xs">Your documents were submitted. Share links unlock after approval; the public verification page will show as verified once approved.</p>
                </div>
            @endif

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
                @foreach ([
                    ['Clicks', $affiliateStats['clicks']],
                    ['Registrations', $affiliateStats['registrations']],
                    ['Applications', $affiliateStats['applications']],
                    ['Commissions', format_money($affiliateStats['commissions'])],
                ] as [$label, $value])
                    <div class="rounded-xl bg-white ring-1 ring-amber-100 p-3">
                        <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="text-lg font-bold text-gray-900 mt-1">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @if ($affiliateKycApproved && $affiliateShare && $affiliateLinks)
                <div class="mt-5 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Share message</p>
                        <p class="text-sm text-gray-800 bg-white rounded-lg ring-1 ring-gray-200 p-3" id="affiliate-share-text">{{ $affiliateShare }}</p>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('affiliate-share-text').textContent)"
                                class="mt-2 text-xs font-semibold text-amber-700 hover:underline">Copy message</button>
                    </div>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <a href="{{ $affiliateLinks['registration_link'] }}" class="font-semibold text-brand hover:underline" target="_blank" rel="noopener">Registration link</a>
                        <a href="{{ $affiliateLinks['verify_link'] }}" class="font-semibold text-brand hover:underline" target="_blank" rel="noopener">Verification page</a>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($recoveryKpi ?? null)
        @include('site.vendor._recovery-kpi', ['kpi' => $recoveryKpi, 'wallet' => $recoveryWallet ?? null])
    @endif

    <section class="relative overflow-hidden rounded-3xl bg-brand text-white mb-6 shadow-lg">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <div class="relative p-6 sm:p-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">
                    {{ $catLabels[$vendor->category] ?? ucfirst(str_replace('_', ' ', (string) $vendor->category)) }}
                </p>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mt-1">Hi, {{ $vendor->name }}</h1>
                <p class="text-sm text-white/70 mt-2 font-mono">{{ $vendor->vendor_number }}</p>
                @if ($isValuer)
                    <p class="text-sm text-white/80 mt-3 max-w-lg">Accept inspection jobs, submit valuation evidence, and track payments from one place.</p>
                @endif
            </div>
            <a href="{{ route($primaryCtaRoute) }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-gold text-brand font-bold px-5 py-3 hover:bg-yellow-400 shrink-0 shadow-md">
                {{ $primaryCtaLabel }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </a>
        </div>
    </section>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach ([
            ['Assigned',        $stats['assigned'],            'text-amber-700', 'bg-amber-50 ring-amber-100'],
            ['In Progress',     $stats['in_progress'],         'text-brand', 'bg-brand-muted/50 ring-brand/10'],
            ['Done this month', $stats['completed_mo'],        'text-emerald-700', 'bg-emerald-50 ring-emerald-100'],
            ['Pending Pay',     $fmt($stats['payments_pend']), 'text-orange-700', 'bg-orange-50 ring-orange-100'],
            ['Total Earnings',  $fmt($stats['earnings']),      'text-sky-700', 'bg-sky-50 ring-sky-100'],
        ] as [$label, $value, $color, $tile])
            <div class="rounded-2xl ring-1 {{ $tile }} p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                <p class="text-xl font-extrabold {{ $color }} mt-1 tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">{{ $isValuer ? 'Upcoming jobs' : 'Upcoming tasks' }}</h2>
                <a href="{{ route('site.partner.tasks') }}" class="text-sm text-brand hover:underline font-semibold">All</a>
            </div>
            @if ($upcoming->isEmpty())
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-8 text-center">
                    <p class="text-sm text-gray-600 font-medium">No assigned or in-progress work right now.</p>
                    <p class="text-xs text-gray-500 mt-1">New jobs will appear here when allocated.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($upcoming as $t)
                        @php
                            $badge = $t->status === 'assigned'
                                ? 'bg-amber-100 text-amber-700'
                                : ($t->status === 'in_progress' ? 'bg-indigo-100 text-brand' : 'bg-gray-100 text-gray-700');
                        @endphp
                        <a href="{{ route('site.partner.task', $t) }}" class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm truncate">{{ ucfirst(str_replace('_',' ', $t->task_type)) }} · {{ $t->customer_name ?: '—' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $t->location ?: '—' }} · Due {{ $t->due_at ? $t->due_at->format('d M H:i') : 'flexible' }}</p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge }} shrink-0 ml-3">{{ str_replace('_',' ', $t->status) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-bold">Notifications</h2>
                    <a href="{{ route('site.partner.notifications') }}" class="text-sm text-brand hover:underline font-semibold">All</a>
                </div>
                @if ($notifications->isEmpty())
                    <p class="text-sm text-gray-500">No notifications yet.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($notifications as $n)
                            <li class="text-sm">
                                <p class="text-gray-900">{{ $n->message ?? $n->subject ?? 'Notification' }}</p>
                                <p class="text-xs text-gray-500">{{ $n->created_at?->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-2xl bg-gradient-to-br from-brand-muted to-white ring-1 ring-brand/15 p-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Account</p>
                <h3 class="font-bold text-gray-900 mt-1">Profile & documents</h3>
                <p class="text-xs text-gray-600 mt-1">Keep identity docs and payout details current.</p>
                <div class="mt-4 flex flex-wrap gap-3 text-sm">
                    <a href="{{ route('site.partner.profile') }}" class="font-semibold text-brand hover:underline">Open profile →</a>
                    <a href="{{ route('site.partner.documents') }}" class="font-semibold text-brand hover:underline">Documents →</a>
                    <a href="{{ route('site.partner.settings') }}" class="font-semibold text-brand hover:underline">Settings →</a>
                </div>
            </div>
        </div>
    </div>
</x-site.vendor-layout>
