<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.dashboard_title'))" active="dashboard">

    @php
        $kycApproved = in_array($vendor->affiliate_kyc_status, ['verified', 'approved'], true);
        $code = $links['affiliate_code'] ?? $vendor->affiliate_code;
    @endphp

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.affiliate_portal.welcome') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">{{ $vendor->name }}</h1>
        <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.dashboard_subtitle') }}</p>
    </div>

    @unless ($kycApproved)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50/80 p-5">
            <p class="font-semibold text-amber-900">{{ __('site.affiliate_portal.kyc_pending_title') }}</p>
            <p class="text-sm text-amber-800 mt-1">{{ __('site.affiliate_portal.kyc_pending_body') }}</p>
            <a href="{{ route('site.affiliate.profile') }}" class="inline-flex mt-3 text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.complete_kyc') }} →</a>
        </div>
    @endunless

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ([
            [__('site.affiliate_portal.stat_clicks'), $stats['clicks'] ?? 0],
            [__('site.affiliate_portal.stat_registrations'), $stats['registrations'] ?? 0],
            [__('site.affiliate_portal.stat_applications'), $stats['applications'] ?? 0],
            [__('site.affiliate_portal.stat_commissions'), format_money($stats['commissions'] ?? 0)],
        ] as [$label, $value])
            <div class="glass-card p-4">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                <p class="text-xl font-bold text-gray-900 mt-1 tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="glass-card p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.promo_code') }}</h2>
            <p class="font-mono text-2xl font-bold text-brand tracking-wide">{{ $code }}</p>
            <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.promo_hint') }}</p>
            <a href="{{ route('site.affiliate.profile') }}" class="inline-flex text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.personalize_code') }} →</a>

            @if ($kycApproved && $share)
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-xs font-medium text-gray-500 mb-2">{{ __('site.affiliate_portal.share_message') }}</p>
                    <p class="text-sm text-gray-800 bg-gray-50 rounded-xl p-3 ring-1 ring-gray-100" id="aff-share">{{ $share }}</p>
                    <div class="flex flex-wrap gap-3 mt-3">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('aff-share').textContent)"
                                class="text-xs font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.copy_message') }}</button>
                        <a href="{{ $links['registration_link'] ?? '#' }}" target="_blank" rel="noopener" class="text-xs font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.registration_link') }}</a>
                    </div>
                </div>
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
            <a href="{{ route('site.affiliate.wallet') }}" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('site.affiliate_portal.view_wallet') }}</a>
        </div>
    </div>

</x-site.affiliate-layout>
