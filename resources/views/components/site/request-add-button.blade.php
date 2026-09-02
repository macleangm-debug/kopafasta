@props([
    'label' => null,
    'href' => null,
])

@php
    $label = $label ?: __('borrower.document_upload.add');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class('kf-request-add') }} aria-label="{{ $label }}">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
    </a>
@else
    <button type="button" {{ $attributes->class('kf-request-add') }} aria-label="{{ $label }}">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
    </button>
@endif
