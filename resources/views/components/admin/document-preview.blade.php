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
@else
    <button type="button"
            onclick="window.kfOpenDocumentPreview(@js($url), @js($label), @js($isPdf ? 'pdf' : 'image'))"
            class="text-xs font-semibold text-brand hover:text-brand-light bg-white ring-1 ring-brand/15 px-3 py-1.5 rounded-lg shrink-0">
        {{ $label }}
    </button>
@endif
