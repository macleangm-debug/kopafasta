@props([
    'name',
    'label' => null,
    'value' => null,
    'mode' => 'numeric',
    'min' => null,
    'max' => null,
    'step' => null,
    'required' => false,
    'help' => null,
    'money' => false,
    'decimals' => 0,
    'id' => null,
])

@php
    $inputId = $id ?: $name;
    $inputMode = ($money || $mode === 'decimal') ? 'decimal' : 'numeric';
    $pattern = ($money || $mode === 'decimal') ? '[0-9.,]*' : '[0-9]*';
    $rawValue = old($name, $value);
    $displayValue = $money
        ? \App\Support\MoneyFormat::forInput($rawValue, (int) $decimals)
        : $rawValue;
@endphp

<div @error($name) data-has-error="true" @enderror>
    @if ($label)
        <label for="{{ $inputId }}" class="block text-xs font-medium text-gray-600 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="text"
        inputmode="{{ $inputMode }}"
        pattern="{{ $pattern }}"
        autocomplete="off"
        value="{{ $displayValue }}"
        @if ($min !== null) min="{{ $min }}" @endif
        @if ($max !== null) max="{{ $max }}" @endif
        @if ($step !== null) step="{{ $step }}" @endif
        @if ($required) required @endif
        @if ($money) data-money-input="{{ (int) $decimals }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-brand/20 focus:border-brand px-3 py-2.5 text-sm tabular-nums']) }}
    >
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
