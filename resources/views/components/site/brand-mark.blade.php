@props([
    'size' => 'md',
    'variant' => 'dark', // dark = for light backgrounds; light = for dark/green backgrounds
    'showSubtitle' => false,
    'mark' => false, // true = square icon only
])

@php
    /*
     * Brand system: official icon (PNG/SVG) + CSS wordmark.
     * Icon height is matched to the wordmark so they read as one lockup.
     */
    $sizes = [
        // icon height tracks the CSS wordmark; mark-only is a touch larger for presence
        'sm' => ['icon' => 'h-5', 'mark' => 'h-7', 'text' => 'text-[15px]', 'sub' => 'text-[10px]', 'gap' => 'gap-1.5'],
        'md' => ['icon' => 'h-7', 'mark' => 'h-9', 'text' => 'text-xl', 'sub' => 'text-[11px]', 'gap' => 'gap-2'],
        'lg' => ['icon' => 'h-9', 'mark' => 'h-11', 'text' => 'text-2xl', 'sub' => 'text-xs', 'gap' => 'gap-2.5'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];

    $markUrl = brand('logo_mark_url') ?: '/images/brand/kopafasta-mark.png';
    $useMarkOnly = (bool) $mark;
    $textClass = $variant === 'light' ? 'text-white' : 'text-gray-900';
    $subClass = $variant === 'light' ? 'text-white/70' : 'text-gray-500';
    $iconClass = ($useMarkOnly ? $s['mark'] : $s['icon']).' w-auto object-contain';
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center shrink-0']) }}>
    @if ($useMarkOnly)
        <img src="{{ asset(ltrim((string) $markUrl, '/')) }}"
             alt="{{ brand_name() }}"
             class="{{ $iconClass }}"
             width="40" height="40">
    @else
        <div class="{{ $showSubtitle ? 'flex flex-col gap-0.5' : '' }}">
            <span class="inline-flex items-center {{ $s['gap'] }}">
                <img src="{{ asset(ltrim((string) $markUrl, '/')) }}"
                     alt=""
                     aria-hidden="true"
                     class="{{ $iconClass }}"
                     width="40" height="40">
                <span class="font-bold tracking-tight leading-none {{ $s['text'] }} {{ $textClass }}">{{ brand_name() }}</span>
            </span>
            @if ($showSubtitle)
                <span class="{{ $s['sub'] }} {{ $subClass }}">{{ brand('tagline') }}</span>
            @endif
        </div>
    @endif
</div>
