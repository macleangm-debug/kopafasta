<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="dashboard">
    @php
        $money = $report['money'];
        $biz = $report['business'];
        $left = (float) $money['in'] - (float) $money['out'];
    @endphp
    <div class="space-y-5" @if($print ?? false) x-init="setTimeout(() => window.print(), 300)" @endif>
        <div class="print:hidden space-y-5">
            <x-site.plus-nav />
            <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.reports.title', ['period' => $report['label']])" :body="__('plus.reports.hero_body')">
                <div class="flex flex-wrap gap-2">
                    @foreach (['week' => __('plus.reports.week'), 'month' => __('plus.reports.month'), 'year' => __('plus.reports.year')] as $key => $label)
                        <a href="{{ route('site.borrower.plus.reports', ['period' => $key]) }}"
                           class="rounded-full px-3 py-1.5 text-sm font-semibold ring-1 {{ $period === $key ? 'bg-brand-gold text-brand ring-brand-gold' : 'bg-white/10 text-white ring-white/20' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </x-site.plus-hero>
        </div>

        <div class="kf-print-sheet max-w-3xl mx-auto rounded-2xl bg-white ring-1 ring-brand/15 overflow-hidden">
            <div class="bg-brand text-white px-5 sm:px-8 py-5 flex items-start justify-between gap-4">
                <div>
                    <x-site.brand-mark size="sm" variant="light" />
                    <p class="mt-3 text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus</p>
                    <h2 class="text-xl font-extrabold mt-1">{{ __('plus.reports.title', ['period' => $report['label']]) }}</h2>
                </div>
                <div class="text-right text-sm text-white/80">
                    <p>{{ __('plus.reports.issued') }}</p>
                    <p class="font-semibold text-white">{{ now()->locale(app()->getLocale())->isoFormat('D MMM YYYY') }}</p>
                    @if (! empty($customer->member_no))
                        <p class="mt-1 text-xs">{{ __('plus.card.member', ['id' => $customer->member_no]) }}</p>
                    @endif
                </div>
            </div>

            <div class="p-5 sm:p-8">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-4">{{ __('plus.reports.summary') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-brand/5 ring-1 ring-brand/10 p-4">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('plus.reports.money') }} 💸</p>
                        <p class="text-sm mt-3">{{ __('plus.reports.money_in', ['amount' => format_money($money['in'])]) }}</p>
                        <p class="text-sm">{{ __('plus.reports.money_out', ['amount' => format_money($money['out'])]) }}</p>
                        <p class="mt-2 text-lg font-extrabold tabular-nums {{ $left < 0 ? 'text-red-700' : 'text-brand' }}">{{ __('plus.reports.money_left', ['amount' => format_money($left)]) }}</p>
                    </div>
                    <div class="rounded-2xl bg-brand/5 ring-1 ring-brand/10 p-4">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('plus.reports.business') }} 🏪</p>
                        <p class="text-sm mt-3">{{ __('plus.business.sold') }} {{ format_money($biz['sold']) }}</p>
                        <p class="text-sm">{{ __('plus.business.spent') }} {{ format_money($biz['spent']) }}</p>
                        <p class="mt-2 text-lg font-extrabold tabular-nums text-brand">{{ __('plus.business.diff') }} {{ format_money($biz['difference']) }}</p>
                    </div>
                    <div class="rounded-2xl bg-brand/5 ring-1 ring-brand/10 p-4">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('plus.reports.goals') }} 🎯</p>
                        <p class="mt-3 text-lg font-extrabold text-gray-900">{{ __('plus.reports.goals_line', ['done' => $report['goals_done'], 'total' => $report['goals_total']]) }}</p>
                        @if (($report['goals_added'] ?? 0) > 0)
                            <p class="text-sm text-gray-600 mt-1">{{ __('plus.reports.goals_added', ['amount' => format_money($report['goals_added'])]) }}</p>
                        @endif
                    </div>
                    <div class="rounded-2xl bg-brand/5 ring-1 ring-brand/10 p-4">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('plus.reports.trust') }} ✦</p>
                        <p class="mt-3 text-lg font-extrabold text-gray-900">{{ strtoupper($grade) }} · {{ $trust['percent'] ?? 0 }} — {{ $trust['label'] ?? '' }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ __('plus.reports.trust_ok') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('site.borrower.plus.reports', ['period' => $period, 'print' => 1]) }}"
           class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold print:hidden">{{ __('plus.reports.print') }}</a>
        <p class="text-xs text-gray-500 print:hidden">{{ __('plus.reports.print_goes') }}</p>
    </div>
</x-site.borrower-layout>
