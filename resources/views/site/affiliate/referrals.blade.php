<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.referrals_title'))" active="referrals">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.nav_referrals')"
        :title="__('site.affiliate_portal.referrals_title')"
        :subtitle="__('site.affiliate_portal.referrals_pipeline_subtitle')"
    />

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        @foreach ([
            'visited' => __('site.affiliate_portal.funnel_visited'),
            'registered' => __('site.affiliate_portal.funnel_registered'),
            'applied' => __('site.affiliate_portal.funnel_applied'),
            'approved' => __('site.affiliate_portal.funnel_approved'),
            'qualifying' => __('site.affiliate_portal.funnel_qualifying'),
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
        <div class="space-y-3">
            @foreach ($pipeline as $referral)
                <div class="glass-card p-4 flex items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $referral['name'] }}</p>
                        <p class="text-sm text-brand font-medium mt-0.5">{{ $referral['stage'] }}</p>
                    </div>
                    <p class="text-sm text-gray-500">{{ $referral['date']?->format('d M Y') }}</p>
                </div>
            @endforeach
        </div>
    @endif

</x-site.affiliate-layout>
