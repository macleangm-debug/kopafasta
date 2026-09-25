@props(['photos' => [], 'category' => 'other', 'zoom' => false, 'share' => null])

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
            @if ($share) style="view-transition-name: {{ $share }}" @endif
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

            @if ($count > 1)
                <button
                    type="button"
                    @click.stop="prev()"
                    class="absolute left-2 top-1/2 -translate-y-1/2 z-20 size-11 rounded-full bg-brand text-white shadow-lg ring-2 ring-white/90 grid place-items-center text-3xl font-black leading-none hover:bg-brand-light"
                    aria-label="Previous photo"
                >‹</button>
                <button
                    type="button"
                    @click.stop="next()"
                    class="absolute right-2 top-1/2 -translate-y-1/2 z-20 size-11 rounded-full bg-brand text-white shadow-lg ring-2 ring-white/90 grid place-items-center text-3xl font-black leading-none hover:bg-brand-light"
                    aria-label="Next photo"
                >›</button>
                <div
                    class="absolute top-2 right-2 z-20 rounded-full bg-black/50 text-white text-[10px] font-semibold px-2 py-0.5 tabular-nums pointer-events-none"
                    x-text="(index + 1) + ' / ' + photos.length"
                ></div>
            @endif
        </div>

        <div class="sm:hidden flex items-center justify-center gap-1.5 pt-1" x-show="photos.length > 1">
            <template x-for="(photo, i) in photos" :key="'dot-' + i">
                <button type="button" @click="go(i)" class="size-2 rounded-full"
                        :class="index === i ? 'bg-brand scale-125' : 'bg-gray-300'"
                        :aria-label="'Photo ' + (i + 1)"></button>
            </template>
        </div>

        {{-- Desktop: additional images fit the cover width. No empty slots. --}}
        @if ($count > 1)
            <div class="hidden sm:grid gap-2" :style="`grid-template-columns: repeat(${Math.min(photos.length - 1, 6)}, minmax(0, 1fr))`" role="tablist" aria-label="Asset photos">
                <template x-for="(photo, i) in photos" :key="'thumb-' + i + '-' + photo">
                    <button
                        type="button"
                        role="tab"
                        x-show="i !== 0"
                        @click="go(i)"
                        class="aspect-[4/3] rounded-xl overflow-hidden ring-2 transition focus:outline-none focus-visible:ring-brand"
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
                    @if ($count > 1)
                        <button
                            type="button"
                            @click.stop="prev()"
                            class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 size-12 rounded-full bg-brand text-white text-3xl font-black grid place-items-center shadow-lg ring-2 ring-white/90 hover:bg-brand-light"
                            aria-label="Previous photo"
                        >‹</button>
                        <button
                            type="button"
                            @click.stop="next()"
                            class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 size-12 rounded-full bg-brand text-white text-3xl font-black grid place-items-center shadow-lg ring-2 ring-white/90 hover:bg-brand-light"
                            aria-label="Next photo"
                        >›</button>
                    @endif
                    <img
                        :src="photos[index]"
                        alt=""
                        class="max-h-[85vh] max-w-[92vw] object-contain rounded-xl shadow-2xl"
                        style="touch-action: pinch-zoom"
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
    <div class="aspect-[4/3] max-h-[22rem] sm:max-h-[28rem] lg:max-h-none rounded-2xl bg-gradient-to-br from-brand-muted to-brand/10 grid place-items-center text-6xl ring-1 ring-black/5"
         @if ($share) style="view-transition-name: {{ $share }}" @endif>
        {{ marketplace_category_emoji($category) }}
    </div>
@endif
