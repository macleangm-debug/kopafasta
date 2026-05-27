@props([
    'name',
    'label' => null,
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'value' => null,
])
<div>
    @if ($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $value ?? old($name) }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm outline-none transition']) }}
    >
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
