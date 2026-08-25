<x-site.borrower-layout :title="brand_title(__('plus.home.money'))" active="dashboard">
    @php
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $labels = app(\App\Services\Plus\PlusWorkspaceService::class);
    @endphp
    <div class="space-y-5" x-data="{ inOpen: false, outOpen: false }">
        <x-site.plus-nav />

        <x-site.plus-hero kicker="Kopafasta Plus · {{ $month_label }}" :title="__('plus.money.title')" :body="__('plus.money.hero_body')">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.money.in') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-white">{{ format_money($in) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.money.out') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-white">{{ format_money($out) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold">{{ __('plus.money.left_label') }}</p>
                    <p class="font-extrabold tabular-nums mt-1 text-brand-gold">{{ format_money($left) }}</p>
                </div>
            </div>
            @if ($insight)
                <p class="mt-4 text-sm text-white/85"><span class="font-semibold text-white">{{ __('plus.money.how_going') }}</span> {{ $insight }}</p>
            @endif
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="inOpen = true" class="rounded-xl bg-brand-gold text-brand px-4 py-3 text-sm font-bold">{{ __('plus.money.in_action') }}</button>
                <button type="button" @click="outOpen = true" class="rounded-xl bg-white/10 ring-1 ring-white/20 text-white px-4 py-3 text-sm font-semibold">{{ __('plus.money.out_action') }}</button>
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
                                        <span>{{ $entry->entry_date?->locale(app()->getLocale())->isoFormat('D MMM') }} · {{ $labels->moneyCategoryLabel($entry->category) }}</span>
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

        @if ($history->isEmpty() && ! count($top_spend))
            <x-site.empty-state compact icon="💸" :title="__('plus.money.empty')" />
        @endif

        <x-site.action-panel title="{{ __('plus.money.in_action') }}" open="inOpen">
            <form method="post" action="{{ route('site.borrower.plus.money.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="direction" value="in">
                <x-site.numeric-input name="amount" id="money-in-amount" :money="true" :label="__('plus.money.how_much')" required />
                <p class="text-xs font-medium text-gray-600">{{ __('plus.money.from_where') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($sources as $key => $labelsRow)
                        <label class="cursor-pointer">
                            <input type="radio" name="category" value="{{ $key }}" class="peer sr-only" @checked($loop->first) required>
                            <span class="inline-flex rounded-full ring-1 ring-gray-200 px-3 py-2 text-sm peer-checked:bg-brand peer-checked:text-white peer-checked:ring-brand">{{ $labelsRow[$locale] }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
            </form>
        </x-site.action-panel>

        <x-site.action-panel title="{{ __('plus.money.out_action') }}" open="outOpen">
            <form method="post" action="{{ route('site.borrower.plus.money.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="direction" value="out">
                <x-site.numeric-input name="amount" id="money-out-amount" :money="true" :label="__('plus.money.how_much')" required />
                <p class="text-xs font-medium text-gray-600">{{ __('plus.money.why') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($categories as $key => $labelsRow)
                        <label class="cursor-pointer">
                            <input type="radio" name="category" value="{{ $key }}" class="peer sr-only" @checked($loop->first) required>
                            <span class="inline-flex rounded-full ring-1 ring-gray-200 px-3 py-2 text-sm peer-checked:bg-brand peer-checked:text-white peer-checked:ring-brand">{{ $labelsRow[$locale] }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
