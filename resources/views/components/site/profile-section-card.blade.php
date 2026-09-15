@props([
    'title',
    'icon' => null,
    'editing' => false,
    'editUrl' => null,
    'complete' => null,
    'stale' => false,
    'empty' => false,
    'emptyOpensView' => false,
    'editAllowed' => true,
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
    $emptyOpensView = (bool) $emptyOpensView;
    $editAllowed = filter_var($editAllowed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $editAllowed = $editAllowed === null ? true : $editAllowed;
    $accordionId = $sectionId ?: ('section-'.substr(md5($title), 0, 8));
    $isStale = (bool) $stale;
    // Tick when complete AND fresh. Accept bool/int/string from Blade bindings.
    $isComplete = ! $isStale && filter_var($complete, FILTER_VALIDATE_BOOLEAN);
    // Server-render the tick so complete cards never flash Edit before Alpine boots.
    $startWithTick = $isComplete && ! $startOpen && ! $startExpanded;
    // Locked/saved sections: never expose Edit — tick only.
    $forceTickOnly = $isComplete && ! $editAllowed;
@endphp

<div
    @if ($sectionId) id="{{ $sectionId }}" @endif
    {{ $attributes->class([
        'glass-card scroll-mt-24',
        ($allowOverflow ?? false) ? 'overflow-visible' : 'overflow-hidden',
    ]) }}
    x-data="profileSectionCard(@js([
        'open' => $forceTickOnly ? false : $startOpen,
        'expanded' => $forceTickOnly ? $startExpanded : $startExpanded,
        'complete' => $isComplete,
        'empty' => (bool) $empty,
        'showEditAction' => $forceTickOnly ? false : ($startOpen || $isStale),
        'emptyOpensView' => $emptyOpensView,
        'editAllowed' => $editAllowed,
        'id' => $accordionId,
        'sectionHash' => $sectionId,
        'unsavedTitle' => __('borrower.profile.unsaved_photos_title'),
        'unsavedMessage' => __('borrower.profile.unsaved_photos_body'),
        'unsavedConfirm' => __('borrower.profile.unsaved_photos_confirm'),
    ]))"
    @profile-card-open-edit.window="if ($event.detail === sectionHash && editAllowed) openEdit()"
