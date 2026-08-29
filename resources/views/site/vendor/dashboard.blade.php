<x-site.vendor-layout :title="__('site.partner_portal.dashboard_title')" active="dashboard">
    @php
        $catLabels = [
            'gps_installer' => __('site.partner_portal.category_gps'),
            'insurance' => __('site.partner_portal.category_insurance'),
            'valuer' => __('site.partner_portal.category_valuer'),
            'towing' => __('site.partner_portal.category_towing'),
            'yard' => __('site.partner_portal.category_yard'),
            'auctioneer' => __('site.partner_portal.category_auctioneer'),
            'debt_collector' => __('site.partner_portal.category_debt_collector'),
            'call_center' => __('site.partner_portal.category_call_center'),
            'legal_partner' => __('site.partner_portal.category_legal'),
            'affiliate' => __('site.partner_portal.category_affiliate'),
        ];
        $isValuer = ($vendor->category ?? null) === 'valuer' || $vendor->isValuer();
        $isInsurance = $vendor->isInsurance();
        $isGps = $vendor->isGpsInstaller();
        $isRecoveryFocused = in_array($vendor->category ?? null, ['debt_collector', 'call_center', 'legal_partner', 'auctioneer', 'gps_installer'], true)
            || array_intersect($vendor->partnerRoles(), ['debt_collector', 'call_center', 'legal_partner', 'auctioneer', 'gps_installer']) !== [];
        $primaryCtaRoute = $isRecoveryFocused ? 'site.partner.recovery-cases' : 'site.partner.tasks';
        $primaryCtaLabel = match (true) {
            $isInsurance => __('site.partner_portal.cta_cover_jobs'),
            $isValuer => __('site.partner_portal.cta_valuation_jobs'),
            $isGps => __('site.partner_portal.cta_gps_jobs'),
            $isRecoveryFocused => __('site.partner_portal.cta_recovery'),
            default => __('site.partner_portal.cta_tasks'),
        };
        $heroBlurb = match (true) {
            $isInsurance => __('site.partner_portal.hero_insurance'),
            $isValuer => __('site.partner_portal.hero_valuer'),
            $isGps => __('site.partner_portal.hero_gps'),
            $isRecoveryFocused => __('site.partner_portal.hero_recovery'),
            default => __('site.partner_portal.hero_default'),
        };
        $jobBlock = app(\App\Services\PartnerProfileService::class)->jobBlockReason($vendor);
        $membershipPayRoute = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';
        $showMembershipPayCta = $jobBlock === 'payment';
        if ($jobBlock === 'profile') {
            $primaryCtaRoute = 'site.partner.profile';
            $primaryCtaLabel = __('site.partner_portal.cta_complete_profile');
        }
        $activeStatCards = [];
        if (! $isInsurance) {
            if ((int) ($stats['assigned'] ?? 0) > 0) {
                $activeStatCards[] = [__('site.partner_portal.stat_assigned'), $stats['assigned'], 'text-amber-700', 'bg-amber-50 ring-amber-100'];
            }
            if ((int) ($stats['in_progress'] ?? 0) > 0) {
                $activeStatCards[] = [__('site.partner_portal.stat_in_progress'), $stats['in_progress'], 'text-brand', 'bg-brand-muted/50 ring-brand/10'];
            }
            if ((int) ($stats['completed_mo'] ?? 0) > 0) {
                $activeStatCards[] = [__('site.partner_portal.stat_completed_mo'), $stats['completed_mo'], 'text-emerald-700', 'bg-emerald-50 ring-emerald-100'];
            }
            if ((float) ($stats['payments_pend'] ?? 0) > 0) {
                $activeStatCards[] = [__('site.partner_portal.stat_pending_pay'), $fmt($stats['payments_pend']), 'text-orange-700', 'bg-orange-50 ring-orange-100'];
            }
            if (is_array($wallet ?? null) && array_key_exists('available', $wallet)) {
                array_unshift($activeStatCards, [
                    __('site.partner_portal.wallet_available'),
                    $fmt($wallet['available']),
                    'text-brand',
                    'bg-brand-muted/50 ring-brand/10',
                ]);
            }
            if ((float) ($stats['earnings'] ?? 0) > 0) {
                $activeStatCards[] = [__('site.partner_portal.stat_earnings'), $fmt($stats['earnings']), 'text-sky-700', 'bg-sky-50 ring-sky-100'];
            }
        }
        $insuranceAssigned = (int) ($stats['assigned'] ?? 0) + (int) ($stats['in_progress'] ?? 0);
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

    <section class="kf-premium-panel rounded-3xl mb-6">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <div class="relative p-6 sm:p-8 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">
                    {{ $catLabels[$vendor->category] ?? ucfirst(str_replace('_', ' ', (string) $vendor->category)) }}
                </p>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mt-1">{{ __('site.partner_portal.hi_name', ['name' => $vendor->name]) }}</h1>
                <p class="text-sm text-white/70 mt-2 font-mono">{{ $vendor->vendor_number }}</p>
                @if ($showMembershipPayCta)
                    @php
                        $membershipFee = app(\App\Services\PartnerMembershipService::class)->feeFor($vendor);
                    @endphp
                    <p class="text-sm text-white/90 mt-3 max-w-lg">{{ __('site.partner_portal.membership_due_title') }}</p>
                    <p class="text-sm text-white/75 mt-1 max-w-lg">{{ __('site.partner_portal.membership_due_body', ['amount' => format_money($membershipFee)]) }}</p>
                @else
                    <p class="text-sm text-white/80 mt-3 max-w-lg">{{ $heroBlurb }}</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('site.partner.profile') }}"
                       class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm">
                        {{ __('site.partner_portal.cta_my_card') }}
                    </a>
                    <a href="{{ route('site.partner.verify') }}"
                       class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white/15 text-white ring-1 ring-white/30 hover:bg-white/25">
                        {{ __('site.partner_portal.cta_verify_member') }}
                    </a>
                    @if ($showMembershipPayCta)
                        <a href="{{ route($membershipPayRoute) }}"
                           class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-brand-gold/95 text-brand hover:brightness-95 shadow-sm ring-1 ring-brand-gold/40">
                            {{ __('site.partner_portal.cta_pay_membership') }}
                        </a>
                    @else
                        <a href="{{ route($primaryCtaRoute) }}"
                           class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-brand-gold/95 text-brand hover:brightness-95 shadow-sm ring-1 ring-brand-gold/40">
                            {{ $primaryCtaLabel }}
                        </a>
                    @endif
                </div>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3 shrink-0 w-full sm:w-auto">
                @php
                    $heroWallet = $wallet ?? $recoveryWallet ?? null;
                    $heroAvailable = null;
                    if (is_array($heroWallet)) {
                        $heroAvailable = array_key_exists('available', $heroWallet)
                            ? (float) $heroWallet['available']
                            : app(\App\Services\PartnerPayoutRequestService::class)->availableBalance(
                                $vendor,
                                \App\Services\RecoveryCommissionWalletService::SOURCE_TYPE
                            );
                    }
                @endphp
                @if ($heroWallet !== null)
                    <a href="{{ ($recoveryWallet ?? null) ? route('site.partner.recovery-wallet') : route('site.partner.payments') }}"
                       class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4 min-w-[12rem]">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.partner_portal.wallet_available') }}</p>
                        <p class="text-2xl font-extrabold tabular-nums mt-1">{{ format_money($heroAvailable ?? 0) }}</p>
                        <p class="text-xs text-white/70 mt-1">{{ __('site.partner_portal.wallet_withdraw_hint') }}</p>
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if ($isInsurance)
        @php
            $insWallet = is_array($wallet ?? null) ? $wallet : [];
            $insAvailable = array_key_exists('available', $insWallet)
                ? (float) $insWallet['available']
                : (float) ($heroAvailable ?? 0);
            $insPending = (float) ($stats['payments_pend'] ?? ($insWallet['pending'] ?? 0));
            $insCompleted = (int) ($stats['completed_mo'] ?? 0);
            $insOpenJobs = $insuranceAssigned;
        @endphp
        <section class="mb-6 overflow-hidden rounded-3xl ring-1 ring-brand/15 bg-white shadow-sm">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-brand/10">
                <a href="{{ route('site.partner.payments') }}" class="group p-5 sm:p-6 hover:bg-brand-muted/30 transition-colors">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('site.partner_portal.wallet_available') }}</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-2 tabular-nums tracking-tight">{{ format_money($insAvailable) }}</p>
                    <p class="text-xs text-gray-500 mt-1.5 group-hover:text-brand font-semibold">{{ __('site.partner_portal.wallet_withdraw_hint') }} →</p>
                </a>
                <div class="p-5 sm:p-6 bg-gradient-to-br from-brand-gold/15 to-white">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-bold">{{ __('site.partner_portal.stat_pending_pay') }}</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-2 tabular-nums tracking-tight">{{ format_money($insPending) }}</p>
                    <p class="text-xs text-gray-500 mt-1.5">{{ __('site.partner_portal.insurance_pending_hint') }}</p>
                </div>
                <div class="p-5 sm:p-6">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-bold">{{ __('site.partner_portal.stat_completed_mo') }}</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-2 tabular-nums tracking-tight">{{ $insCompleted }}</p>
                    <p class="text-xs text-gray-500 mt-1.5">{{ __('site.partner_portal.insurance_completed_hint') }}</p>
                </div>
                <a href="{{ route('site.partner.tasks') }}" data-kf-motion="tab" class="group p-5 sm:p-6 hover:bg-brand-muted/30 transition-colors">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('site.partner_portal.stat_open_cover') }}</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-2 tabular-nums tracking-tight">{{ $insOpenJobs }}</p>
                    <p class="text-xs text-gray-500 mt-1.5 group-hover:text-brand font-semibold">{{ __('site.partner_portal.cta_cover_jobs') }} →</p>
                </a>
            </div>
        </section>
    @elseif (count($activeStatCards) > 0)
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            @foreach ($activeStatCards as [$label, $value, $color, $tile])
                @if ($label === __('site.partner_portal.wallet_available'))
                    <a href="{{ route('site.partner.payments') }}" class="rounded-2xl ring-1 {{ $tile }} p-4 hover:brightness-95">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                        <p class="text-xl font-extrabold {{ $color }} mt-1 tabular-nums">{{ $value }}</p>
                        <p class="text-[11px] text-gray-500 mt-1 font-semibold">{{ __('site.partner_portal.wallet_withdraw_hint') }} →</p>
                    </a>
                @else
                    <div class="rounded-2xl ring-1 {{ $tile }} p-4">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                        <p class="text-xl font-extrabold {{ $color }} mt-1 tabular-nums">{{ $value }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">
                    @if ($isInsurance)
                        {{ __('site.partner_portal.upcoming_cover_jobs') }}
                    @elseif ($isValuer || $isGps)
                        {{ __('site.partner_portal.upcoming_jobs') }}
                    @else
                        {{ __('site.partner_portal.upcoming_tasks') }}
                    @endif
                </h2>
                <a href="{{ route('site.partner.tasks') }}" data-kf-motion="tab" class="text-sm text-brand hover:underline font-semibold">{{ __('site.partner_portal.all') }}</a>
            </div>
            @if ($upcoming->isEmpty())
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-8 text-center">
                    <p class="text-sm text-gray-700 font-semibold">{{ __('site.partner_portal.no_assigned_tasks') }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('site.partner_portal.no_assigned_tasks_hint') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($upcoming as $t)
                        @php
                            $badge = $t->status === 'assigned'
                                ? 'bg-amber-100 text-amber-700'
                                : ($t->status === 'in_progress' ? 'bg-indigo-100 text-brand' : 'bg-gray-100 text-gray-700');
                        @endphp
                        <a href="{{ route('site.partner.task', $t) }}" data-kf-share="kf-task-{{ $t->id }}" class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm truncate">{{ ucfirst(str_replace('_',' ', $t->task_type)) }} · {{ $t->customer_name ?: '—' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $t->location ?: '—' }} · {{ __('site.partner_portal.due') }} {{ $t->due_at ? $t->due_at->format('d M H:i') : __('site.partner_portal.flexible') }}</p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge }} shrink-0 ml-3">{{ str_replace('_',' ', $t->status) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-6">
            @if ($isInsurance)
                <div class="overflow-hidden rounded-2xl ring-1 ring-brand/15 bg-brand text-white shadow-md">
                    <div class="p-5">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.partner_portal.account') }}</p>
                        <h3 class="font-extrabold text-lg mt-1">{{ __('site.partner_portal.profile_documents') }}</h3>
                        <p class="text-xs text-white/70 mt-1.5 leading-relaxed">{{ __('site.partner_portal.profile_documents_hint') }}</p>
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-white/10 border-t border-white/10 bg-black/10">
                        <a href="{{ route('site.partner.profile') }}" class="px-3 py-3.5 text-center text-xs font-bold hover:bg-white/10 transition-colors">
                            {{ __('site.partner_portal.nav_profile') }}
                        </a>
                        <a href="{{ route('site.partner.documents') }}" class="px-3 py-3.5 text-center text-xs font-bold hover:bg-white/10 transition-colors">
                            {{ __('site.partner_portal.nav_documents') }}
                        </a>
                        <a href="{{ route('site.partner.settings') }}" class="px-3 py-3.5 text-center text-xs font-bold hover:bg-white/10 transition-colors">
                            {{ __('site.partner_portal.nav_settings') }}
                        </a>
                    </div>
                </div>
            @else
                <div class="rounded-2xl bg-gradient-to-br from-brand-muted to-white ring-1 ring-brand/15 p-5">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_portal.account') }}</p>
                    <h3 class="font-bold text-gray-900 mt-1">{{ __('site.partner_portal.profile_documents') }}</h3>
                    <p class="text-xs text-gray-600 mt-1">{{ __('site.partner_portal.profile_documents_hint') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                        <a href="{{ route('site.partner.profile') }}" class="font-semibold text-brand hover:underline">{{ __('site.partner_portal.open_profile') }}</a>
                        <a href="{{ route('site.partner.documents') }}" class="font-semibold text-brand hover:underline">{{ __('site.partner_portal.open_documents') }}</a>
                        <a href="{{ route('site.partner.settings') }}" class="font-semibold text-brand hover:underline">{{ __('site.partner_portal.open_settings') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-site.vendor-layout>
