@props([
    'name',
    'label',
    'configured' => false,
    'help' => null,
])

@php
    $replaceFlag = $name.'_replace';
    $startReplacing = (bool) old($replaceFlag) || ! $configured;
@endphp

<div
    x-data="{ replacing: {{ $startReplacing ? 'true' : 'false' }} }"
    class="space-y-2"
    data-secret-replace
    @error($name) data-has-error="true" @enderror
>
    <div class="flex items-center justify-between gap-2">
        <label for="{{ $name }}" class="block text-xs font-semibold text-gray-700">
            {{ $label }}
        </label>
        @if ($configured)
            <button type="button"
                    class="text-[11px] font-semibold text-brand hover:underline"
                    @click="replacing = !replacing; if (!replacing) { $refs.input.value = '' }">
                <span x-show="!replacing">Replace</span>
                <span x-show="replacing" x-cloak>Keep existing</span>
            </button>
        @endif
    </div>

    <div x-show="!replacing" x-cloak class="rounded-xl border border-brand/15 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-700 font-mono tracking-tight">
        •••••••••••••••• <span class="text-xs text-gray-500 font-sans">(saved — unchanged unless replaced)</span>
    </div>

    <div x-show="replacing" x-cloak>
        <input type="hidden" name="{{ $replaceFlag }}" value="1" :disabled="!replacing">
        <input
            x-ref="input"
            id="{{ $name }}"
            type="password"
            name="{{ $name }}"
            autocomplete="new-password"
            value=""
            :disabled="!replacing"
            placeholder="{{ $configured ? 'Enter a new value to replace' : 'Enter value' }}"
            class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition"
            {{ $attributes }}
        >
        @if ($help)
            <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
        @endif
    </div>

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
