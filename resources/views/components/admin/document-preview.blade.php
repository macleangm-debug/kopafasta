@props([
    'url',
    'label' => 'Preview',
    'variant' => 'button',
])

@php
    $isPdf = str_contains(strtolower((string) $url), '.pdf');
@endphp

@if ($variant === 'link')
    <button type="button"
            onclick="window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="text-xs font-semibold text-brand hover:text-brand-light hover:underline">
        {{ $label }}
    </button>
@elseif ($variant === 'thumbnail')
    <button type="button"
            onclick="window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="group relative block w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden ring-1 ring-brand/15 bg-gray-100 shrink-0 text-left"
            title="{{ $label }}">
        @if ($isPdf)
            <span class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-brand-muted/40 text-brand">
                <span class="text-[10px] font-bold uppercase tracking-widest">PDF</span>
                <span class="text-[10px] font-semibold opacity-80 group-hover:underline">{{ $label }}</span>
            </span>
        @else
            <img src="{{ $url }}" alt="" class="w-full h-full object-cover">
            <span class="absolute inset-x-0 bottom-0 bg-black/55 text-white text-[10px] font-semibold text-center py-1 opacity-0 group-hover:opacity-100 transition">{{ $label }}</span>
        @endif
    </button>
@else
    <button type="button"
            onclick="window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="text-xs font-semibold text-brand hover:text-brand-light bg-white ring-1 ring-brand/15 px-3 py-1.5 rounded-lg shrink-0">
        {{ $label }}
    </button>
@endif
