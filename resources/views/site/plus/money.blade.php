<x-site.borrower-layout :title="brand_title(__('plus.home.money'))" active="plus">
    @php
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $labels = app(\App\Services\Plus\PlusWorkspaceService::class);
        $sourceOptions = collect($sources)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
        $categoryOptions = collect($categories)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
    @endphp
    <div class="space-y-5" x-data="{
        inOpen: false,
        outOpen: false,
        resetAmount(id) {
            const el = document.getElementById(id);
            if (! el) return;
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }">
        <x-site.plus-nav />

        <x-site.plus-hero kicker="Kopafasta Plus · {{ $month_label }}" :title="__('plus.money.title')" :body="__('plus.money.hero_body')">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.money.in') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-lg" title="{{ format_money($in) }}">{{ format_money_compact($in) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.money.out') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-lg" title="{{ format_money($out) }}">{{ format_money_compact($out) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold">{{ __('plus.money.left_label') }}</p>
                    <p class="font-extrabold tabular-nums mt-1 text-lg text-brand-gold" title="{{ format_money($left) }}">{{ format_money_compact($left) }}</p>
                </div>
            </div>
            @if ($insight)
                <p class="mt-4 text-sm text-white/85"><span class="font-semibold text-white">{{ __('plus.money.how_going') }}</span> {{ $insight }}</p>
            @endif
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="inOpen = true; resetAmount('money-in-amount')" class="rounded-xl bg-brand-gold text-brand px-4 py-3 text-sm font-bold">{{ __('plus.money.in_action') }}</button>
                <button type="button" @click="outOpen = true; resetAmount('money-out-amount')" class="rounded-xl bg-white/10 ring-1 ring-white/20 text-white px-4 py-3 text-sm font-semibold">{{ __('plus.money.out_action') }}</button>
            </div>

            @if (count($top_spend) || $history->isNotEmpty())
                <div class="mt-5 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4 space-y-4">
                    @if (count($top_spend))
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.money.top_spend') }}</p>
                            <div class="mt-2 space-y-2">
                                @foreach ($top_spend as $row)
                                    <div class="flex justify-between text-sm">
                                        <span>{{ $row['label'] }}</span>
                                        <span class="font-semibold tabular-nums">{{ format_money($row['amount']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($history->isNotEmpty())
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.money.history') }}</p>
                            <div class="mt-2 space-y-2">
                                @foreach ($history->take(4) as $entry)
                                    <div class="flex justify-between gap-3 text-sm">
                                        <span>{{ $entry->entry_date?->locale(app()->getLocale())->isoFormat('D MMM') }} · {{ $labels->moneyCategoryLabel($entry->category, $entry->other_label) }}</span>
                                        <span class="tabular-nums">{{ (float) $entry->inflow > 0 ? '+'.format_money($entry->inflow) : '−'.format_money($entry->outflow) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </x-site.plus-hero>

        @if (count($upcoming))
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.money.upcoming') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ($upcoming as $item)
                        <div class="flex justify-between gap-3 text-sm">
                            <span>{{ $item['title'] }}</span>
                            <span class="text-gray-600 tabular-nums">{{ format_money($item['amount']) }} · {{ $item['date']->locale(app()->getLocale())->isoFormat('dddd D MMM') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($history->isNotEmpty())
            @include('site.plus._history-table', [
                'title' => __('plus.money.history'),
                'dateLabel' => __('plus.money.col_date'),
                'whatLabel' => __('plus.money.col_what'),
                'amountLabel' => __('plus.money.amount'),
                'rows' => $history->map(fn ($entry) => [
                    'date' => $entry->entry_date?->locale(app()->getLocale())->isoFormat('D MMM'),
                    'label' => $labels->moneyCategoryLabel($entry->category, $entry->other_label),
                    'in' => (float) $entry->inflow > 0,
                    'amount' => (float) $entry->inflow > 0 ? $entry->inflow : $entry->outflow,
                ])->all(),
            ])
        @endif

        @if ($history->isEmpty() && ! count($top_spend))
            <x-site.empty-state compact icon="💸" :title="__('plus.money.empty')" />
        @endif

        @include('site.plus._money-capture', [
            'open' => 'inOpen',
            'title' => __('plus.money.in_action'),
            'formId' => 'plus-money-in',
            'direction' => 'in',
            'amountName' => 'in_amount',
            'amountId' => 'money-in-amount',
            'amountLabel' => __('plus.money.how_much_in'),
            'categoryLabel' => __('plus.money.from_where'),
            'options' => $sourceOptions,
            'confirmTemplate' => __('plus.money.confirm_in'),
        ])

        @include('site.plus._money-capture', [
            'open' => 'outOpen',
            'title' => __('plus.money.out_action'),
            'formId' => 'plus-money-out',
            'direction' => 'out',
            'amountName' => 'out_amount',
            'amountId' => 'money-out-amount',
            'amountLabel' => __('plus.money.how_much_out'),
            'categoryLabel' => __('plus.money.why'),
            'options' => $categoryOptions,
            'confirmTemplate' => __('plus.money.confirm_out'),
        ])
    </div>
</x-site.borrower-layout>
