@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'help' => null,
    'money' => false,
    'decimals' => 0,
])

@if ($money)
    <x-admin.money-input
        :name="$name"
        :label="$label"
        :value="$value"
        :placeholder="$placeholder"
        :required="$required"
        :help="$help"
        :decimals="$decimals"
        {{ $attributes->except(['money', 'decimals', 'type', 'step']) }}
    />
@else
<div @error($name) data-has-error="true" @enderror>
    @if ($label)
        <label for="{{ $name }}" class="block text-xs font-semibold text-gray-700 mb-1">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'w-full text-sm bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 placeholder:text-gray-400 hover:border-gray-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition']) }}
    >
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
@endif
