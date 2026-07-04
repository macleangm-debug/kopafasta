@props([
    'title' => '',
    'open' => 'pickerOpen',
    'triggerLabel' => '',
    'triggerValue' => '',
    'showOnDesktop' => false,
])

@php
    $visibility = $showOnDesktop ? '' : 'lg:hidden';
@endphp

<div class="{{ $visibility }}">
    <button type="button"
            @click="{{ $open }} = true"
            class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
        <span class="flex-1 text-left truncate">{{ $triggerValue ?: $triggerLabel }}</span>
        <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
    </button>

    <x-site.bottom-sheet :title="$title" :open="$open">
        {{ $slot }}
    </x-site.bottom-sheet>
</div>
