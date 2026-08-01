@props([
    'title',
    'icon' => null,
    'editing' => false,
    'editUrl' => null,
    'complete' => null,
    'empty' => false,
    'addUrl' => null,
    'addLabel' => null,
    'sectionId' => null,
    'inlineEdit' => true,
    'defaultOpen' => false,
    'allowOverflow' => false,
    'collapsible' => true,
])

@php
    $hasForm = isset($form);
    $useInline = $inlineEdit && ($hasForm || $editing) && ! $editUrl;
    $startOpen = $editing || $defaultOpen;
    // Collapse completed sections by default to keep long pages short.
    $startExpanded = $defaultOpen || $empty || $complete !== true;
@endphp

<div
    @if ($sectionId) id="{{ $sectionId }}" @endif
    class="glass-card scroll-mt-24 {{ ($allowOverflow ?? false) ? 'overflow-visible' : 'overflow-hidden' }}"
    x-data="{ open: @js($startOpen), expanded: @js($startExpanded) }"
    @if ($sectionId)
        x-init="if (window.location.hash === '#{{ $sectionId }}') { open = true; expanded = true }"
    @endif
>
    <div class="flex items-start justify-between gap-3 px-5 sm:px-6 py-4 border-b border-gray-100/80">
        <button type="button"
                @if ($collapsible)
                    @click="if (!open) expanded = !expanded"
                @endif
                class="flex items-start gap-3 min-w-0 text-left flex-1">
            @if ($icon)
                <span class="text-2xl leading-none shrink-0 mt-0.5" aria-hidden="true">{{ $icon }}</span>
            @endif
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-900 inline-flex items-center gap-2">
                    <span>{{ $title }}</span>
                    @if ($collapsible && $useInline)
                        <svg class="size-4 text-gray-400 transition" :class="expanded || open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    @endif
                </h2>
                @if ($complete === true)
                    <p class="text-xs text-emerald-700 mt-1">{{ __('borrower.profile.section_complete') }}</p>
                @elseif ($complete === false)
                    <p class="text-xs text-amber-700 mt-1">{{ __('borrower.profile.section_incomplete') }}</p>
                @endif
            </div>
        </button>
        @if ($useInline)
            <button type="button"
                    @click="open = !open; if (open) expanded = true"
                    class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-full ring-1 transition"
                    :class="open ? 'text-gray-700 ring-gray-200 bg-gray-50' : 'text-amber-700 ring-amber-200 bg-amber-50 hover:text-amber-800'">
                <span x-show="!open">{{ $empty ? ($addLabel ?? __('borrower.profile.add_details')) : __('borrower.profile.edit_section') }}</span>
                <span x-show="open" x-cloak>{{ __('borrower.profile.cancel_edit') }}</span>
            </button>
        @elseif (! $editing && $editUrl)
            <a href="{{ $editUrl }}"
               class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 hover:text-amber-800 px-3 py-1.5 rounded-full ring-1 ring-amber-200 bg-amber-50">
                {{ $empty ? ($addLabel ?? __('borrower.profile.add_details')) : __('borrower.profile.edit_section') }}
            </a>
        @endif
    </div>

    @if ($useInline)
        <div x-show="!open && expanded" class="p-5 sm:p-6">
            @if ($empty && $addUrl)
                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                    <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                    <button type="button" @click="open = true"
                            class="inline-flex mt-4 items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                        {{ $addLabel ?? __('borrower.profile.add_details') }}
                    </button>
                </div>
            @else
                {{ $view ?? $slot }}
            @endif
        </div>
        <div x-show="!open && !expanded" x-cloak class="px-5 sm:px-6 py-3">
            <button type="button" @click="expanded = true" class="text-xs font-semibold text-brand hover:underline">
                {{ __('borrower.profile.hub.view_edit') }} →
            </button>
        </div>
        <div x-show="open" x-cloak class="p-5 sm:p-6 border-t border-gray-100/80 bg-gray-50/30">
            {{ $form ?? $slot }}
        </div>
    @else
        <div class="p-5 sm:p-6">
            @if ($empty && $addUrl)
                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                    <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                    <a href="{{ $addUrl }}"
                       class="inline-flex mt-4 items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                        {{ $addLabel ?? __('borrower.profile.add_details') }}
                    </a>
                </div>
            @else
                {{ $view ?? $slot }}
            @endif
        </div>
    @endif
</div>
