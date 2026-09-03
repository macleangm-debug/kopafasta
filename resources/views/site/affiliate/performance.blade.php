<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.performance_title'))" active="performance">

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
                        @unless ($standing['premium'] ?? false)
                            <span class="text-base font-medium text-gray-500">/ {{ $kpi['key'] === 'conversion' ? number_format($kpi['target'], 0).'%' : number_format($kpi['target'], 0) }}</span>
                        @endunless
                    </p>
                </div>
            @endif
        @endforeach
    </div>

    <section class="glass-card p-6 mb-6 space-y-4">
        <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.miss_target_title') }}</h2>
        @foreach ($warningLadder as $step)
            <div class="flex items-center justify-between gap-4 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100 text-sm">
                <span class="font-semibold text-gray-900">{{ $step['label'] }}</span>
                <span class="text-gray-600">{{ __('site.affiliate_portal.after_periods', ['count' => $step['periods']]) }}</span>
            </div>
        @endforeach
    </section>

    <section class="glass-card p-6">
        <h2 class="text-lg font-bold text-gray-900">{{ __('site.affiliate_portal.recovery_title') }}</h2>
        <p class="text-sm text-gray-700 mt-2">{{ $recovery }}</p>
    </section>

</x-site.affiliate-layout>
