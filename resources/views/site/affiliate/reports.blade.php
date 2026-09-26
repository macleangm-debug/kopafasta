<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.nav_reports'))" active="reports" :hero="false">
    <section class="kf-premium-panel rounded-3xl mb-6" x-data="{ monthSheet: false }">
        <div class="relative p-5 sm:p-6">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ $partner_type }}</p>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ $title }}</h1>
            <p class="text-sm text-white/80 mt-1">{{ $partner_name }}</p>
            <p class="text-sm text-white/70 mt-1 font-mono">{{ $partner_number }}</p>

            <form method="get" action="{{ route('site.affiliate.reports') }}" class="hidden lg:flex items-center gap-3 mt-5">
                <label for="report-month" class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.report_month') }}</label>
                <select id="report-month" name="month" onchange="this.form.submit()"
                        class="rounded-xl border-0 bg-white/10 text-white text-sm ring-1 ring-white/25 px-3 py-2">
                    @foreach ($months as $value)
                        <option value="{{ $value }}" @selected($value === $month) class="text-gray-900">{{ \Illuminate\Support\Carbon::parse($value.'-01')->translatedFormat('F Y') }}</option>
                    @endforeach
                </select>
            </form>

            <button type="button" class="lg:hidden mt-5 inline-flex items-center gap-2 rounded-xl bg-white/10 ring-1 ring-white/25 px-3 py-2 text-sm font-semibold text-white"
                    @click="monthSheet = true">
                <span class="text-[10px] uppercase tracking-widest text-brand-gold">{{ __('site.affiliate_portal.report_month') }}</span>
                <span>{{ $month_label }}</span>
            </button>
            <x-site.bottom-sheet :title="__('site.affiliate_portal.report_month')" open="monthSheet">
                <div class="space-y-1">
                    @foreach ($months as $value)
                        <a href="{{ route('site.affiliate.reports', ['month' => $value]) }}"
                           class="block rounded-xl px-4 py-3 text-sm font-semibold {{ $value === $month ? 'bg-brand text-white' : 'text-gray-800 hover:bg-gray-50' }}">
                            {{ \Illuminate\Support\Carbon::parse($value.'-01')->translatedFormat('F Y') }}
                        </a>
                    @endforeach
                </div>
            </x-site.bottom-sheet>
        </div>
    </section>

    <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white mb-6">
        <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5">
            <h2 class="font-bold text-white">{{ __('site.affiliate_portal.report_activity') }}</h2>
        </div>
        <div class="p-4 sm:p-5 grid grid-cols-2 {{ count($funnelKeys) === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4' }} gap-3">
            @foreach ($funnelKeys as $key)
                <div class="rounded-xl bg-brand/[0.04] ring-1 ring-brand/10 px-4 py-4 min-h-[6.5rem] h-full">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('site.affiliate_portal.funnel_'.$key) }}</p>
                    <p class="text-xl font-extrabold tabular-nums mt-1">{{ $activity[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white mb-6">
        <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5">
            <h2 class="font-bold text-white">{{ __('site.affiliate_portal.report_earnings') }}</h2>
        </div>
        <div class="p-4 sm:p-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-brand/[0.04] ring-1 ring-brand/10 px-4 py-4 min-h-[6.5rem] h-full">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('site.affiliate_portal.funnel_earned') }}</p>
                <p class="text-xl font-extrabold tabular-nums mt-1">{{ format_money($earnings['commission_earned']) }}</p>
            </div>
            <div class="rounded-xl bg-brand/[0.04] ring-1 ring-brand/10 px-4 py-4 min-h-[6.5rem] h-full">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('site.affiliate_portal.report_withdrawn') }}</p>
                <p class="text-xl font-extrabold tabular-nums mt-1">{{ format_money($earnings['withdrawn']) }}</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white">
        <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5">
            <h2 class="font-bold text-white">{{ __('site.affiliate_portal.report_comparison') }}</h2>
        </div>
        <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="rounded-xl bg-brand/[0.04] ring-1 ring-brand/10 px-4 py-4 min-h-[6.5rem] h-full">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $comparison['previous_label'] ?? $comparison['previous_month'] }}</p>
                <p class="text-xl font-extrabold tabular-nums mt-1">{{ $comparison['previous_registered'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('site.affiliate_portal.funnel_paying') }}</p>
            </div>
            <div class="rounded-xl bg-brand/[0.04] ring-1 ring-brand/10 px-4 py-4 min-h-[6.5rem] h-full">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $comparison['current_label'] ?? $comparison['current_month'] }}</p>
                <p class="text-xl font-extrabold tabular-nums mt-1">{{ $comparison['current_registered'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('site.affiliate_portal.funnel_paying') }}</p>
                @if ($comparison['delta_percent'] !== null)
                    <p class="text-sm font-extrabold text-brand mt-2 tabular-nums">
                        {{ ($comparison['delta_percent'] > 0 ? '+' : '').$comparison['delta_percent'] }}%
                    </p>
                @endif
            </div>
        </div>
    </section>
</x-site.affiliate-layout>
