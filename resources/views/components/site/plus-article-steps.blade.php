@props([
    'opening' => [],
    'cards' => [],
    'slides' => null,
    'completeUrl' => null,
    'completed' => false,
])

@php
    $opening = array_values(array_filter(is_array($opening) ? $opening : [], fn ($p) => filled(trim((string) $p))));
    $cards = array_values(array_filter(is_array($cards) ? $cards : [], fn ($p) => filled(trim((string) $p))));
    $slides = is_array($slides) ? $slides : \App\Support\PlusArticleSteps::slidesFrom($opening, $cards);
    $slides = array_values(array_filter($slides, fn ($p) => filled(trim((string) $p))));
    $count = count($slides);
@endphp

<div class="space-y-3" x-data="{
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
        },
        go(dir) {
            const el = this.$refs.scroller;
            if (! el) return;
            el.scrollBy({ left: dir * el.clientWidth, behavior: 'smooth' });
        },
        sync() {
            const el = this.$refs.scroller;
            if (! el) return;
            const w = Math.max(el.clientWidth, 1);
            this.i = Math.min(this.n - 1, Math.max(0, Math.round(el.scrollLeft / w)));
            if (this.i === this.n - 1) this.markDone();
        }
     }"
     x-init="if (n <= 1) markDone()">
    @if ($count)
        <div class="w-full overflow-hidden">
            <div class="flex w-full overflow-x-auto snap-x snap-mandatory scrollbar-none"
                 x-ref="scroller"
                 @scroll.debounce.40ms="sync()">
                @foreach ($slides as $idx => $slide)
                    <article class="w-full min-w-full max-w-full shrink-0 grow-0 basis-full snap-start snap-always pr-1">
                        <div class="space-y-2.5 lg:space-y-2">
                            @foreach (\App\Support\PlusArticleSteps::blocks((string) $slide) as $block)
                                @if (($block['type'] ?? '') === 'h')
                                    <h2 class="text-brand font-extrabold text-lg lg:text-xl leading-snug tracking-tight">{{ $block['text'] }}</h2>
                                @else
                                    <p class="text-[15px] lg:text-base text-gray-800 leading-snug lg:leading-[1.4]">{!! $block['html'] ?? e($block['text'] ?? '') !!}</p>
                                @endif
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
        @if ($count > 1)
            <div class="flex items-center justify-between gap-3">
                <button type="button"
                        class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold disabled:bg-gray-200 disabled:text-gray-400"
                        :disabled="i === 0"
                        @click="go(-1)">{{ __('plus.learn.prev') }}</button>
                <div class="flex gap-1.5">
                    @foreach ($slides as $idx => $slide)
                        <button type="button"
                                class="size-2 rounded-full"
                                :class="i === {{ $idx }} ? 'bg-brand' : 'bg-gray-200'"
                                @click="$refs.scroller.children[{{ $idx }}]?.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' })"></button>
                    @endforeach
                </div>
                <button type="button"
                        class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold disabled:bg-gray-200 disabled:text-gray-400"
                        :disabled="i === n - 1"
                        @click="go(1)">{{ __('plus.learn.next') }}</button>
            </div>
        @endif
    @endif

    @if ($slot->isNotEmpty())
        <div>{{ $slot }}</div>
    @endif
</div>
