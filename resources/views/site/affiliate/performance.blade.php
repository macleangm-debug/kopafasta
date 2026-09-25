<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.impact_title'))" active="performance" :hero="false">
    @php
        $funnelKeys = $funnelKeys ?? app(\App\Services\AffiliatePortalPresenter::class)->visibleFunnelKeys();
        $pipeline = $pipeline ?? collect();
    @endphp

    <section class="kf-premium-panel rounded-2xl p-6 sm:p-8 mb-6 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative">
            @if ($premium)
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.premium_badge') }}</p>
            @endif
            <h1 class="text-2xl sm:text-3xl font-bold mt-2">{{ __('site.affiliate_portal.impact_hero') }}</h1>
            <p class="text-sm text-white/80 mt-2 max-w-2xl">{{ $standing['status_label'] ?? '' }}</p>
        </div>
    </section>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        @foreach ($funnelKeys as $key)
            <div class="glass-card p-4">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('site.affiliate_portal.funnel_'.$key) }}</p>
                <p class="text-2xl font-bold mt-1 tabular-nums">{{ $funnel[$key] ?? 0 }}</p>
            </div>
        @endforeach
        @if ($premium)
            <div class="glass-card p-4">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('site.affiliate_portal.impact_earned') }}</p>
                <p class="text-2xl font-bold mt-1 tabular-nums">{{ format_money($impact['earned'] ?? 0) }}</p>
            </div>
        @endif
    </div>

    @unless ($premium)
        <div class="grid sm:grid-cols-2 gap-4 mb-6">
            @foreach ($standing['kpi_results'] ?? [] as $kpi)
                @if ($kpi['enabled'] ?? false)
                    <div class="glass-card p-5">
                        <p class="text-xs uppercase tracking-widest text-gray-500">{{ $kpi['label'] }}</p>
                        <p class="text-2xl font-bold tabular-nums mt-2">
                            {{ $kpi['key'] === 'conversion' ? number_format($kpi['actual'], 1).'%' : number_format($kpi['actual'], 0) }}
                            <span class="text-base font-medium text-gray-500">/ {{ $kpi['key'] === 'conversion' ? number_format($kpi['target'], 0).'%' : number_format($kpi['target'], 0) }}</span>
                        </p>
                    </div>
                @endif
            @endforeach
        </div>
    @endunless

    @if ($pipeline->isEmpty())
        <x-site.empty-state
            class="mb-6"
            icon="👥"
            :title="__('site.affiliate_portal.no_referrals_title')"
            :description="__('site.affiliate_portal.no_referrals_body')"
            :action-label="__('site.affiliate_portal.nav_share')"
            :action-url="route('site.affiliate.share')"
        />
    @else
        <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white mb-6">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5">
                <h2 class="font-bold text-white">{{ __('site.affiliate_portal.recent_referrals') }}</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($pipeline as $referral)
                    <div class="px-4 sm:px-5 py-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $referral['name'] }}</p>
                            <p class="text-sm text-brand font-medium mt-0.5">{{ $referral['stage'] }}</p>
                        </div>
                        <p class="text-sm text-gray-500">{{ $referral['date']?->format('d M Y') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($premium)
        @if (! empty($impact['insights']))
            <section class="glass-card p-6 space-y-3">
                <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.impact_insights') }}</h2>
                @foreach ($impact['insights'] as $insight)
                    <p class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-800">{{ $insight }}</p>
                @endforeach
            </section>
        @endif
    @else
        <section class="glass-card p-6 space-y-3">
            <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.performance_help_title') }}</h2>
            <details class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 group">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-semibold text-gray-900">
                    <span>{{ __('site.affiliate_portal.faq_assessed') }}</span>
                    <span class="text-gray-400 group-open:rotate-180 transition">⌄</span>
                </summary>
                <p class="text-sm text-gray-700 mt-3">{{ $assessmentExplanation }}</p>
            </details>
            <details class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 group">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-semibold text-gray-900">
                    <span>{{ __('site.affiliate_portal.faq_miss_target') }}</span>
                    <span class="text-gray-400 group-open:rotate-180 transition">⌄</span>
                </summary>
                <div class="mt-3 space-y-2">
                    @foreach ($warningLadder as $step)
                        <p class="text-sm text-gray-700">
                            <span class="font-semibold">{{ __('site.affiliate_portal.miss_step', ['n' => $step['periods']]) }}</span>
                            → {{ $step['label'] }}
                        </p>
                    @endforeach
                </div>
            </details>
            <details class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 group">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm font-semibold text-gray-900">
                    <span>{{ __('site.affiliate_portal.faq_good_standing') }}</span>
                    <span class="text-gray-400 group-open:rotate-180 transition">⌄</span>
                </summary>
                <p class="text-sm text-gray-700 mt-3">{{ $recovery }}</p>
            </details>
        </section>
    @endif
</x-site.affiliate-layout>
