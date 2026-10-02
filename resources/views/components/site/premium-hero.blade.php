{{--
  Shared Kopafasta premium glass hero.
  One-time glare sweep on first paint (session-keyed); respects prefers-reduced-motion.
--}}
@props([
    'eyebrow' => null,
    'title' => '',
    'subtitle' => null,
    'variant' => 'brand', // brand | orange | red | slate | bronze
])

@php
    $panel = match ($variant) {
        'orange' => 'kf-premium-panel-orange',
        'red' => 'kf-premium-panel-red',
        'slate' => 'kf-premium-panel-slate',
        'bronze' => 'kf-premium-panel-bronze',
        default => 'kf-premium-panel',
    };
@endphp

<section {{ $attributes->class(['relative overflow-hidden rounded-2xl kf-glass-hero kf-hero-enter', $panel]) }}
         data-kf-glass-hero
         x-data
         x-init="
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            $el.classList.add('kf-glass-hero--glare');
         ">
    <x-site.glass-hero-surface />
    <div class="relative z-[1] px-5 sm:px-6 py-6 sm:py-7 text-white">
        @if (filled($eyebrow))
            <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">{{ $eyebrow }}</p>
        @endif
        @if (filled($title))
            <h1 class="text-2xl sm:text-3xl font-bold mt-1 tracking-tight">{{ $title }}</h1>
        @endif
        @if (filled($subtitle))
            <p class="text-sm text-white/75 mt-2 max-w-2xl">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
