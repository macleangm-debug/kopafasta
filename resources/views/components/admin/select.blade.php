@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'help' => null,
])

@php
    $current = old($name, $value);
    $optionItems = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $optionsAreList = array_is_list($optionItems);
@endphp

<div @error($name) data-has-error="true" @enderror>
    @if ($label)
        <label for="{{ $name }}" class="block text-xs font-semibold text-gray-700 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <div class="relative">
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $attributes->merge(['class' => 'appearance-none w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm pl-3.5 pr-9 py-2.5 font-medium text-gray-700 cursor-pointer hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition']) }}
        >
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($optionItems as $key => $optionLabel)
                @php($optValue = $optionsAreList ? $optionLabel : $key)
                <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
