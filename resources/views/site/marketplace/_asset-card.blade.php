@props([
    'asset',
    'categories' => [],
    'showUrl',
    'applyUrl' => null,
    'authenticated' => false,
])

@php
    $photoUrls = marketplace_photo_urls($asset['photos'] ?? []);
    $photoCount = count($photoUrls);
@endphp

<article class="glass-card overflow-hidden flex flex-col h-full hover:shadow-[0_16px_48px_rgba(0,77,64,0.12)] hover:-translate-y-0.5 transition-all duration-300 group"
         @if (! empty($asset['id'])) data-kf-share="kf-mp-{{ $asset['id'] }}" @endif>
    <div class="relative overflow-hidden bg-slate-50"
         @if ($photoCount > 0)
         x-data="{
            index: 0,
            imgLoaded: false,
            photos: @js($photoUrls),
            prev() { if (this.photos.length < 2) return; this.imgLoaded = false; this.index = (this.index - 1 + this.photos.length) % this.photos.length },
            next() { if (this.photos.length < 2) return; this.imgLoaded = false; this.index = (this.index + 1) % this.photos.length },
            touchStartX: 0,
            onTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX },
            onTouchEnd(e) {
                const diff = e.changedTouches[0].screenX - this.touchStartX;
                if (Math.abs(diff) > 40) diff > 0 ? this.prev() : this.next();
            },
         }"
         @endif>
        @if ($photoCount > 0)
            <a href="{{ $showUrl }}" class="block relative aspect-[4/3]"
               @touchstart.passive="onTouchStart($event)"
               @touchend.passive="onTouchEnd($event)">
                <div x-show="!imgLoaded" class="absolute inset-0 skeleton z-10"></div>
                <img :src="photos[index]" alt="{{ $asset['title'] }}" loading="lazy" decoding="async" referrerpolicy="no-referrer"
                     x-on:load="imgLoaded = true"
                     x-on:error="imgLoaded = true"
                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-[1.03] transition-all duration-500"
                     :class="imgLoaded ? 'opacity-100' : 'opacity-0'">
            </a>
            @if ($photoCount > 1)
                <button type="button" @click.stop.prevent="prev()"
                        class="absolute left-2 top-1/2 -translate-y-1/2 z-20 size-8 rounded-full bg-white/95 shadow ring-1 ring-black/5 grid place-items-center text-gray-800 hover:bg-white text-lg leading-none"
                        aria-label="Previous photo">‹</button>
                <button type="button" @click.stop.prevent="next()"
                        class="absolute right-2 top-1/2 -translate-y-1/2 z-20 size-8 rounded-full bg-white/95 shadow ring-1 ring-black/5 grid place-items-center text-gray-800 hover:bg-white text-lg leading-none"
                        aria-label="Next photo">›</button>
                <div class="absolute top-2 right-2 z-20 rounded-full bg-black/50 text-white text-[10px] font-semibold px-2 py-0.5 tabular-nums"
                     x-text="(index + 1) + ' / ' + photos.length"></div>
                <div class="absolute bottom-2 left-1/2 -translate-x-1/2 z-20 flex gap-1">
                    <template x-for="(photo, i) in photos" :key="'dot-' + i">
                        <button type="button" @click.stop.prevent="imgLoaded = false; index = i"
                                class="size-1.5 rounded-full transition"
                                :class="i === index ? 'bg-white' : 'bg-white/50'"
                                :aria-label="'Photo ' + (i + 1)"></button>
                    </template>
                </div>
            @endif
        @else
            <a href="{{ $showUrl }}" class="block aspect-[4/3] bg-gradient-to-br from-brand-muted to-brand/10 grid place-items-center text-5xl">
                {{ marketplace_category_emoji($asset['category'] ?? '') }}
            </a>
        @endif
        <span class="absolute top-3 left-3 rounded-full bg-white/95 backdrop-blur px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-brand shadow-sm z-20">
            {{ $categories[$asset['category'] ?? ''] ?? ($asset['category'] ?? '') }}
        </span>
        @if (! empty($asset['asset_value']))
            <span class="absolute bottom-3 right-3 rounded-lg bg-brand/90 backdrop-blur text-white text-xs font-bold px-2.5 py-1 tabular-nums shadow-sm z-20">
                {{ format_money($asset['asset_value'], false, 0) }}
            </span>
        @endif
    </div>
    <div class="p-4 flex-1 flex flex-col gap-2">
        <div class="min-w-0">
            <a href="{{ $showUrl }}" class="font-bold text-base text-gray-900 leading-snug line-clamp-2 group-hover:text-brand transition">{{ $asset['title'] }}</a>
            @if (! empty($asset['vendor']))
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                    {{ $asset['vendor'] }}
                    @if (! empty($asset['supplier_region']))
                        <span class="text-gray-400">· {{ $asset['supplier_region'] }}</span>
                    @endif
                </p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl bg-brand-muted/60 px-3 py-2.5 min-h-[4.25rem] flex flex-col justify-center">
                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('borrower.marketplace.deposit') }}</p>
                <p class="font-bold text-brand tabular-nums mt-0.5 break-words leading-snug">{{ format_money($asset['deposit'], false, 0) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-3 py-2.5 ring-1 ring-gray-100 min-h-[4.25rem] flex flex-col justify-center">
                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</p>
                <p class="font-bold text-gray-900 tabular-nums mt-0.5 break-words leading-snug">{{ format_money($asset['weekly_installment'], false, 0) }}</p>
            </div>
        </div>

        @if (! empty($asset['max_tenure_months']))
            <p class="text-[11px] text-gray-500 min-h-[1.25rem]">
                {{ __('borrower.marketplace.duration_range', ['min' => 1, 'max' => (int) $asset['max_tenure_months']]) }}
            </p>
        @else
            <p class="text-[11px] text-transparent min-h-[1.25rem]" aria-hidden="true">—</p>
        @endif

        <a href="{{ $showUrl }}" class="mt-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2.5 transition-all">
            {{ __('borrower.marketplace.view_details') }}
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
        </a>
    </div>
</article>
