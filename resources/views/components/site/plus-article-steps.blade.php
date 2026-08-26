@props([
    'opening' => [],
    'cards' => [],
    'completeUrl' => null,
    'completed' => false,
])

@php
    $opening = array_values(array_filter(is_array($opening) ? $opening : [], fn ($p) => filled(trim((string) $p))));
    $cards = array_values(array_filter(is_array($cards) ? $cards : [], fn ($p) => filled(trim((string) $p))));
    $count = count($cards);
    $paraClass = 'text-[15px] text-gray-800 leading-[1.55]';
@endphp

<div class="space-y-4" x-data="{
        i: 0,
        n: {{ $count }},
        done: {{ $completed ? 'true' : 'false' }},
        completeUrl: @js($completeUrl),
        markDone() {
            if (this.done || ! this.completeUrl) return;
            this.done = true;
            const token = document.querySelector('meta[name=csrf-token]')?.content;
            fetch(this.completeUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).catch(() => {});
        }
     }"
     x-init="if (n === 0) markDone()">
    @if ($opening)
        <div class="space-y-3">
            @foreach ($opening as $para)
                <p class="{{ $paraClass }}">{{ $para }}</p>
            @endforeach
        </div>
    @endif

    @if ($count)
        <div class="flex overflow-x-auto snap-x snap-mandatory scrollbar-none -mx-1"
             x-ref="scroller"
             @scroll.debounce.50ms="
                i = Math.min(n - 1, Math.max(0, Math.round($refs.scroller.scrollLeft / Math.max($refs.scroller.clientWidth, 1))));
                if (i === n - 1) markDone();
             ">
            @foreach ($cards as $idx => $card)
                <div class="min-w-full snap-start shrink-0 px-1 space-y-3">
                    @foreach (preg_split('/\n\s*\n/', trim($card)) as $para)
                        @if (filled(trim($para)))
                            <p class="{{ $paraClass }}">{{ trim($para) }}</p>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between gap-3">
            <button type="button"
                    class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold disabled:bg-gray-200 disabled:text-gray-400"
                    :disabled="i === 0"
                    @click="$refs.scroller.scrollBy({ left: -$refs.scroller.clientWidth, behavior: 'smooth' })">{{ __('plus.learn.prev') }}</button>
            <div class="flex gap-1.5">
                @foreach ($cards as $idx => $card)
                    <button type="button"
                            class="size-2 rounded-full"
                            :class="i === {{ $idx }} ? 'bg-brand' : 'bg-gray-200'"
                            @click="$refs.scroller.children[{{ $idx }}].scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' })"></button>
                @endforeach
            </div>
            <button type="button"
                    class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold disabled:bg-gray-200 disabled:text-gray-400"
                    :disabled="i === n - 1"
                    @click="$refs.scroller.scrollBy({ left: $refs.scroller.clientWidth, behavior: 'smooth' })">{{ __('plus.learn.next') }}</button>
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div>{{ $slot }}</div>
    @endif
</div>
