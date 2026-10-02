@props([
    'title' => null,
    'body' => null,
    'label' => null,
    'url' => null,
    'compact' => false,
])

@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $title = $title ?: ($isSw ? 'Pata huduma zote za mwanachama' : 'Get the full member experience');
    $body = $body ?: ($isSw
        ? 'Fungua akaunti yako ya Kopafasta na upate huduma zote zinazopatikana kwa wanachama.'
        : 'Open your Kopafasta account and access every member service in one place.');
    $label = $label ?: ($isSw ? 'Anza Sasa' : 'Get started');
    $url = $url ?: route('site.register.borrower');
@endphp

<div {{ $attributes->class([
    'rounded-2xl bg-gradient-to-br from-brand-muted/70 to-white ring-1 ring-brand/15 shadow-sm',
    $compact ? 'p-3.5' : 'p-4 sm:p-5',
]) }}>
    <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-brand">Kopafasta</p>
    <h3 class="mt-1 text-sm sm:text-base font-bold text-gray-900 leading-snug">{{ $title }}</h3>
    <p class="mt-1 text-xs sm:text-sm text-gray-600 leading-relaxed">{{ $body }}</p>
    <a href="{{ $url }}"
       class="mt-3 inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand font-bold text-sm px-5 py-2.5 shadow-sm transition">
        {{ $label }}
    </a>
</div>
