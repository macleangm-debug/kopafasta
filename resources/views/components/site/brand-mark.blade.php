@props([
    'size' => 'md',
    'variant' => 'dark',
    'showSubtitle' => false,
])

@php
    $sizes = [
        'sm' => ['img' => 'h-7', 'sub' => 'text-[10px]'],
        'md' => ['img' => 'h-9', 'sub' => 'text-[11px]'],
        'lg' => ['img' => 'h-11', 'sub' => 'text-xs'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $logoUrl = $variant === 'light'
        ? (brand('logo_url_light') ?: brand('logo_url'))
        : brand('logo_url');
    $subClass = $variant === 'light' ? 'text-white/70' : 'text-gray-500';
    $isSvg = $logoUrl && str_ends_with(strtolower($logoUrl), '.svg');
    $needsLightBackdrop = $logoUrl && ! $isSvg && $variant === 'light';
    $imgWrapClass = $needsLightBackdrop ? 'inline-flex rounded-md bg-white px-1.5 py-0.5' : '';
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 shrink-0']) }}>
    @if ($logoUrl)
        <div class="{{ $showSubtitle ? 'flex flex-col gap-1' : '' }}">
            <span class="{{ $imgWrapClass }}">
                <img src="{{ asset($logoUrl) }}"
                     alt="{{ brand_name() }}"
                     class="{{ $s['img'] }} w-auto object-contain"
                     width="160"
                     height="40">
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
