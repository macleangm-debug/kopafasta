@props([
    'size' => 'md',
    'variant' => 'dark', // dark = for light backgrounds; light = for dark/green backgrounds
    'showSubtitle' => false,
    'mark' => false, // true = square icon only
])

@php
    $sizes = [
        'sm' => ['img' => 'h-7', 'mark' => 'size-7', 'sub' => 'text-[10px]'],
        'md' => ['img' => 'h-9', 'mark' => 'size-9', 'sub' => 'text-[11px]'],
        'lg' => ['img' => 'h-11', 'mark' => 'size-11', 'sub' => 'text-xs'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $logoUrl = $mark
        ? (brand('logo_mark_url') ?: brand('logo_url'))
        : ($variant === 'light'
            ? (brand('logo_url_light') ?: brand('logo_url'))
            : brand('logo_url'));
    $subClass = $variant === 'light' ? 'text-white/70' : 'text-gray-500';
    $isSvg = $logoUrl && str_ends_with(strtolower(parse_url((string) $logoUrl, PHP_URL_PATH) ?: (string) $logoUrl), '.svg');
    // Full wordmark PNGs are black-on-white; on dark surfaces sit them on a light chip so they stay readable.
    $needsLightBackdrop = $logoUrl && ! $isSvg && $variant === 'light' && ! $mark;
    $imgWrapClass = $needsLightBackdrop ? 'inline-flex rounded-md bg-white px-1.5 py-0.5' : '';
    $imgClass = $mark ? ($s['mark'].' object-contain') : ($s['img'].' w-auto object-contain');
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 shrink-0']) }}>
    @if ($logoUrl)
        <div class="{{ $showSubtitle ? 'flex flex-col gap-1' : '' }}">
            <span class="{{ $imgWrapClass }}">
                <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}"
                     alt="{{ brand_name() }}"
                     class="{{ $imgClass }}"
                     @if ($mark) width="40" height="40" @else width="160" height="80" @endif>
            </span>
            @if ($showSubtitle)
                <span class="{{ $s['sub'] }} {{ $subClass }}">{{ brand('tagline') }}</span>
            @endif
        </div>
    @else
        <span class="size-9 grid place-items-center font-extrabold rounded-lg bg-gradient-to-br from-brand-light to-brand text-white shadow-sm text-lg">{{ brand('logo_letter', 'K') }}</span>
        <div class="leading-tight">
            <span class="font-bold tracking-tight text-lg {{ $variant === 'light' ? 'text-white' : 'text-gray-900' }}">{{ brand_name() }}</span>
            @if ($showSubtitle)
                <span class="block {{ $s['sub'] }} {{ $subClass }}">{{ brand('tagline') }}</span>
            @endif
        </div>
    @endif
</div>
