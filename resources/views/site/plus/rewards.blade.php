<x-site.borrower-layout :title="brand_title(__('plus.home.rewards'))" active="dashboard">
    <div class="space-y-5" x-data="{ redeemOpen: false, code: '' }">
        <x-site.plus-nav />
        <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.rewards.title')" :body="__('plus.rewards.hero_body')">
            <p class="text-4xl sm:text-5xl font-black tabular-nums tracking-tight">{{ __('plus.rewards.points', ['balance' => $balance]) }}</p>
            <p class="text-sm text-white/80 mt-2">{{ __('plus.rewards.borrow_line') }}</p>
            @if ($balance > 0)
                <button type="button" @click="redeemOpen = true" class="mt-4 rounded-xl bg-brand-gold text-brand px-5 py-2.5 text-sm font-bold">{{ __('plus.rewards.use') }}</button>
            @endif
        </x-site.plus-hero>

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.rewards.can_get') }}</p>
            <div class="mt-3 space-y-3">
                @foreach ($catalog as $item)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span>{{ $item['title'] }} — {{ $item['points'] }}</span>
                        @if ($balance >= (int) $item['points'])
                            <form method="post" action="{{ route('site.borrower.plus.rewards.redeem') }}">
                                @csrf
                                <input type="hidden" name="code" value="{{ $item['code'] }}">
                                <button class="rounded-xl bg-brand text-white px-3 py-1.5 text-xs font-semibold">{{ __('plus.rewards.redeem') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ __('plus.rewards.earned') }}</p>
            @forelse ($earned as $row)
                <div class="rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm flex justify-between gap-3 mb-2">
                    <span>{{ $row->reason }}</span>
                    <span class="font-semibold">+{{ $row->points }}</span>
                </div>
            @empty
                <x-site.empty-state compact icon="✦" :title="__('plus.rewards.empty')" />
            @endforelse
        </div>

        <x-site.action-panel title="{{ __('plus.rewards.use') }}" open="redeemOpen">
            <form method="post" action="{{ route('site.borrower.plus.rewards.redeem') }}" class="space-y-3">
                @csrf
                @foreach ($catalog as $item)
                    <label class="flex items-center justify-between gap-3 rounded-xl ring-1 ring-gray-200 px-4 py-3 cursor-pointer">
                        <span class="text-sm">{{ $item['title'] }} · {{ $item['points'] }}</span>
                        <input type="radio" name="code" value="{{ $item['code'] }}" @checked($loop->first)>
                    </label>
                @endforeach
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.rewards.redeem') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
