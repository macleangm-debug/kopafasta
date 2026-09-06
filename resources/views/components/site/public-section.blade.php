@props([
    'tone' => 'white', // white | muted | brand-soft
    'narrow' => false,
    'pad' => true,
])

@php
    $bg = match ($tone) {
        'muted' => 'bg-[#f7faf8]',
        'brand-soft' => 'premium-gradient',
        default => 'bg-white',
    };
    $width = $narrow ? 'max-w-3xl' : 'max-w-7xl';
@endphp

<section {{ $attributes->class([$bg, 'border-y border-brand/5' => $tone !== 'white']) }}>
    <div @class([$width, 'mx-auto px-4 sm:px-6 lg:px-8', 'py-10 lg:py-14' => $pad])>
        {{ $slot }}
    </div>
</section>
