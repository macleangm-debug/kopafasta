@props([
    'title' => null,
    'eyebrow' => null,
    'href' => null,
    'cta' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['group relative flex flex-col h-full rounded-2xl bg-white ring-1 ring-brand/10 p-5 sm:p-6 shadow-[0_12px_30px_rgba(8,47,39,0.05)] hover:ring-brand/25 transition']) }}>
    <span class="text-brand-gold font-black tracking-[-0.14em] text-lg" aria-hidden="true">›››</span>
    @if ($eyebrow)
        <p class="mt-2 text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $eyebrow }}</p>
    @endif
    @if ($title)
        <h3 class="mt-2 text-lg font-bold text-gray-900">{{ $title }}</h3>
    @endif
    <div class="mt-2 text-sm text-gray-600 leading-relaxed flex-1">{{ $slot }}</div>
    @if ($cta)
        <p class="mt-4 text-sm font-bold text-brand group-hover:underline">{{ $cta }} →</p>
    @endif
</{{ $tag }}>
