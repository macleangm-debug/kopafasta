<x-site.borrower-layout :title="brand_title(__('plus.home.business'))" active="dashboard">
    <div class="space-y-5" x-data="{ saleOpen: false, spendOpen: false }">
        <a href="{{ route('site.borrower.plus.home') }}" class="text-sm font-semibold text-brand">← Plus</a>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6">
            <h1 class="text-xl font-bold text-gray-900">{{ __('plus.business.title') }}</h1>
            <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-4">{{ __('plus.business.today') }}</p>
            <div class="mt-2 grid grid-cols-3 gap-3">
                <div>
                    <p class="text-xs text-gray-500">{{ __('plus.business.sold') }}</p>
                    <p class="font-bold tabular-nums">{{ format_money($today['sold']) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('plus.business.spent') }}</p>
                    <p class="font-bold tabular-nums">{{ format_money($today['spent']) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('plus.business.diff') }}</p>
                    <p class="font-extrabold tabular-nums text-brand">{{ ($today['difference'] >= 0 ? '+' : '').format_money($today['difference']) }}</p>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="saleOpen = true" class="rounded-xl bg-brand text-white px-4 py-3 text-sm font-semibold">{{ __('plus.business.sold_action') }}</button>
                <button type="button" @click="spendOpen = true" class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm font-semibold">{{ __('plus.business.spent_action') }}</button>
            </div>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.business.week') }}</p>
            <p class="text-sm mt-2">{{ __('plus.business.sold') }}: <span class="font-semibold">{{ format_money($week['sold']) }}</span></p>
            <p class="text-sm">{{ __('plus.business.spent') }}: <span class="font-semibold">{{ format_money($week['spent']) }}</span></p>
            <p class="text-sm">{{ __('plus.business.diff') }}: <span class="font-bold">{{ format_money($week['difference']) }}</span></p>
            @if ($points->count() > 1)
                <div class="mt-4 flex items-end gap-1 h-16">
                    @php $max = max(1, $points->max('sold')); @endphp
                    @foreach ($points as $point)
                        <div class="flex-1 bg-brand/20 rounded-t" style="height: {{ max(8, round(($point['sold'] / $max) * 100)) }}%"></div>
                    @endforeach
                </div>
            @endif
            @if ($insight)
                <div class="mt-4 rounded-xl bg-brand/5 ring-1 ring-brand/10 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ __('plus.business.seen') }}</p>
                    <p class="text-sm text-gray-800 mt-1">{{ $insight }}</p>
                </div>
            @endif
        </div>

        <div>
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ __('plus.business.history') }}</p>
            @forelse ($history as $entry)
                <div class="rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm flex justify-between gap-3 mb-2">
                    <span>{{ $entry->entry_date?->format('d M') }}</span>
                    <span class="tabular-nums">{{ __('plus.business.sold') }} {{ format_money($entry->sold) }} · {{ __('plus.business.spent') }} {{ format_money($entry->spent) }}</span>
                </div>
            @empty
                <x-site.empty-state compact icon="🏪" :title="__('plus.business.empty')" />
            @endforelse
        </div>

        <x-site.action-panel title="{{ __('plus.business.sold_action') }}" open="saleOpen">
            <form method="post" action="{{ route('site.borrower.plus.business.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="kind" value="sale">
                <x-site.numeric-input name="amount" :money="true" :label="__('plus.business.amount')" required />
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
            </form>
        </x-site.action-panel>
        <x-site.action-panel title="{{ __('plus.business.spent_action') }}" open="spendOpen">
            <form method="post" action="{{ route('site.borrower.plus.business.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="kind" value="spend">
                <x-site.numeric-input name="amount" :money="true" :label="__('plus.business.amount')" required />
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
