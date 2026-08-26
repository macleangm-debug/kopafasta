@php
    $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
    $saleOptions = collect($sale_types)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
    $spendOptions = collect($spend_types)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
@endphp
<x-site.borrower-layout :title="brand_title(__('plus.home.business'))" active="plus">
    <div class="space-y-5" x-data="{
        saleOpen: false,
        spendOpen: false,
        resetAmount(id) {
            const el = document.getElementById(id);
            if (! el) return;
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }">
        <x-site.plus-nav />

        <x-site.plus-hero kicker="Kopafasta Plus · {{ $period_label }}" :title="__('plus.business.title')" :body="__('plus.business.hero_body')">
            <div class="flex gap-1 rounded-full bg-white/10 p-1 mb-4">
                @foreach (['today' => __('plus.business.today'), 'week' => __('plus.business.week'), 'month' => __('plus.business.month')] as $key => $label)
                    <a href="{{ route('site.borrower.plus.business', ['period' => $key]) }}"
                       class="flex-1 text-center rounded-full px-2 py-1.5 text-xs font-semibold {{ $period === $key ? 'bg-brand-gold text-brand' : 'text-white/80' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.business.sold') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-lg" title="{{ format_money($summary['sold']) }}">{{ format_money_compact($summary['sold']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.business.spent') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-lg" title="{{ format_money($summary['spent']) }}">{{ format_money_compact($summary['spent']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold">{{ __('plus.business.diff') }}</p>
                    <p class="font-extrabold tabular-nums mt-1 text-lg text-brand-gold" title="{{ format_money($summary['difference']) }}">{{ ($summary['difference'] >= 0 ? '+' : '').format_money_compact($summary['difference']) }}</p>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="saleOpen = true; resetAmount('business-sale-amount')" class="rounded-xl bg-brand-gold text-brand px-4 py-3 text-sm font-bold">{{ __('plus.business.sold_action') }}</button>
                <button type="button" @click="spendOpen = true; resetAmount('business-spend-amount')" class="rounded-xl bg-white/10 ring-1 ring-white/20 text-white px-4 py-3 text-sm font-semibold">{{ __('plus.business.spent_action') }}</button>
            </div>

            <div class="mt-5 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4">
                @if ($chart_ready)
                    <x-site.plus-week-chart :days="$chart" />
                @else
                    <p class="text-sm font-semibold text-white">{{ __('plus.business.chart_empty_title') }}</p>
                    <p class="mt-1 text-sm text-white/75">{{ __('plus.business.chart_empty_body') }}</p>
                @endif
                @if ($insight)
                    <p class="mt-3 text-sm text-white/85">{{ $insight }}</p>
                @endif
            </div>
        </x-site.plus-hero>

        @if (count($history_rows))
            @include('site.plus._history-table', [
                'title' => __('plus.business.history'),
                'dateLabel' => __('plus.business.col_date'),
                'whatLabel' => __('plus.business.col_what'),
                'amountLabel' => __('plus.business.amount'),
                'rows' => collect($history_rows)->map(fn ($row) => [
                    'date' => $row['date']->locale(app()->getLocale())->isoFormat('D MMM'),
                    'label' => ($row['kind'] === 'sale' ? __('plus.business.sale') : __('plus.business.spend')).' · '.$row['label'],
                    'in' => $row['kind'] === 'sale',
                    'amount' => $row['amount'],
                ])->all(),
            ])
        @else
            <x-site.empty-state compact icon="🏪" :title="__('plus.business.empty')" />
        @endif

        @include('site.plus._business-capture', [
            'open' => 'saleOpen',
            'title' => __('plus.business.sold_action'),
            'formId' => 'plus-business-sale',
            'kind' => 'sale',
            'amountName' => 'sold',
            'amountId' => 'business-sale-amount',
            'amountLabel' => __('plus.business.how_much_sold'),
            'categoryLabel' => __('plus.business.what_sold'),
            'options' => $saleOptions,
            'confirmTemplate' => __('plus.business.confirm_sale'),
            'noteLabel' => __('plus.business.note'),
        ])
        @include('site.plus._business-capture', [
            'open' => 'spendOpen',
            'title' => __('plus.business.spent_action'),
            'formId' => 'plus-business-spend',
            'kind' => 'spend',
            'amountName' => 'spent',
            'amountId' => 'business-spend-amount',
            'amountLabel' => __('plus.business.how_much_spent'),
            'categoryLabel' => __('plus.business.what_for'),
            'options' => $spendOptions,
            'confirmTemplate' => __('plus.business.confirm_spend'),
            'noteLabel' => __('plus.business.note'),
        ])
    </div>
</x-site.borrower-layout>
