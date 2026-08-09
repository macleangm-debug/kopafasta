@props([
    'size' => 'md',
    'variant' => 'dark', // dark = for light backgrounds; light = for dark/green backgrounds
    'showSubtitle' => false,
    'mark' => false, // true = square icon only
    'portal' => null, // e.g. "Borrower's Portal" — shown under the lockup
])

@php
    /*
     * Brand system: official icon (PNG) + CSS wordmark.
     * Icon height is matched to the wordmark so they read as one lockup.
     */
    $sizes = [
        'sm' => ['icon' => 'h-5', 'mark' => 'h-7', 'text' => 'text-[15px]', 'sub' => 'text-[10px]', 'portal' => 'text-[10px]', 'gap' => 'gap-1.5'],
        'md' => ['icon' => 'h-6', 'mark' => 'h-9', 'text' => 'text-lg', 'sub' => 'text-[11px]', 'portal' => 'text-[11px]', 'gap' => 'gap-2'],
        'lg' => ['icon' => 'h-8', 'mark' => 'h-11', 'text' => 'text-xl', 'sub' => 'text-xs', 'portal' => 'text-xs', 'gap' => 'gap-2.5'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];

    $markUrl = brand('logo_mark_url') ?: '/images/brand/kopafasta-mark.png';
    $useMarkOnly = (bool) $mark;
    $textClass = $variant === 'light' ? 'text-white' : 'text-gray-900';
    $subClass = $variant === 'light' ? 'text-white/70' : 'text-gray-500';
    $portalClass = $variant === 'light' ? 'text-brand-gold' : 'text-brand';
    $iconClass = ($useMarkOnly ? $s['mark'] : $s['icon']).' w-auto object-contain shrink-0';
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex flex-col items-start shrink-0 min-w-0']) }}>
    @if ($useMarkOnly)
        <img src="{{ asset(ltrim((string) $markUrl, '/')) }}"
             alt="{{ brand_name() }}"
             class="{{ $iconClass }}"
             width="40" height="40">
    @else
        <span class="inline-flex items-center {{ $s['gap'] }} min-w-0">
            <img src="{{ asset(ltrim((string) $markUrl, '/')) }}"
                 alt=""
                 aria-hidden="true"
                 class="{{ $iconClass }}"
                 width="40" height="40">
            <span class="font-bold tracking-tight leading-none {{ $s['text'] }} {{ $textClass }} truncate">{{ brand_name() }}</span>
        </span>
    @endif

    @if ($portal)
        <span class="mt-1.5 {{ $s['portal'] }} font-semibold uppercase tracking-[0.14em] {{ $portalClass }} leading-tight">
            {{ $portal }}
        </span>
    @elseif ($showSubtitle)
        <span class="mt-1 {{ $s['sub'] }} {{ $subClass }}">{{ brand('tagline') }}</span>
    @endif
</div>
