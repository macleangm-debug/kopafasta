@props([
    'title' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'actionHref' => null,
    'actionLabel' => null,
])

<div {{ $attributes->class(['kf-carousel-shell']) }}
     x-data="{
        scrollByCard(dir) {
            const track = this.$refs.track;
            if (!track) return;
            const slide = track.querySelector('[data-public-slide]');
            const step = (slide ? slide.getBoundingClientRect().width : 280) + 16;
            track.scrollBy({ left: dir * step, behavior: 'smooth' });
        }
     }">
    @if ($eyebrow || $title || $subtitle || ($actionHref && $actionLabel))
        <header class="kf-public-section__intro !mb-5">
            <div class="kf-public-section__copy min-w-0 flex-1">
                @if (filled($eyebrow))
                    <p class="kf-public-section__eyebrow">{{ $eyebrow }}</p>
                @endif
                @if (filled($title))
                    <h2 class="kf-public-section__title !text-xl sm:!text-2xl">{{ $title }}</h2>
                @endif
                @if (filled($subtitle))
                    <p class="kf-public-section__body">{{ $subtitle }}</p>
                @endif
            </div>
            @if (filled($actionHref) && filled($actionLabel))
                <a href="{{ $actionHref }}" class="kf-public-section__action shrink-0">{{ $actionLabel }}</a>
            @endif
        </header>
    @endif

    <div class="relative">
        <div x-ref="track" class="kf-carousel-track flex gap-4"
             style="-webkit-overflow-scrolling: touch;">
            {{ $slot }}
        </div>
        <x-site.carousel-control direction="prev" @click="scrollByCard(-1)" />
        <x-site.carousel-control direction="next" @click="scrollByCard(1)" />
    </div>
</div>
