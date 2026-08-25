<x-site.borrower-layout :title="brand_title(__('plus.home.goals'))" active="dashboard">
    @php $locale = app()->getLocale() === 'sw' ? 'sw' : 'en'; @endphp
    <div class="space-y-5" x-data="{ newOpen: false, addId: null }">
        <a href="{{ route('site.borrower.plus.home') }}" class="text-sm font-semibold text-brand">← Plus</a>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-gray-900">{{ __('plus.goals.title') }}</h1>
            <button type="button" @click="newOpen = true" class="rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-semibold">{{ __('plus.goals.new') }}</button>
        </div>

        @forelse ($goals as $goal)
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <p class="font-semibold text-gray-900">{{ $goal->kindIcon() }} {{ $goal->title }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ format_money($goal->saved_amount) }} / {{ format_money($goal->target_amount) }}</p>
                <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full bg-brand rounded-full" style="width: {{ $goal->progressPercent() }}%"></div>
                </div>
                <p class="text-sm font-semibold mt-2">{{ $goal->progressPercent() }}% · {{ __('plus.goals.remaining', ['amount' => format_money($goal->remaining())]) }}</p>
                @if ($goal->isComplete())
                    <p class="text-sm text-emerald-700 font-medium mt-2">{{ __('plus.goals.completed') }}</p>
                @elseif ($goal->isPaused())
                    <form method="post" action="{{ route('site.borrower.plus.goals.pause', $goal) }}" class="mt-3">
                        @csrf
                        <button class="text-sm font-semibold text-brand">{{ __('plus.goals.resume') }}</button>
                    </form>
                @else
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="addId = {{ $goal->id }}" class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">+ {{ __('plus.goals.add') }}</button>
                        <form method="post" action="{{ route('site.borrower.plus.goals.pause', $goal) }}">
                            @csrf
                            <button class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2 text-sm font-semibold">{{ __('plus.goals.pause') }}</button>
                        </form>
                    </div>
                    <x-site.action-panel title="{{ __('plus.goals.add') }}" open="addId === {{ $goal->id }}">
                        <form method="post" action="{{ route('site.borrower.plus.goals.contribute', $goal) }}" class="space-y-4">
                            @csrf
                            <x-site.numeric-input name="amount" :money="true" :label="__('plus.business.amount')" required />
                            <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
                        </form>
                    </x-site.action-panel>
                @endif
            </div>
        @empty
            <x-site.empty-state compact icon="🎯" :title="__('plus.goals.empty')" />
        @endforelse

        <x-site.action-panel title="{{ __('plus.goals.new') }}" open="newOpen">
            <form method="post" action="{{ route('site.borrower.plus.goals.save') }}" class="space-y-4">
                @csrf
                <p class="text-xs font-medium text-gray-600">{{ __('plus.goals.kind') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($kinds as $key => $meta)
                        <label class="cursor-pointer">
                            <input type="radio" name="kind" value="{{ $key }}" class="peer sr-only" @checked($loop->first) required>
                            <span class="inline-flex rounded-full ring-1 ring-gray-200 px-3 py-2 text-sm peer-checked:bg-brand peer-checked:text-white peer-checked:ring-brand">{{ $meta['icon'] }} {{ $meta[$locale] }}</span>
                        </label>
                    @endforeach
                </div>
                <x-site.numeric-input name="target_amount" :money="true" :label="__('plus.goals.target')" required />
                <label class="block text-xs font-medium text-gray-600">{{ __('plus.goals.date') }}
                    <input type="date" name="target_date" class="mt-1 w-full min-h-11 rounded-xl border-gray-300">
                </label>
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.goals.save') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
