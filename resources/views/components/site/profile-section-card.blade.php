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
])

@php
    $hasForm = isset($form);
    $useInline = $inlineEdit && ($hasForm || $editing) && ! $editUrl;
    $startOpen = $editing || $defaultOpen;
@endphp

<div
    @if ($sectionId) id="{{ $sectionId }}" @endif
    class="glass-card scroll-mt-24 {{ ($allowOverflow ?? false) ? 'overflow-visible' : 'overflow-hidden' }}"
    @if ($useInline)
        x-data="{ open: @js($startOpen) }"
        @if ($sectionId)
            x-init="if (window.location.hash === '#{{ $sectionId }}') open = true"
        @endif
    @endif
>
    <div class="flex items-start justify-between gap-3 px-5 sm:px-6 py-4 border-b border-gray-100/80">
        <div class="flex items-start gap-3 min-w-0">
            @if ($icon)
                <span class="text-2xl leading-none shrink-0 mt-0.5" aria-hidden="true">{{ $icon }}</span>
            @endif
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
                @if ($complete === true)
                    <p class="text-xs text-emerald-700 mt-1">{{ __('borrower.profile.section_complete') }}</p>
                @elseif ($complete === false)
                    <p class="text-xs text-amber-700 mt-1">{{ __('borrower.profile.section_incomplete') }}</p>
                @endif
            </div>
        </div>
        @if ($useInline)
            <button type="button"
                    @click="open = !open"
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
        <div x-show="!open" class="p-5 sm:p-6">
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
