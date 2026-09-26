<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.nav_reports'))" active="reports" :hero="false">
    <section class="kf-premium-panel rounded-3xl mb-6">
        <div class="relative p-5 sm:p-6">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ $partner_type }}</p>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ $title }}</h1>
            <p class="text-sm text-white/80 mt-1">{{ $partner_name }}</p>
            <p class="text-sm text-white/70 mt-1 font-mono">{{ $partner_number }}</p>
        </div>
    </section>

    <form method="get" action="{{ route('site.affiliate.reports') }}" class="glass-card p-4 mb-6 flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1">
            <label for="report-month" class="block text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">{{ __('site.affiliate_portal.report_month') }}</label>
            <select id="report-month" name="month" class="w-full rounded-xl border-gray-200 text-sm">
                @foreach ($months as $value)
                    <option value="{{ $value }}" @selected($value === $month)>{{ \Illuminate\Support\Carbon::parse($value.'-01')->translatedFormat('F Y') }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex justify-center bg-brand text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
            {{ __('site.affiliate_portal.view_monthly_report') }}
        </button>
    </form>

    <section class="glass-card p-5 mb-6">
        <h2 class="font-bold text-gray-900 mb-4">{{ __('site.affiliate_portal.report_activity') }}</h2>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($funnelKeys as $key)
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.funnel_'.$key) }}</p>
                    <p class="text-lg font-bold tabular-nums mt-1">
                        @if ($key === 'earned')
                            {{ format_money($activity[$key] ?? 0) }}
                        @else
                            {{ $activity[$key] ?? 0 }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </section>

    @if (! empty($kpiCard))
        <section class="glass-card p-5 mb-6">
            <h2 class="font-bold text-gray-900 mb-4">{{ __('site.affiliate_portal.report_targets') }}</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-xs text-gray-500">{{ $kpiCard['label'] }}</p>
                    <p class="text-lg font-bold tabular-nums mt-1">{{ rtrim(rtrim(number_format($kpiCard['target'], 1, '.', ''), '0'), '.') }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.funnel_qualifying') }}</p>
                    <p class="text-lg font-bold tabular-nums mt-1">{{ rtrim(rtrim(number_format($kpiCard['achieved'], 1, '.', ''), '0'), '.') }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-xs text-gray-500">%</p>
                    <p class="text-lg font-bold tabular-nums mt-1">{{ $kpiCard['percent'] }}%</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.remaining') }}</p>
                    <p class="text-lg font-bold tabular-nums mt-1">{{ rtrim(rtrim(number_format($kpiCard['remaining'], 1, '.', ''), '0'), '.') }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="glass-card p-5 mb-6">
        <h2 class="font-bold text-gray-900 mb-4">{{ __('site.affiliate_portal.report_earnings') }}</h2>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.report_qualifying_payments') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ $earnings['qualifying_payments'] }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.report_payment_value') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ format_money($earnings['payment_value']) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.funnel_earned') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ format_money($earnings['commission_earned']) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.report_withdrawn') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ format_money($earnings['withdrawn']) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.report_available') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ format_money($earnings['available']) }}</p>
            </div>
        </div>
    </section>

    <section class="glass-card p-5 mb-6">
        <h2 class="font-bold text-gray-900 mb-4">{{ __('site.affiliate_portal.report_withdrawals') }}</h2>
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.report_requested') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ $withdrawals['requested'] }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.report_processing') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ $withdrawals['processing'] }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.report_paid') }}</p>
                <p class="text-lg font-bold tabular-nums mt-1">{{ $withdrawals['paid'] }}</p>
            </div>
        </div>
    </section>

    <section class="glass-card p-5">
        <h2 class="font-bold text-gray-900 mb-3">{{ __('site.affiliate_portal.report_comparison') }}</h2>
        <p class="text-sm text-gray-700">{{ __('site.affiliate_portal.report_compare_line', [
            'month' => $comparison['previous_month'],
            'count' => $comparison['previous_qualifying'],
        ]) }}</p>
        <p class="text-sm text-gray-700 mt-1">{{ __('site.affiliate_portal.report_compare_line', [
            'month' => $comparison['current_month'],
            'count' => $comparison['current_qualifying'],
        ]) }}</p>
        @if ($comparison['delta_percent'] !== null)
            <p class="text-lg font-extrabold text-brand mt-3 tabular-nums">
                {{ ($comparison['delta_percent'] > 0 ? '+' : '').$comparison['delta_percent'] }}%
            </p>
        @endif
    </section>
</x-site.affiliate-layout>
