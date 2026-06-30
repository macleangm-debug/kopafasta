@props(['photos' => [], 'category' => 'other'])

@php
    $urls = collect($photos)->map(fn ($path) => Storage::url($path))->values()->all();
@endphp

@if (count($urls) > 0)
    <div class="relative rounded-2xl overflow-hidden bg-slate-100 aspect-[4/3]"
         x-data="{ index: 0, photos: @js($urls), prev() { this.index = (this.index - 1 + this.photos.length) % this.photos.length }, next() { this.index = (this.index + 1) % this.photos.length } }">
        <template x-for="(photo, i) in photos" :key="photo">
            <img :src="photo" alt="" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300"
                 :class="i === index ? 'opacity-100 z-10' : 'opacity-0 z-0'">
        </template>

        <template x-if="photos.length > 1">
            <div>
                <button type="button" @click="prev()"
                        class="absolute left-3 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 text-gray-800 shadow grid place-items-center hover:bg-white"
                        aria-label="Previous photo">‹</button>
                <button type="button" @click="next()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 text-gray-800 shadow grid place-items-center hover:bg-white"
                        aria-label="Next photo">›</button>
                <div class="absolute bottom-3 inset-x-0 z-20 flex justify-center gap-1.5">
                    <template x-for="(photo, i) in photos" :key="'dot-' + photo">
                        <button type="button" @click="index = i"
                                class="size-2 rounded-full transition"
                                :class="i === index ? 'bg-white' : 'bg-white/50'"
                                :aria-label="'Photo ' + (i + 1)"></button>
                    </template>
                </div>
            </div>
        </template>
    </div>
@else
    <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 grid place-items-center text-6xl">
        {{ marketplace_category_emoji($category) }}
    </div>
@endif
