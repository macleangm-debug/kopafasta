@props([
    'url',
    'label' => 'Preview',
    'variant' => 'button',
    'type' => null,
])

@php
    $urlString = strtolower((string) $url);
    $isPdf = $type === 'pdf'
        || str_contains($urlString, '.pdf')
        || str_contains($urlString, 'loan-agreements')
        || str_contains($urlString, 'rejection-letter')
        || str_contains($urlString, 'final-contract');
    $iconClass = $attributes->get('class') ?: 'h-16 w-16 sm:h-24 sm:w-24';
@endphp

@if ($variant === 'link')
    <button type="button"
            onclick="window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="text-xs font-semibold text-brand hover:text-brand-light hover:underline">
        {{ $label }}
    </button>
@elseif ($variant === 'file-icon')
    <span {{ $attributes->merge(['class' => $iconClass.' relative block rounded-xl overflow-hidden ring-1 ring-brand/15 bg-white shrink-0']) }} title="{{ $label }}">
        @if ($isPdf)
            <span class="absolute inset-0 flex flex-col items-center justify-center text-brand">
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span class="text-[10px] font-bold mt-1">PDF</span>
            </span>
        @else
            <img src="{{ $url }}" alt="" class="w-full h-full object-cover">
        @endif
    </span>
@elseif ($variant === 'thumbnail')
    <button type="button"
            onclick="window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="group relative block h-16 w-16 sm:h-24 sm:w-24 rounded-xl overflow-hidden ring-1 ring-brand/15 bg-white shrink-0 text-left"
            title="{{ $label }}">
        @if ($isPdf)
            <span class="absolute inset-0 flex flex-col items-center justify-center text-brand">
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span class="text-[10px] font-bold mt-1">PDF</span>
            </span>
        @else
            <img src="{{ $url }}" alt="" class="w-full h-full object-cover">
            <span class="absolute inset-x-0 bottom-0 bg-black/55 text-white text-[10px] font-semibold text-center py-1 opacity-0 group-hover:opacity-100 transition">{{ $label }}</span>
        @endif
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
