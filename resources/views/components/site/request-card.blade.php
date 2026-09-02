@props([
    'icon' => 'document',
    'title',
    'subtitle' => null,
    'meta' => null,
    'status' => null,
    'statusTone' => 'amber',
])

@php
    $hasDetails = filled($subtitle) || filled($meta);
    $statusClass = match ($statusTone) {
        'emerald' => 'bg-emerald-100 text-emerald-900 ring-emerald-200/80',
        'rose' => 'bg-rose-100 text-rose-900 ring-rose-200/80',
        'sky' => 'bg-sky-100 text-sky-950 ring-sky-200/80',
        default => 'bg-amber-100 text-amber-900 ring-amber-200/80',
    };
@endphp

<div {{ $attributes->except('class') }} class="space-y-2" x-data="{ open: false, adding: false }">
    <div @class(['kf-request-card', $attributes->get('class')])>
        <div class="kf-request-card-row">
            @if ($hasDetails)
                <button type="button"
                        class="kf-request-card-toggle"
                        @click="open = ! open"
                        :aria-expanded="open">
            @else
                <div class="kf-request-card-toggle">
            @endif
                <div class="kf-request-card-icon" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        @switch($icon)
                            @case('identity')
                                <rect x="3" y="6" width="18" height="12" rx="2"/>
                                <circle cx="8.25" cy="12" r="1.6"/>
                                <path d="M12 10.25h6M12 13.75h4"/>
                                @break
                            @case('face')
                                <path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                                <path d="M4.5 20.118a7.5 7.5 0 0115 0"/>
                                @break
                            @case('signature')
                                <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897z"/>
                                @break
                            @case('income')
                                <path d="M2.25 18.75c8.25-3 13.5-7.5 19.5-15"/>
                                <path d="M3 13.5c4.5-1.5 7.5-4.5 10.5-9"/>
                                @break
                            @case('residence')
                                <path d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12"/>
                                <path d="M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
                                @break
                            @case('business')
                                <path d="M3.75 21h16.5M4.5 3h15v18h-15z"/>
                                <path d="M9 7.5h.01M9 11.25h.01M9 15h.01M15 7.5h.01M15 11.25h.01M15 15h.01"/>
                                @break
                            @case('collateral')
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                                @break
                            @case('clarification')
                                <path d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                <path d="M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                                @break
                            @default
                                <path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12"/>
                                <path d="M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        @endswitch
                    </svg>
                </div>
                <div class="min-w-0 flex-1 text-left">
                    <p class="text-sm font-extrabold text-gray-900 leading-snug truncate">{{ $title }}</p>
                </div>
                @if ($hasDetails)
                    <svg class="size-4 shrink-0 text-gray-400 transition" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                @endif
            @if ($hasDetails)
                </button>
            @else
                </div>
            @endif
            @if ($status)
                <span class="inline-flex items-center rounded-full text-[10px] font-bold px-2 py-0.5 ring-1 shrink-0 {{ $statusClass }}">{{ $status }}</span>
            @endif
            @isset($action)
                <div class="shrink-0 self-center" @click.stop>
                    {{ $action }}
                </div>
            @endisset
        </div>
        @if ($hasDetails)
            <div x-show="open" x-cloak class="kf-request-card-details">
                @if ($subtitle)
                    <p class="text-xs font-bold text-gray-800">{{ $subtitle }}</p>
                @endif
                @if ($meta)
                    <p @class(['text-xs leading-relaxed text-gray-500', 'mt-0.5' => filled($subtitle)])>{{ $meta }}</p>
                @endif
            </div>
        @endif
    </div>
    {{ $slot }}
</div>
