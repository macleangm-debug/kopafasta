@props([
    'name' => 'national_id',
    'value' => '',
    'required' => true,
    'placeholder' => 'XXXXXXXX-XXXXX-XXXXX-XX',
])

@php
    $displayValue = old($name, $value);
@endphp

<input
    {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono tracking-wide']) }}
    type="text"
    inputmode="numeric"
    autocomplete="off"
    autocorrect="off"
    spellcheck="false"
    maxlength="25"
    data-nida-input
    name="{{ $name }}"
    value="{{ $displayValue }}"
    @if ($required) required @endif
    placeholder="{{ $placeholder }}"
    pattern="[0-9]{8}-[0-9]{5}-[0-9]{5}-[0-9]{2}"
    title="{{ __('borrower.nida.format_hint') }}"
/>
