@props(['steps' => []])

@php
    $steps = array_values(array_filter(
        is_array($steps) ? $steps : [],
        fn ($step) => filled(trim((string) $step)),
    ));
    $count = count($steps);
@endphp

@if ($count)
    <div class="space-y-4" x-data="{ i: 0, n: {{ $count }} }">
        <div class="flex overflow-x-auto snap-x snap-mandatory scrollbar-none -mx-1"
             x-ref="scroller"
             @scroll.debounce.50ms="i = Math.min(n - 1, Math.max(0, Math.round($refs.scroller.scrollLeft / Math.max($refs.scroller.clientWidth, 1))))">
            @foreach ($steps as $idx => $step)
                <div class="min-w-full snap-start shrink-0 px-1">
                    @if ($count > 1)
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.learn.step', ['n' => $idx + 1, 'of' => $count]) }}</p>
                    @endif
                    <p class="{{ $count > 1 ? 'mt-2 ' : '' }}text-[15px] text-gray-800 leading-relaxed">{{ $step }}</p>
                </div>
            @endforeach
        </div>
        @if ($count > 1)
            <div class="flex items-center justify-between gap-3">
                <button type="button"
                        class="text-sm font-semibold text-brand disabled:text-gray-300"
                        :disabled="i === 0"
                        @click="$refs.scroller.scrollBy({ left: -$refs.scroller.clientWidth, behavior: 'smooth' })">{{ __('plus.learn.prev') }}</button>
                <div class="flex gap-1.5">
                    @foreach ($steps as $idx => $step)
                        <button type="button"
                                class="size-2 rounded-full"
                                :class="i === {{ $idx }} ? 'bg-brand' : 'bg-gray-200'"
                                @click="$refs.scroller.children[{{ $idx }}].scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' })"
                                aria-label="{{ __('plus.learn.step', ['n' => $idx + 1, 'of' => $count]) }}"></button>
                    @endforeach
                </div>
                <button type="button"
                        class="text-sm font-semibold text-brand disabled:text-gray-300"
                        :disabled="i === n - 1"
                        @click="$refs.scroller.scrollBy({ left: $refs.scroller.clientWidth, behavior: 'smooth' })">{{ __('plus.learn.next') }}</button>
            </div>
        @endif
        @if ($slot->isNotEmpty())
            <div>{{ $slot }}</div>
        @endif
    </div>
@endif
