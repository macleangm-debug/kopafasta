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
    'defaultEdit' => false,
    'allowOverflow' => false,
    'collapsible' => true,
    'showStatus' => false,
])

@php
    $hasForm = isset($form);
    $useInline = $inlineEdit && ($hasForm || $editing) && ! $editUrl;
    // defaultOpen = expand to VIEW only. defaultEdit / editing = open the form.
    $startOpen = (bool) $editing || (bool) $defaultEdit;
    $startExpanded = $startOpen || (bool) $defaultOpen;
    $accordionId = $sectionId ?: ('section-'.substr(md5($title), 0, 8));
    $isComplete = $complete === true;
    // Server-render the tick so complete cards never flash Edit before Alpine boots.
    $startWithTick = $isComplete && ! $startOpen;
@endphp

<div
    @if ($sectionId) id="{{ $sectionId }}" @endif
    class="glass-card scroll-mt-24 {{ ($allowOverflow ?? false) ? 'overflow-visible' : 'overflow-hidden' }}"
    x-data="profileSectionCard(@js([
        'open' => $startOpen,
        'expanded' => $startExpanded,
        'complete' => $isComplete,
        'showEditAction' => $startOpen,
        'id' => $accordionId,
        'sectionHash' => $sectionId,
        'unsavedTitle' => __('borrower.profile.unsaved_photos_title'),
        'unsavedMessage' => __('borrower.profile.unsaved_photos_body'),
        'unsavedConfirm' => __('borrower.profile.unsaved_photos_confirm'),
    ]))"
>
    <div class="flex items-start justify-between gap-3 px-5 sm:px-6 py-4 border-b border-gray-100/80">
        <button type="button"
                @if ($collapsible)
                    @click="toggleExpand()"
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
                @if ($showStatus && $isComplete)
                    <p class="text-xs text-emerald-700 mt-1">{{ __('borrower.profile.section_complete') }}</p>
                @elseif ($showStatus && $complete === false)
                    <p class="text-xs text-amber-700 mt-1">{{ __('borrower.profile.section_incomplete') }}</p>
                @endif
            </div>
        </button>

        @if ($useInline)
            <div class="shrink-0 relative min-h-9 min-w-9 flex items-center justify-end">
                {{-- Tick: visible in HTML for complete cards (no Alpine flash) --}}
                <button type="button"
                        @click.stop="revealEdit()"
                        class="size-9 rounded-full grid place-items-center bg-gradient-to-br from-brand to-brand-light text-brand-gold shadow-sm shadow-brand/25 ring-2 ring-brand-gold/40 hover:ring-brand-gold/70 transition"
                        @if ($startWithTick)
                            x-show="showCompleteTick"
                        @else
                            x-show="showCompleteTick" x-cloak
                        @endif
                        title="{{ __('borrower.profile.section_complete_tap') }}"
                        aria-label="{{ __('borrower.profile.section_complete_tap') }}">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                </button>

                {{-- Edit/Cancel: hidden in HTML when complete until tick is tapped --}}
                <button type="button"
                        @click="open ? requestClose() : openEdit()"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-full ring-1 transition"
                        @if ($startWithTick)
                            x-show="!showCompleteTick" x-cloak
                        @else
                            x-show="!showCompleteTick"
                        @endif
                        :class="open ? 'text-gray-700 ring-gray-200 bg-gray-50' : 'text-amber-700 ring-amber-200 bg-amber-50 hover:text-amber-800'">
                    <span x-show="!open">{{ $empty ? ($addLabel ?? __('borrower.profile.add_details')) : __('borrower.profile.edit_section') }}</span>
                    <span x-show="open" x-cloak>{{ __('borrower.profile.cancel_edit') }}</span>
                </button>
            </div>
        @elseif (! $editing && $editUrl)
            @if ($isComplete)
                <div x-data="{ reveal: @js($startOpen) }" class="shrink-0 relative min-h-9 min-w-9 flex items-center justify-end">
                    <button type="button"
                            x-show="!reveal"
                            @click="reveal = true"
                            class="size-9 rounded-full grid place-items-center bg-gradient-to-br from-brand to-brand-light text-brand-gold shadow-sm shadow-brand/25 ring-2 ring-brand-gold/40 hover:ring-brand-gold/70 transition"
                            title="{{ __('borrower.profile.section_complete_tap') }}"
                            aria-label="{{ __('borrower.profile.section_complete_tap') }}">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <a href="{{ $editUrl }}"
                       x-show="reveal"
                       x-cloak
                       class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 hover:text-amber-800 px-3 py-1.5 rounded-full ring-1 ring-amber-200 bg-amber-50">
                        {{ $empty ? ($addLabel ?? __('borrower.profile.add_details')) : __('borrower.profile.edit_section') }}
                    </a>
                </div>
            @else
                <a href="{{ $editUrl }}"
                   class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 hover:text-amber-800 px-3 py-1.5 rounded-full ring-1 ring-amber-200 bg-amber-50">
                    {{ $empty ? ($addLabel ?? __('borrower.profile.add_details')) : __('borrower.profile.edit_section') }}
                </a>
            @endif
        @elseif ($isComplete)
            <span class="shrink-0 size-9 rounded-full grid place-items-center bg-gradient-to-br from-brand to-brand-light text-brand-gold shadow-sm shadow-brand/25 ring-2 ring-brand-gold/40"
                  title="{{ __('borrower.profile.section_complete') }}"
                  aria-label="{{ __('borrower.profile.section_complete') }}">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                </svg>
            </span>
        @endif
    </div>

    @if ($useInline)
        <div x-show="!open && expanded" x-cloak class="p-5 sm:p-6">
            @if ($empty && $addUrl)
                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                    <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                    <button type="button" @click="openEdit()"
                            class="inline-flex mt-4 items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                        {{ $addLabel ?? __('borrower.profile.add_details') }}
                    </button>
                </div>
            @else
                {{ $view ?? $slot }}
            @endif
        </div>
        <div x-show="!open && !expanded" class="px-5 sm:px-6 py-3">
            <button type="button" @click="toggleExpand()" class="text-xs font-semibold text-brand hover:underline">
                {{ $isComplete ? __('borrower.profile.hub.view') : __('borrower.profile.hub.view_edit') }} →
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
