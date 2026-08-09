@props(['photos' => [], 'category' => 'other', 'zoom' => false])

@php
    $urls = marketplace_photo_urls($photos);
    $count = count($urls);
@endphp

@if ($count > 0)
    <div
        class="space-y-2.5"
        x-data="{
            index: 0,
            zoomed: false,
            photos: @js($urls),
            prev() { if (this.photos.length < 2) return; this.index = (this.index - 1 + this.photos.length) % this.photos.length },
            next() { if (this.photos.length < 2) return; this.index = (this.index + 1) % this.photos.length },
            go(i) { this.index = i },
            touchStartX: 0,
            onTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX },
            onTouchEnd(e) {
                const diff = e.changedTouches[0].screenX - this.touchStartX;
                if (Math.abs(diff) > 40) diff > 0 ? this.prev() : this.next();
            },
            onKey(e) {
                if (this.zoomed) {
                    if (e.key === 'Escape') this.zoomed = false;
                    if (e.key === 'ArrowLeft') this.prev();
                    if (e.key === 'ArrowRight') this.next();
                    return;
                }
                if (e.key === 'ArrowLeft') this.prev();
                if (e.key === 'ArrowRight') this.next();
            },
        }"
        @keydown.window="onKey($event)"
    >
        {{-- Main preview (cover / selected) --}}
        <div
            class="relative rounded-2xl overflow-hidden bg-slate-100 aspect-[4/3] max-h-[22rem] sm:max-h-[28rem] lg:max-h-none ring-1 ring-black/5 shadow-md select-none"
            @touchstart.passive="onTouchStart($event)"
            @touchend.passive="onTouchEnd($event)"
        >
            <img
                :src="photos[index]"
                alt=""
                loading="eager"
                decoding="async"
                fetchpriority="high"
                referrerpolicy="no-referrer"
                class="absolute inset-0 w-full h-full object-cover {{ $zoom ? 'cursor-zoom-in' : '' }}"
                @if ($zoom) @click="zoomed = true" @endif
            >

            <template x-if="photos.length > 1">
                <div>
                    <button
                        type="button"
                        @click.stop="prev()"
                        class="absolute left-2 top-1/2 -translate-y-1/2 z-20 size-8 rounded-full bg-white/95 shadow ring-1 ring-black/5 grid place-items-center text-gray-800 hover:bg-white text-lg leading-none"
                        aria-label="Previous photo"
                    >‹</button>
                    <button
                        type="button"
                        @click.stop="next()"
                        class="absolute right-2 top-1/2 -translate-y-1/2 z-20 size-8 rounded-full bg-white/95 shadow ring-1 ring-black/5 grid place-items-center text-gray-800 hover:bg-white text-lg leading-none"
                        aria-label="Next photo"
                    >›</button>
                    <div
                        class="absolute top-2 right-2 z-20 rounded-full bg-black/50 text-white text-[10px] font-semibold px-2 py-0.5 tabular-nums"
                        x-text="(index + 1) + ' / ' + photos.length"
                    ></div>
                </div>
            </template>
        </div>

        {{-- Bottom thumbnails — click to change main preview --}}
        @if ($count > 1)
            <div class="flex gap-2 overflow-x-auto pb-0.5" role="tablist" aria-label="Asset photos">
                <template x-for="(photo, i) in photos" :key="'thumb-' + i + '-' + photo">
                    <button
                        type="button"
                        role="tab"
                        @click="go(i)"
                        class="shrink-0 size-16 sm:size-20 lg:size-24 rounded-xl overflow-hidden ring-2 transition focus:outline-none focus-visible:ring-brand"
                        :class="index === i ? 'ring-brand opacity-100' : 'ring-gray-200 opacity-70 hover:opacity-100'"
                        :aria-selected="index === i"
                        :aria-label="'Photo ' + (i + 1)"
                    >
                        <img :src="photo" alt="" class="w-full h-full object-cover" referrerpolicy="no-referrer">
                    </button>
                </template>
            </div>
        @endif

        @if ($zoom)
            <template x-teleport="body">
                <div
                    x-show="zoomed"
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-0 z-[90] bg-black/85 flex items-center justify-center p-4"
                    @click.self="zoomed = false"
                    @touchstart.passive="onTouchStart($event)"
                    @touchend.passive="onTouchEnd($event)"
                >
                    <button
                        type="button"
                        class="absolute top-4 right-4 text-white/90 text-2xl font-semibold leading-none"
                        @click="zoomed = false"
                        aria-label="Close"
                    >×</button>
                    <template x-if="photos.length > 1">
                        <div>
                            <button
                                type="button"
                                @click.stop="prev()"
                                class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 size-10 rounded-full bg-white/15 text-white text-xl grid place-items-center hover:bg-white/25"
                                aria-label="Previous photo"
                            >‹</button>
                            <button
                                type="button"
                                @click.stop="next()"
                                class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 size-10 rounded-full bg-white/15 text-white text-xl grid place-items-center hover:bg-white/25"
                                aria-label="Next photo"
                            >›</button>
                        </div>
                    </template>
                    <img
                        :src="photos[index]"
                        alt=""
                        class="max-h-[85vh] max-w-[92vw] object-contain rounded-xl shadow-2xl"
                        referrerpolicy="no-referrer"
                        @click.stop
                    >
                    <div class="absolute bottom-5 left-1/2 -translate-x-1/2 flex gap-2" x-show="photos.length > 1">
                        <template x-for="(photo, i) in photos" :key="'zthumb-' + i">
                            <button type="button" @click.stop="go(i)"
                                    class="size-11 rounded-lg overflow-hidden ring-2"
                                    :class="index === i ? 'ring-amber-400' : 'ring-white/30 opacity-80'">
                                <img :src="photo" alt="" class="w-full h-full object-cover">
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        @endif
    </div>
@else
    <div class="aspect-[4/3] max-h-[22rem] sm:max-h-[28rem] lg:max-h-none rounded-2xl bg-gradient-to-br from-brand-muted to-brand/10 grid place-items-center text-6xl ring-1 ring-black/5">
        {{ marketplace_category_emoji($category) }}
    </div>
@endif
