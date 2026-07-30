@props(['photos' => [], 'category' => 'other', 'zoom' => false])

@php
    $urls = marketplace_photo_urls($photos);
@endphp

@if (count($urls) > 0)
    <div x-data="{
        index: 0,
        zoomed: false,
        photos: @js($urls),
        prev() { this.index = (this.index - 1 + this.photos.length) % this.photos.length },
        next() { this.index = (this.index + 1) % this.photos.length },
        touchStartX: 0,
        onTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX },
        onTouchEnd(e) {
            const diff = e.changedTouches[0].screenX - this.touchStartX;
            if (Math.abs(diff) > 50) diff > 0 ? this.prev() : this.next();
        }
    }">
        <div class="relative rounded-2xl overflow-hidden bg-slate-100 aspect-[4/3] sm:aspect-[16/10] max-h-72 ring-1 ring-white/50 shadow-lg"
             @touchstart="onTouchStart($event)" @touchend="onTouchEnd($event)">
            <template x-for="(photo, i) in photos" :key="photo">
                <img :src="photo" alt="" @if($zoom) @click="zoomed = !zoomed" @endif
                     referrerpolicy="no-referrer"
                     class="absolute inset-0 w-full h-full object-cover transition-all duration-300 {{ $zoom ? 'cursor-zoom-in' : '' }}"
                     :class="[
                        i === index ? 'opacity-100 z-10' : 'opacity-0 z-0',
                        zoomed && i === index ? 'scale-150 cursor-zoom-out' : 'scale-100'
                     ]">
            </template>

            <template x-if="photos.length > 1">
                <div>
                    <button type="button" @click="prev()"
                            class="absolute left-3 top-1/2 -translate-y-1/2 z-20 size-10 rounded-full glass-card grid place-items-center text-gray-800 hover:bg-white"
                            aria-label="Previous photo">‹</button>
                    <button type="button" @click="next()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 z-20 size-10 rounded-full glass-card grid place-items-center text-gray-800 hover:bg-white"
                            aria-label="Next photo">›</button>
                    <div class="absolute bottom-3 inset-x-0 z-20 flex justify-center gap-1.5">
                        <template x-for="(photo, i) in photos" :key="'dot-' + photo">
                            <button type="button" @click="index = i"
                                    class="size-2 rounded-full transition"
                                    :class="i === index ? 'bg-white scale-125' : 'bg-white/50'"
                                    :aria-label="'Photo ' + (i + 1)"></button>
                        </template>
                    </div>
                    <div class="absolute top-3 right-3 z-20 rounded-full bg-black/40 text-white text-xs px-2.5 py-1 backdrop-blur"
                         x-text="(index + 1) + ' / ' + photos.length"></div>
                </div>
            </template>
        </div>

        @if (count($urls) > 1)
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <template x-for="(photo, i) in photos" :key="'thumb-' + photo">
                    <button type="button" @click="index = i"
                            class="shrink-0 size-16 rounded-xl overflow-hidden ring-2 transition"
                            :class="index === i ? 'ring-brand' : 'ring-transparent opacity-70 hover:opacity-100'">
                        <img :src="photo" alt="" class="w-full h-full object-cover" referrerpolicy="no-referrer">
                    </button>
                </template>
            </div>
        @endif
    </div>
@else
    <div class="aspect-[4/3] rounded-3xl bg-gradient-to-br from-brand-muted to-brand/10 grid place-items-center text-6xl ring-1 ring-white/50">
        {{ marketplace_category_emoji($category) }}
    </div>
@endif
