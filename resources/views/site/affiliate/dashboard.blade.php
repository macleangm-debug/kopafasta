<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.dashboard_title'))" active="dashboard">

    @php
        $eligibility = $eligibility ?? app(\App\Services\AffiliateEligibilityService::class)->for($vendor);
        $sharingUnlocked = $eligibility['can_share'] ?? false;
        $kycApproved = in_array($vendor->affiliate_kyc_status, ['verified', 'approved'], true);
        $code = $links['affiliate_code'] ?? $vendor->affiliate_code;
        $regLink = $links['registration_link'] ?? '#';
        $membership = $membership ?? app(\App\Services\AffiliateMembershipService::class)->summary($vendor);
        $standing = $standing ?? app(\App\Services\AffiliateEvaluationService::class)->currentStanding($vendor);
        $membershipDays = $vendor->membership_expires_at
            ? max(0, (int) now()->startOfDay()->diffInDays($vendor->membership_expires_at->copy()->startOfDay(), false))
            : 0;
        $hero = [
            'variant' => $sharingUnlocked ? 'applications' : 'guarantor_request',
            'greeting' => $vendor->name,
            'membership_no' => $vendor->partner_number ?? null,
            'title' => __('site.affiliate_portal.welcome'),
            'subtitle' => $sharingUnlocked
                ? __('site.affiliate_portal.banner_verified')
                : (__('site.affiliate_portal.eligibility_blocked')),
            'cta_label' => $sharingUnlocked
                ? __('site.affiliate_portal.nav_referrals')
                : (in_array('terms_unaccepted', $eligibility['reasons'] ?? [], true)
                    ? __('affiliate_terms.accept_button')
                    : (! ($membership['active'] ?? false)
                        ? __('site.affiliate_portal.membership_pay')
                        : __('site.affiliate_portal.complete_kyc'))),
            'cta_url' => $sharingUnlocked
                ? route('site.affiliate.referrals')
                : (in_array('terms_unaccepted', $eligibility['reasons'] ?? [], true)
                    ? route('site.affiliate.terms')
                    : (! ($membership['active'] ?? false)
                        ? route('site.affiliate.membership.pay')
                        : route('site.affiliate.profile'))),
            'secondary_cta_label' => ($sharingUnlocked && $code) ? __('site.affiliate_portal.verified_badge') : null,
            'secondary_cta_url' => ($sharingUnlocked && $code) ? route('site.affiliate.verify', $code) : null,
        ];
    @endphp

    <x-site.borrower-dashboard-hero :hero="$hero" />

    <div class="glass-card p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.membership_status') }}</p>
                <p class="text-lg font-bold text-gray-900">{{ $membership['label'] ?? '—' }}
                    @if (($membership['active'] ?? false) && $membershipDays > 0)
                        · {{ __('site.affiliate_portal.membership_days', ['days' => $membershipDays]) }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('site.affiliate.terms') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.view_terms') }}</a>
                <a href="{{ route('site.affiliate.terms') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.view_policy') }}</a>
            </div>
        </div>
    </div>

    @if ($standing ?? null)
        <div class="glass-card p-6 mb-8 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.performance_title') }}</p>
                    <p class="text-lg font-bold text-gray-900">{{ $standing['status_label'] }} · {{ number_format((float) $standing['score'], 0) }}/100</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $standing['period_start']->format('d M Y') }} – {{ $standing['period_end']->format('d M Y') }}
                    </p>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ($standing['kpi_results'] as $kpi)
                    @if ($kpi['enabled'])
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                            <p class="text-xs text-gray-500">{{ $kpi['label'] }}</p>
                            @if ($standing['premium'] ?? false)
                                <p class="text-lg font-bold tabular-nums">{{ $kpi['key'] === 'conversion' ? number_format($kpi['actual'], 1).'%' : number_format($kpi['actual'], 0) }}</p>
                            @else
                                <p class="text-lg font-bold tabular-nums">{{ $kpi['key'] === 'conversion' ? number_format($kpi['actual'], 1).'%' : number_format($kpi['actual'], 0) }}
                                    <span class="text-sm font-medium text-gray-500">/ {{ $kpi['key'] === 'conversion' ? number_format($kpi['target'], 0).'%' : number_format($kpi['target'], 0) }}</span>
                                    <span class="text-sm">{{ $kpi['met'] ? '✓' : '' }}</span>
                                </p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
            @if (! empty($standing['next_action']))
                <p class="text-sm text-gray-700">{{ $standing['next_action'] }}</p>
            @endif
        </div>
    @endif

    @if ($sharingUnlocked && $code)
        <section class="mb-8 kf-premium-panel rounded-2xl p-6 sm:p-8">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.promo_code') }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold mt-1 font-mono tracking-wide">{{ $code }}</h2>
                    <p class="text-sm text-white/80 mt-2">{{ __('site.affiliate_portal.promo_hint') }}</p>
                    <p class="text-sm text-white/80 mt-1">{{ __('site.affiliate_portal.wallet_snapshot') }}:
                        <span class="font-bold text-brand-gold">{{ format_money($wallet['approved'] ?? 0) }}</span>
                        <span class="text-white/60">({{ __('site.affiliate_portal.approved') }})</span>
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 shrink-0">
                    @if ($share)
                        <x-site.referral-share :link="$regLink" :code="$code" :message="$share" />
                    @endif
                    <a href="{{ route('site.affiliate.wallet') }}"
                       class="inline-flex justify-center items-center bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-xl text-sm ring-1 ring-white/20">
                        {{ __('site.affiliate_portal.view_wallet') }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        @foreach ([
            [__('site.affiliate_portal.stat_clicks'), $stats['clicks'] ?? 0, 'text-gray-900'],
            [__('site.affiliate_portal.stat_registrations'), $stats['registrations'] ?? 0, 'text-gray-900'],
            [__('site.affiliate_portal.stat_applications'), $stats['applications'] ?? 0, 'text-gray-900'],
            [__('site.affiliate_portal.stat_commissions'), format_money($stats['commissions'] ?? 0), 'text-brand'],
        ] as [$label, $value, $color])
            <div class="glass-card p-4">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                <p class="text-xl font-bold mt-1 tabular-nums {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="glass-card p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.share_message') }}</h2>
            @if ($sharingUnlocked && $share)
                <p class="text-sm text-gray-800 bg-gray-50 rounded-xl p-4 ring-1 ring-gray-100 whitespace-pre-line">{{ $share }}</p>
                <a href="{{ $regLink }}" target="_blank" rel="noopener"
                   class="inline-flex text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.registration_link') }} →</a>
            @else
                <p class="text-sm text-gray-500">{{ ! $sharingUnlocked ? __('site.affiliate_portal.eligibility_blocked') : __('site.affiliate_portal.kyc_pending_body') }}</p>
                <a href="{{ route('site.affiliate.membership.pay') }}" class="inline-flex text-sm font-semibold text-brand hover:underline">
                    {{ __('site.affiliate_portal.membership_pay') }} →
                </a>
            @endif
        </div>

        <div class="glass-card p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.wallet_snapshot') }}</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('site.affiliate_portal.pending') }}</dt><dd class="font-semibold tabular-nums">{{ format_money($wallet['pending'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('site.affiliate_portal.approved') }}</dt><dd class="font-semibold text-emerald-700 tabular-nums">{{ format_money($wallet['approved'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('site.affiliate_portal.paid') }}</dt><dd class="font-semibold tabular-nums">{{ format_money($wallet['paid'] ?? 0) }}</dd></div>
            </dl>
            <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.min_payout_note', ['amount' => format_money($minPayout)]) }}</p>
            <a href="{{ route('site.affiliate.wallet') }}" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.view_wallet') }}</a>
        </div>
    </div>

</x-site.affiliate-layout>
