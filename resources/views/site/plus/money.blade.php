<x-site.borrower-layout :title="brand_title(__('plus.home.money'))" active="dashboard">
    @php $locale = app()->getLocale() === 'sw' ? 'sw' : 'en'; @endphp
    <div class="space-y-5" x-data="{ inOpen: false, outOpen: false }">
        <a href="{{ route('site.borrower.plus.home') }}" class="text-sm font-semibold text-brand">← Plus</a>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6">
            <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ $month_label }}</p>
            <h1 class="text-xl font-bold text-gray-900 mt-1">{{ __('plus.money.title') }}</h1>
            <div class="mt-4 grid grid-cols-3 gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('plus.money.in') }}</p>
                    <p class="font-bold tabular-nums mt-1">{{ format_money($in) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('plus.money.out') }}</p>
                    <p class="font-bold tabular-nums mt-1">{{ format_money($out) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('plus.money.left_label') }}</p>
                    <p class="font-extrabold tabular-nums mt-1 text-brand">{{ format_money($left) }}</p>
                </div>
            </div>
            @if ($insight)
                <p class="mt-4 text-sm text-gray-600"><span class="font-semibold text-gray-800">{{ __('plus.money.how_going') }}</span> {{ $insight }}</p>
            @endif
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="inOpen = true" class="rounded-xl bg-brand text-white px-4 py-3 text-sm font-semibold">{{ __('plus.money.in_action') }}</button>
                <button type="button" @click="outOpen = true" class="rounded-xl bg-white ring-1 ring-gray-200 text-gray-900 px-4 py-3 text-sm font-semibold">{{ __('plus.money.out_action') }}</button>
            </div>
        </div>

        @if (count($top_spend))
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.money.top_spend') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ($top_spend as $row)
                        <div class="flex justify-between text-sm">
                            <span>{{ $row['label'] }}</span>
                            <span class="font-semibold tabular-nums">{{ format_money($row['amount']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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

        <div>
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ __('plus.money.history') }}</p>
            @forelse ($history as $entry)
                <div class="rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm flex justify-between gap-3 mb-2">
                    <span>{{ $entry->entry_date?->format('d M') }} · {{ $entry->category ?: '—' }}</span>
                    <span class="tabular-nums">{{ (float) $entry->inflow > 0 ? '+'.format_money($entry->inflow) : '−'.format_money($entry->outflow) }}</span>
                </div>
            @empty
                <x-site.empty-state compact icon="💸" :title="__('plus.money.empty')" />
            @endforelse
        </div>

        <x-site.action-panel title="{{ __('plus.money.in_action') }}" open="inOpen">
            <form method="post" action="{{ route('site.borrower.plus.money.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="direction" value="in">
                <x-site.numeric-input name="amount" :money="true" :label="__('plus.money.how_much')" required />
                <p class="text-xs font-medium text-gray-600">{{ __('plus.money.from_where') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($sources as $key => $labels)
                        <label class="cursor-pointer">
                            <input type="radio" name="category" value="{{ $key }}" class="peer sr-only" @checked($loop->first) required>
                            <span class="inline-flex rounded-full ring-1 ring-gray-200 px-3 py-2 text-sm peer-checked:bg-brand peer-checked:text-white peer-checked:ring-brand">{{ $labels[$locale] }}</span>
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
                <x-site.numeric-input name="amount" :money="true" :label="__('plus.money.how_much')" required />
                <p class="text-xs font-medium text-gray-600">{{ __('plus.money.why') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($categories as $key => $labels)
                        <label class="cursor-pointer">
                            <input type="radio" name="category" value="{{ $key }}" class="peer sr-only" @checked($loop->first) required>
                            <span class="inline-flex rounded-full ring-1 ring-gray-200 px-3 py-2 text-sm peer-checked:bg-brand peer-checked:text-white peer-checked:ring-brand">{{ $labels[$locale] }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
