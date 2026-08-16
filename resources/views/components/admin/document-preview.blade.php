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
        <span class="absolute top-1.5 right-1.5 size-7 grid place-items-center rounded-full bg-black/55 text-white" aria-hidden="true">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
        </span>
    </button>
@elseif ($variant === 'icon')
    <button type="button"
            onclick="event.stopPropagation(); window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="size-9 grid place-items-center rounded-full bg-black/55 hover:bg-black/75 text-white shadow-sm"
            title="{{ $label }}"
            aria-label="{{ $label }}">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
    </button>
@else
    <button type="button"
            onclick="window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="text-xs font-semibold text-brand hover:text-brand-light bg-white ring-1 ring-brand/15 px-3 py-1.5 rounded-lg shrink-0">
        {{ $label }}
    </button>
@endif
