<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="dashboard">
    @php
        $money = $report['money'];
        $biz = $report['business'];
    @endphp
    <div class="space-y-5" @if($print ?? false) x-init="setTimeout(() => window.print(), 300)" @endif>
        <a href="{{ route('site.borrower.plus.home') }}" class="text-sm font-semibold text-brand print:hidden">← Plus</a>
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <h1 class="text-xl font-bold text-gray-900">{{ __('plus.reports.title', ['period' => $report['label']]) }}</h1>
            <div class="flex gap-2">
                @foreach (['week' => __('plus.reports.week'), 'month' => __('plus.reports.month'), 'year' => __('plus.reports.year')] as $key => $label)
                    <a href="{{ route('site.borrower.plus.reports', ['period' => $key]) }}"
                       class="rounded-full px-3 py-1.5 text-sm font-semibold ring-1 {{ $period === $key ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 space-y-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.money') }}</p>
            <p>{{ __('plus.reports.money_in', ['amount' => format_money($money['in'])]) }}</p>
            <p>{{ __('plus.reports.money_out', ['amount' => format_money($money['out'])]) }}</p>
            <p class="font-bold">{{ __('plus.reports.money_left', ['amount' => format_money($money['in'] - $money['out'])]) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 space-y-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.business') }}</p>
            <p>{{ __('plus.business.sold') }} {{ format_money($biz['sold']) }}</p>
            <p>{{ __('plus.business.spent') }} {{ format_money($biz['spent']) }}</p>
            <p class="font-bold">{{ __('plus.business.diff') }} {{ format_money($biz['difference']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.goals') }}</p>
            <p class="mt-2">{{ __('plus.reports.goals_line', ['done' => $report['goals_done'], 'total' => $report['goals_total']]) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.trust') }}</p>
            <p class="mt-2 text-lg font-bold">{{ strtoupper($grade) }} · {{ $trust['percent'] ?? 0 }} — {{ $trust['label'] ?? '' }}</p>
            <p class="text-sm text-gray-600 mt-1">{{ __('plus.reports.trust_ok') }}</p>
        </div>
        <a href="{{ route('site.borrower.plus.reports', ['period' => $period, 'print' => 1]) }}"
           class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold print:hidden">{{ __('plus.reports.print') }}</a>
    </div>
</x-site.borrower-layout>
