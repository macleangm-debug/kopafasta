@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->class(['space-y-4']) }}
     x-data="{
        scrollByCard(dir) {
            const track = this.$refs.track;
            if (!track) return;
            const slide = track.querySelector('[data-public-slide]');
            const step = (slide ? slide.getBoundingClientRect().width : 280) + 16;
            track.scrollBy({ left: dir * step, behavior: 'smooth' });
        }
     }">
    @if ($title || $subtitle)
        <div class="flex items-end justify-between gap-4">
            <div>
                @if ($title)<h2 class="text-xl sm:text-2xl font-bold text-gray-900">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="mt-1 text-sm text-gray-600 max-w-2xl">{{ $subtitle }}</p>@endif
            </div>
            <div class="hidden sm:flex gap-2 shrink-0">
                <button type="button" @click="scrollByCard(-1)" class="size-9 rounded-full ring-1 ring-brand/20 bg-white text-brand font-bold" aria-label="Previous">‹</button>
                <button type="button" @click="scrollByCard(1)" class="size-9 rounded-full ring-1 ring-brand/20 bg-white text-brand font-bold" aria-label="Next">›</button>
            </div>
        </div>
    @endif
    <div x-ref="track" class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory scroll-smooth scrollbar-none -mx-4 px-4 sm:mx-0 sm:px-0"
         style="-webkit-overflow-scrolling: touch;">
        {{ $slot }}
    </div>
    <div class="flex sm:hidden justify-center gap-2">
        <button type="button" @click="scrollByCard(-1)" class="size-9 rounded-full ring-1 ring-brand/20 bg-white text-brand font-bold" aria-label="Previous">‹</button>
        <button type="button" @click="scrollByCard(1)" class="size-9 rounded-full ring-1 ring-brand/20 bg-white text-brand font-bold" aria-label="Next">›</button>
    </div>
</div>
