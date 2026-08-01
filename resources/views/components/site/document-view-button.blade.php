@props([
    'url',
    'label' => null,
    'type' => null,
    'class' => 'text-amber-600 hover:underline text-sm font-semibold',
])

@php
    $label = $label ?? __('borrower.profile.view_document');
    $inferredType = $type ?? (str_ends_with(strtolower((string) $url), '.pdf') ? 'pdf' : 'image');
@endphp

<button type="button"
        {{ $attributes->merge(['class' => $class]) }}
        onclick="window.kfSiteOpenDocumentPreview(@js($url), @js($label), @js($inferredType))">
    {{ $slot->isEmpty() ? $label : $slot }}
</button>
