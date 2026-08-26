@props([
    'name',
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'help' => null,
    'decimals' => 0,
])

@php
    $displayValue = \App\Support\MoneyFormat::forInput(old($name, $value), (int) $decimals);
@endphp

<div @error($name) data-has-error="true" @enderror>
    @if ($label)
        <label for="{{ $name }}" class="block text-xs font-semibold text-gray-700 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        value="{{ $displayValue }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        data-money-input="{{ (int) $decimals }}"
        {{ $attributes->merge(['class' => 'w-full text-lg sm:text-xl font-semibold tabular-nums bg-white border border-brand/15 rounded-xl shadow-sm px-4 py-3.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition']) }}
    >
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
