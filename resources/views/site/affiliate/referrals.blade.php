<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.referrals_title'))" active="referrals">

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        @foreach ([
            'visited' => __('site.affiliate_portal.funnel_visited'),
            'registered' => __('site.affiliate_portal.funnel_registered'),
            'applied' => __('site.affiliate_portal.funnel_applied'),
            'commission' => __('site.affiliate_portal.funnel_commission'),
        ] as $key => $label)
            <div class="glass-card p-4">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold mt-1 tabular-nums">{{ $funnel[$key] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    @if ($pipeline->isEmpty())
        <x-site.empty-state
            icon="👥"
            :title="__('site.affiliate_portal.no_referrals_title')"
            :description="__('site.affiliate_portal.no_referrals_body')"
            :action-label="__('site.affiliate_portal.nav_share')"
            :action-url="route('site.affiliate.share')"
        />
    @else
        <div class="space-y-2">
            @foreach ($pipeline as $referral)
                <div class="rounded-xl bg-white ring-1 ring-brand/10 px-3.5 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-gray-900 font-mono text-sm">{{ $referral['member_no'] ?: '—' }}</p>
                        <p class="tabular-nums text-sm font-bold text-gray-900 shrink-0">{{ format_money($referral['commission_amount'] ?? 0) }}</p>
                    </div>
                    <div class="flex items-start justify-between gap-3 mt-1">
                        <p class="text-xs text-gray-500">{{ $referral['source'] ?? '' }} · {{ $referral['date']?->format('d M Y') }}</p>
                        <p class="text-xs font-semibold text-brand shrink-0">{{ $referral['status_label'] ?? $referral['stage'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-site.affiliate-layout>
