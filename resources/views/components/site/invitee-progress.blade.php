@props([
    'name' => '',
    'badge' => null,
    'badgeTone' => 'sky',
    'steps' => [],
    'terminal' => false,
    'terminalLabel' => null,
])

@php
    $toneClass = match ((string) $badgeTone) {
        'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-900 ring-amber-200',
        'rose' => 'bg-rose-50 text-rose-800 ring-rose-200',
        default => 'bg-sky-50 text-sky-800 ring-sky-200',
    };
@endphp

<div {{ $attributes->class(['rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm bg-gradient-to-br from-white via-white to-brand-muted/25']) }}>
    <div class="px-4 sm:px-5 pt-4 pb-3 flex flex-wrap items-start justify-between gap-2">
        @if ($slot->isNotEmpty() || filled($name))
            <div class="min-w-0">
                @if ($slot->isNotEmpty())
                    {{ $slot }}
                @else
                    <p class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight truncate">{{ $name }}</p>
                @endif
            </div>
        @else
            <div class="min-w-0"></div>
        @endif
        @if ($terminal && $terminalLabel)
            <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 bg-rose-50 text-rose-800 ring-rose-200">
                {{ $terminalLabel }}
            </span>
        @elseif (filled($badge))
            <span @class([
                'shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1',
                $toneClass,
            ])>
                {{ $badge }}
            </span>
        @endif
    </div>

    @if (! $terminal && ! empty($steps))
        {{-- Desktop: horizontal connected journey --}}
        <ol class="hidden sm:flex items-stretch gap-0 px-4 sm:px-5 pb-5" aria-label="{{ __('borrower.loan_profile.application_progress') }}">
            @foreach ($steps as $step)
                @php
                    $isComplete = (bool) ($step['complete'] ?? false);
                    $isCurrent = (bool) ($step['current'] ?? false);
                @endphp
                <li class="flex items-center min-w-0 {{ $loop->last ? '' : 'flex-1' }}">
                    <div @class([
                        'flex items-center gap-2 min-w-0 rounded-xl px-2 py-2',
                        'bg-brand/10 ring-1 ring-brand/25' => $isCurrent && ! $isComplete,
                    ])>
                        <span @class([
                            'size-8 rounded-full grid place-items-center text-xs font-bold shrink-0 ring-2',
                            'bg-emerald-500 text-white ring-emerald-200' => $isComplete,
                            'bg-brand text-white ring-brand/30 shadow-sm' => ! $isComplete && $isCurrent,
                            'bg-white text-gray-300 ring-gray-200' => ! $isComplete && ! $isCurrent,
                        ])>
                            @if ($isComplete)
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 10l3 3 7-7"/></svg>
                            @elseif ($isCurrent)
                                <span class="size-2 rounded-full bg-brand-gold" aria-hidden="true"></span>
                            @else
                                <span class="size-1.5 rounded-full bg-gray-300" aria-hidden="true"></span>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p @class([
                                'text-xs font-semibold leading-snug truncate',
                                'text-emerald-800' => $isComplete,
                                'text-brand' => ! $isComplete && $isCurrent,
                                'text-gray-400' => ! $isComplete && ! $isCurrent,
                            ])>{{ $step['label'] ?? '' }}</p>
                        </div>
                    </div>
                    @unless ($loop->last)
                        <span @class([
                            'mx-1.5 h-0.5 flex-1 min-w-[0.75rem] rounded-full',
                            'bg-emerald-400' => $isComplete,
                            'bg-brand/30' => ! $isComplete && $isCurrent,
                            'bg-gray-200' => ! $isComplete && ! $isCurrent,
                        ]) aria-hidden="true"></span>
                    @endunless
                </li>
            @endforeach
        </ol>

        {{-- Mobile: compact vertical stepped journey --}}
        <ol class="sm:hidden space-y-0 px-4 pb-4" aria-label="{{ __('borrower.loan_profile.application_progress') }}">
            @foreach ($steps as $step)
                @php
                    $isComplete = (bool) ($step['complete'] ?? false);
                    $isCurrent = (bool) ($step['current'] ?? false);
                @endphp
                <li class="flex gap-3">
                    <div class="flex flex-col items-center shrink-0">
                        <span @class([
                            'size-7 rounded-full grid place-items-center text-[10px] font-bold ring-2',
                            'bg-emerald-500 text-white ring-emerald-200' => $isComplete,
                            'bg-brand text-white ring-brand/30' => ! $isComplete && $isCurrent,
                            'bg-white text-gray-300 ring-gray-200' => ! $isComplete && ! $isCurrent,
                        ])>
                            @if ($isComplete)
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 10l3 3 7-7"/></svg>
                            @elseif ($isCurrent)
                                ●
                            @else
                                ○
                            @endif
                        </span>
                        @unless ($loop->last)
                            <span @class([
                                'w-0.5 flex-1 min-h-[1rem] my-1 rounded-full',
                                'bg-emerald-400' => $isComplete,
                                'bg-gray-200' => ! $isComplete,
                            ]) aria-hidden="true"></span>
                        @endunless
                    </div>
                    <div @class(['pb-3 min-w-0 pt-0.5', 'pb-0' => $loop->last])>
                        <p @class([
                            'text-sm font-semibold',
                            'text-emerald-800' => $isComplete,
                            'text-brand' => ! $isComplete && $isCurrent,
                            'text-gray-400' => ! $isComplete && ! $isCurrent,
                        ])>{{ $step['label'] ?? '' }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
