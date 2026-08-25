<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.dashboard_title'))" active="dashboard">

    @php
        $kycApproved = in_array($vendor->affiliate_kyc_status, ['verified', 'approved'], true);
        $code = $links['affiliate_code'] ?? $vendor->affiliate_code;
        $regLink = $links['registration_link'] ?? '#';
        $membershipOk = app(\App\Services\AffiliateMembershipService::class)->isSharingAllowed($vendor);
        $sharingUnlocked = $kycApproved && $membershipOk;
        $hero = [
            'variant' => $sharingUnlocked ? 'applications' : 'guarantor_request',
            'greeting' => $vendor->name,
            'membership_no' => $vendor->partner_number ?? null,
            'title' => __('site.affiliate_portal.welcome'),
            'subtitle' => $sharingUnlocked
                ? __('site.affiliate_portal.banner_verified')
                : (! $membershipOk
                    ? __('site.affiliate_portal.membership_subtitle')
                    : __('site.affiliate_portal.banner_pending')),
            'cta_label' => $sharingUnlocked
                ? __('site.affiliate_portal.nav_referrals')
                : (! $membershipOk
                    ? __('site.affiliate_portal.membership_pay')
                    : __('site.affiliate_portal.complete_kyc')),
            'cta_url' => $sharingUnlocked
                ? route('site.affiliate.referrals')
                : (! $membershipOk
                    ? route('site.affiliate.membership.pay')
                    : route('site.affiliate.profile')),
            'secondary_cta_label' => ($sharingUnlocked && $code) ? __('site.affiliate_portal.verified_badge') : null,
            'secondary_cta_url' => ($sharingUnlocked && $code) ? route('site.affiliate.verify', $code) : null,
        ];
    @endphp

    <x-site.borrower-dashboard-hero :hero="$hero" />

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
                <p class="text-sm text-gray-500">{{ ! $membershipOk ? __('site.affiliate_portal.membership_subtitle') : __('site.affiliate_portal.kyc_pending_body') }}</p>
                <a href="{{ ! $membershipOk ? route('site.affiliate.membership.pay') : route('site.affiliate.profile') }}" class="inline-flex text-sm font-semibold text-brand hover:underline">
                    {{ ! $membershipOk ? __('site.affiliate_portal.membership_pay') : __('site.affiliate_portal.complete_kyc') }} →
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