>
    <div
        @class([
            'flex items-start justify-between gap-3 px-5 sm:px-6 py-4 border-b border-gray-100/80',
            'cursor-pointer' => $collapsible,
        ])
        @if ($collapsible)
            role="button"
            tabindex="0"
            @click="toggleExpand()"
            @keydown.enter.prevent="toggleExpand()"
            @keydown.space.prevent="toggleExpand()"
        @endif
    >
        <div class="flex items-start gap-3 min-w-0 text-left flex-1">
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
                @if ($isStale)
                    <p class="text-xs font-semibold text-amber-700 mt-1">{{ __('borrower.profile.section_needs_update') }}</p>
                @elseif ($showStatus && $isComplete)
                    <p class="text-xs text-emerald-700 mt-1">{{ __('borrower.profile.section_complete') }}</p>
                @elseif ($showStatus && $complete === false)
                    <p class="text-xs text-amber-700 mt-1">{{ __('borrower.profile.section_incomplete') }}</p>
                @endif
            </div>
        </div>

        @if ($useInline)
            <div class="shrink-0 flex items-center justify-end min-h-9">
                @if ($forceTickOnly)
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-brand to-brand-light pl-1.5 pr-3 py-1.5 text-brand-gold shadow-sm shadow-brand/20 ring-1 ring-brand-gold/50 pointer-events-none"
                        title="{{ __('borrower.profile.section_complete') }}"
                        aria-label="{{ __('borrower.profile.section_complete') }}">
                        <span class="grid size-7 place-items-center rounded-full bg-white/15 ring-1 ring-white/25">
                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <span class="text-[11px] font-bold text-white/90 hidden sm:inline">{{ __('borrower.profile.section_complete') }}</span>
                    </span>
                @else
                {{-- Exclusive controls: never render tick and Edit in the same frame --}}
                <template x-if="typeof showCompleteTick === 'boolean' ? showCompleteTick : @js($startWithTick)">
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-brand to-brand-light pl-1.5 pr-3 py-1.5 text-brand-gold shadow-sm shadow-brand/20 ring-1 ring-brand-gold/50 pointer-events-none"
                        title="{{ __('borrower.profile.section_complete') }}"
                        aria-label="{{ __('borrower.profile.section_complete') }}">
                        <span class="grid size-7 place-items-center rounded-full bg-white/15 ring-1 ring-white/25">
                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <span class="text-[11px] font-bold text-white/90 hidden sm:inline">{{ __('borrower.profile.section_complete') }}</span>
                    </span>
                </template>
                {{-- Edit only in View/Edit — collapsed cards use Angalia / View --}}
                <template x-if="typeof showHeaderEdit === 'boolean' ? showHeaderEdit : false">
                    <button type="button"
                            @click.stop="open ? requestClose() : openEdit()"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-full ring-1 transition"
                            :class="open
                                ? 'text-gray-700 ring-gray-200 bg-gray-50'
                                : (@js($isStale)
                                    ? 'text-amber-800 ring-amber-300 bg-amber-50 hover:bg-amber-100'
                                    : 'text-brand ring-brand/20 bg-brand-muted/50 hover:bg-brand-muted')">
                        <span x-show="!open" class="inline-flex items-center gap-1.5">
                            @if ($isStale)
                                <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                                <span>{{ __('borrower.profile.update_section') }}</span>
                            @else
                                <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                <span>{{ __('borrower.profile.edit_section') }}</span>
                            @endif
                        </span>
                        <span x-show="open" x-cloak>{{ __('borrower.profile.cancel_edit') }}</span>
                    </button>
                </template>
                @endif
            </div>
        @elseif (! $editing && $editUrl)
            <div class="shrink-0 relative min-h-9 min-w-9 flex items-center justify-end">
            @if ($isComplete)
                <div x-data="{ reveal: @js($startOpen) }" class="relative min-h-9 min-w-9 flex items-center justify-end">
                    <span
                            x-show="!reveal"
                            class="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-brand to-brand-light pl-1.5 pr-3 py-1.5 text-brand-gold shadow-sm shadow-brand/25 ring-2 ring-brand-gold/40 pointer-events-none"
                            title="{{ __('borrower.profile.section_complete') }}"
                            aria-label="{{ __('borrower.profile.section_complete') }}">
                        <span class="grid size-7 place-items-center rounded-full bg-white/15 ring-1 ring-white/25">
                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <span class="text-[11px] font-bold text-white/90 hidden sm:inline">{{ __('borrower.profile.section_complete') }}</span>
                    </span>
                    <a href="{{ $editUrl }}"
                       x-show="reveal"
                       x-cloak
                       @click.stop
                       class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 hover:text-amber-800 px-3 py-1.5 rounded-full ring-1 ring-amber-200 bg-amber-50">
                        @if ($empty)
                            <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            {{ $addLabel ?? __('borrower.profile.add_details') }}
                        @else
                            <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                            {{ __('borrower.profile.edit_section') }}
                        @endif
                    </a>
                </div>
            @else
                <a href="{{ $editUrl }}"
                   @click.stop
                   class="inline-flex items-center gap-1.5 text-sm font-semibold {{ $isStale ? 'text-amber-800 ring-amber-300' : 'text-amber-700 ring-amber-200' }} hover:text-amber-800 px-3 py-1.5 rounded-full ring-1 bg-amber-50">
                    @if ($empty)
                        <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        {{ $addLabel ?? __('borrower.profile.add_details') }}
                    @elseif ($isStale)
                        <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                        {{ __('borrower.profile.update_section') }}
                    @else
                        <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        {{ __('borrower.profile.edit_section') }}
                    @endif
                </a>
            @endif
            </div>
        @elseif ($isComplete)
            <span class="shrink-0 inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-brand to-brand-light pl-1.5 pr-3 py-1.5 text-brand-gold shadow-sm shadow-brand/25 ring-2 ring-brand-gold/40 pointer-events-none"
                  title="{{ __('borrower.profile.section_complete') }}"
                  aria-label="{{ __('borrower.profile.section_complete') }}">
                <span class="grid size-7 place-items-center rounded-full bg-white/15 ring-1 ring-white/25">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span class="text-[11px] font-bold text-white/90 hidden sm:inline">{{ __('borrower.profile.section_complete') }}</span>
            </span>
        @endif
    </div>

    @if ($useInline)
        {{-- SSR/hydration agree: cloak only the panel that should be hidden on first paint --}}
        <div x-show="!open && expanded" @unless ($startExpanded && ! $startOpen) x-cloak @endunless class="p-5 sm:p-6" @click.stop>
            {{-- empty is Alpine-live so document uploads can leave Add → View without reload --}}
            <div x-show="empty && !emptyOpensView" x-cloak class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                <button type="button" @click="openEdit()"
                        class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-amber-700 hover:text-amber-800">
                    {{ $addLabel ?? __('borrower.profile.add_details') }} →
                </button>
            </div>
            <div x-show="!empty || emptyOpensView" @if ($empty && ! $emptyOpensView) x-cloak @endif>
                {{ $view ?? $slot }}
            </div>
        </div>
        <div x-show="!open && !expanded" @if ($startExpanded || $startOpen) x-cloak @endif class="px-5 sm:px-6 py-3">
            <button type="button"
                    @click.stop="empty && !emptyOpensView ? openEdit() : toggleExpand()"
                    class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                <span x-show="empty && !emptyOpensView" @unless ($empty && ! $emptyOpensView) x-cloak @endunless>
                    {{ $addLabel ?? __('borrower.profile.add_details') }} →
                </span>
                <span x-show="!(empty && !emptyOpensView)" @if ($empty && ! $emptyOpensView) x-cloak @endif>
                    {{ $isStale ? __('borrower.profile.hub.view_update') : __('borrower.profile.hub.view') }} →
                </span>
            </button>
        </div>
        <div x-show="open" x-cloak class="p-5 sm:p-6 border-t border-gray-100/80 bg-gray-50/30" @click.stop>
            {{ $form ?? $slot }}
        </div>
    @else
        <div class="p-5 sm:p-6" @click.stop>
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
