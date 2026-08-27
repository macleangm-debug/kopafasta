@props([
    'photos' => [],
    'retake' => true,
])

@php
    $slides = collect($photos)
        ->map(function ($photo, $index) {
            if (is_string($photo)) {
                return ['url' => $photo, 'label' => '', 'index' => $index];
            }

            return [
                'url' => $photo['url'] ?? null,
                'label' => $photo['label'] ?? '',
                'index' => $photo['index'] ?? $index,
            ];
        })
        ->filter(fn ($photo) => filled($photo['url'] ?? null))
        ->values()
        ->all();
@endphp

@if ($slides === [])
    {{ $slot }}
@else
    <div class="space-y-3"
         x-data="{
            index: 0,
            total: {{ count($slides) }},
            next() { this.index = (this.index + 1) % this.total; this.scrollTo(); },
            prev() { this.index = (this.index - 1 + this.total) % this.total; this.scrollTo(); },
            go(i) { this.index = i; this.scrollTo(); },
            scrollTo() {
                this.$refs.track?.children[this.index]?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            },
         }">
        <div class="relative">
            <div x-ref="track"
                 class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-2 scroll-smooth"
                 style="-webkit-overflow-scrolling: touch;">
                @foreach ($slides as $i => $slide)
                    <figure class="snap-center shrink-0 w-[78%] sm:w-72"
                            @click="go({{ $i }})">
                        <button type="button"
                                class="block w-full overflow-hidden rounded-2xl ring-1 ring-brand/15 bg-white"
                                @click="$dispatch('photo-carousel-open', { url: @js($slide['url']), label: @js($slide['label']), index: {{ $i }} })">
                            <img src="{{ $slide['url'] }}" alt="{{ $slide['label'] }}" class="h-56 w-full object-cover object-top">
                        </button>
                        @if (filled($slide['label']))
                            <figcaption class="mt-2 text-sm font-semibold text-gray-800">{{ $slide['label'] }}</figcaption>
                        @endif
                        @if ($retake)
                        <button type="button"
                                class="mt-2 text-xs font-semibold text-brand"
                                @click="$dispatch('photo-carousel-retake', { index: {{ $slide['index'] }} })">
                            {{ __('site.partner_account.face_retake') }}
                        </button>
                        @endif
                    </figure>
                @endforeach
            </div>
            @if (count($slides) > 1)
                <button type="button" @click="prev()"
                        class="hidden sm:grid absolute left-0 top-24 -translate-x-1/2 place-items-center size-10 rounded-full bg-brand text-white shadow-lg"
                        aria-label="{{ __('site.partner_portal.valuation_photo_back') }}">‹</button>
                <button type="button" @click="next()"
                        class="hidden sm:grid absolute right-0 top-24 translate-x-1/2 place-items-center size-10 rounded-full bg-brand text-white shadow-lg"
                        aria-label="{{ __('site.partner_portal.valuation_continue') }}">›</button>
            @endif
        </div>
        @if (count($slides) > 1)
            <div class="flex justify-center gap-1.5">
                @foreach ($slides as $i => $slide)
                    <button type="button" @click="go({{ $i }})"
                            class="size-2 rounded-full"
                            :class="index === {{ $i }} ? 'bg-brand' : 'bg-gray-300'"
                            aria-label="{{ $slide['label'] }}"></button>
                @endforeach
            </div>
        @endif
    </div>
@endif
