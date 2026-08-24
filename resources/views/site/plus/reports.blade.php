<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="dashboard">
    <div class="space-y-4">
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('plus.reports.money') }}</p>
            <p class="mt-1">{{ __('plus.reports.money_line', ['in' => format_money($moneyIn), 'out' => format_money($moneyOut), 'left' => format_money($moneyIn - $moneyOut)]) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('plus.reports.business') }}</p>
            <p class="mt-1">{{ __('plus.reports.business_line', ['sold' => format_money($sold), 'spent' => format_money($spent), 'left' => format_money($sold - $spent)]) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('plus.reports.goals') }}</p>
            <p class="mt-1">{{ __('plus.reports.goals_line', ['done' => $goals->filter->isComplete()->count(), 'total' => $goals->count()]) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('plus.reports.trust') }}</p>
            <p class="mt-1">{{ strtoupper($grade) }} · {{ $trust['percent'] ?? 0 }} — {{ $trust['label'] ?? '' }}</p>
        </div>
    </div>
</x-site.borrower-layout>
