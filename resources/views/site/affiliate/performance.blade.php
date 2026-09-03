<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.performance_title'))" active="performance">

    @if ($premium)
        <x-site.borrower-page-header
            :eyebrow="__('site.affiliate_portal.premium_badge')"
            :title="__('site.affiliate_portal.impact_title')"
            :subtitle="__('site.affiliate_portal.impact_subtitle')"
        />

        <section class="kf-premium-panel rounded-2xl p-6 sm:p-8 mb-6 relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.premium_badge') }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold mt-2">{{ __('site.affiliate_portal.impact_hero') }}</h2>
            </div>
        </section>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @foreach ([
                ['key' => 'visited', 'value' => $impact['visited'] ?? 0],
                ['key' => 'registered', 'value' => $impact['registered'] ?? 0],
                ['key' => 'applied', 'value' => $impact['applied'] ?? 0],
                ['key' => 'qualifying', 'value' => $impact['qualifying'] ?? 0],
                ['key' => 'earned', 'value' => format_money($impact['earned'] ?? 0)],
            ] as $metric)
                <div class="glass-card p-5">
                    <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.impact_'.$metric['key']) }}</p>
                    <p class="text-2xl font-bold tabular-nums mt-2">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>

        @if (! empty($impact['insights']))
            <section class="glass-card p-6 space-y-3">
                <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.impact_insights') }}</h2>
                @foreach ($impact['insights'] as $insight)
                    <p class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-800">{{ $insight }}</p>
                @endforeach
            </section>
        @endif
    @else
        <x-site.borrower-page-header
            :eyebrow="__('site.affiliate_portal.nav_performance')"
            :title="__('site.affiliate_portal.performance_rules_title')"
            :subtitle="__('site.affiliate_portal.performance_rules_subtitle')"
        />

        <section class="glass-card p-6 mb-6 grid md:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.assessment_period') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ __('site.affiliate_portal.every_days', ['days' => app(\App\Services\AffiliateSettingsService::class)->evaluationPeriodDays()]) }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.ramp_up') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ __('site.affiliate_portal.first_days', ['days' => $rampUpDays]) }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.next_assessment') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $nextAssessment?->format('d M Y') }}</p>
            </div>
        </section>

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

        <section class="glass-card p-6 space-y-3" x-data="{ open: null }">
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
                    @foreach ($warningLadder as $index => $step)
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
