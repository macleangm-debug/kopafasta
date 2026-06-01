@props([
    'size' => 'md',
    'variant' => 'dark',
    'showSubtitle' => false,
])

@php
    $sizes = [
        'sm' => ['box' => 'size-7 text-sm rounded-md', 'name' => 'text-sm', 'sub' => 'text-[10px]'],
        'md' => ['box' => 'size-9 text-lg rounded-lg', 'name' => 'text-lg', 'sub' => 'text-[11px]'],
        'lg' => ['box' => 'size-11 text-xl rounded-xl', 'name' => 'text-xl', 'sub' => 'text-xs'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $boxClass = $variant === 'light'
        ? 'bg-white text-amber-700 shadow'
        : 'bg-gradient-to-br from-amber-400 to-amber-600 text-gray-900 shadow-sm';
    $nameClass = $variant === 'light' ? 'text-white' : 'text-gray-900';
    $subClass = $variant === 'light' ? 'text-white/70' : 'text-gray-500';
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 shrink-0']) }}>
    <span class="{{ $s['box'] }} grid place-items-center font-extrabold {{ $boxClass }}">{{ brand('logo_letter', 'K') }}</span>
    <div class="leading-tight">
        <span class="font-bold tracking-tight {{ $s['name'] }} {{ $nameClass }}">{{ brand_name() }}</span>
        @if ($showSubtitle)
            <span class="block {{ $s['sub'] }} {{ $subClass }}">{{ brand('tagline') }}</span>
        @endif
    </div>
</div>
