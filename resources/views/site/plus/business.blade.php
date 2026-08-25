@php
    $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
    $saleOptions = collect($sale_types)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
    $spendOptions = collect($spend_types)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
@endphp
<x-site.borrower-layout :title="brand_title(__('plus.home.business'))" active="dashboard">
    <div class="space-y-5" x-data="{
        saleOpen: false,
        spendOpen: false,
        setSpendCat(val) { this.spendCat = val; },
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

        <div x-data="{ shown: 10 }">
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ __('plus.business.history') }}</p>
            <div class="hidden lg:block overflow-x-auto rounded-2xl ring-1 ring-gray-100 bg-white">
                <table class="w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-widest text-gray-500">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">{{ __('plus.business.col_date') }}</th>
                            <th class="text-left px-4 py-3 font-semibold">{{ __('plus.business.col_what') }}</th>
                            <th class="text-right px-4 py-3 font-semibold">{{ __('plus.business.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history_rows as $i => $row)
                            <tr class="border-t border-gray-50" x-show="{{ $i }} < shown">
                                <td class="px-4 py-3 text-gray-600">{{ $row['date']->locale(app()->getLocale())->isoFormat('D MMM') }}</td>
                                <td class="px-4 py-3">{{ $row['kind'] === 'sale' ? __('plus.business.sale') : __('plus.business.spend') }} · {{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $row['kind'] === 'sale' ? 'text-emerald-700' : 'text-gray-900' }}">
                                    {{ $row['kind'] === 'sale' ? '+' : '−' }}{{ format_money($row['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6"><x-site.empty-state compact icon="🏪" :title="__('plus.business.empty')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="lg:hidden space-y-2">
                @forelse ($history_rows as $i => $row)
                    <div class="rounded-2xl bg-white ring-1 ring-gray-100 px-4 py-3" x-show="{{ $i }} < shown">
                        <div class="flex justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">{{ $row['kind'] === 'sale' ? __('plus.business.sale') : __('plus.business.spend') }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $row['label'] }} · {{ $row['date']->locale(app()->getLocale())->isoFormat('D MMM') }}</p>
                            </div>
                            <p class="text-sm font-bold tabular-nums {{ $row['kind'] === 'sale' ? 'text-emerald-700' : 'text-gray-900' }}">
                                {{ $row['kind'] === 'sale' ? '+' : '−' }}{{ format_money($row['amount']) }}
                            </p>
                        </div>
                    </div>
                @empty
                    <x-site.empty-state compact icon="🏪" :title="__('plus.business.empty')" />
                @endforelse
            </div>
            @if (count($history_rows) > 10)
                <button type="button" class="mt-3 text-sm font-semibold text-brand" x-show="shown < {{ count($history_rows) }}" @click="shown += 10">{{ __('plus.load_more') }}</button>
            @endif
        </div>

        @include('site.plus._business-capture', [
            'open' => 'saleOpen',
            'title' => __('plus.business.sold_action'),
            'formId' => 'plus-business-sale',
            'kind' => 'sale',
            'amountName' => 'sold',
            'amountId' => 'business-sale-amount',
            'amountLabel' => __('plus.business.how_much_sold'),
            'categoryLabel' => __('plus.business.what_sold'),
            'categoryModel' => 'saleCat',
            'categorySetter' => 'setSaleCat',
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
            'categoryModel' => 'spendCat',
            'categorySetter' => 'setSpendCat',
            'options' => $spendOptions,
            'confirmTemplate' => __('plus.business.confirm_spend'),
            'noteLabel' => __('plus.business.note'),
        ])
    </div>
</x-site.borrower-layout>
