@props([
    'steps' => [],
    'title' => null,
    'percent' => null,
    'compact' => false,
])

@php
    $steps = collect($steps)->filter(fn ($step) => filled($step['label'] ?? null))->values();

    $hasExplicitCurrent = $steps->contains(fn (array $step) => (bool) ($step['current'] ?? false));

    if (! $hasExplicitCurrent && $steps->isNotEmpty()) {
        $currentIndex = $steps->search(fn (array $step) => ! ($step['complete'] ?? false));
        if ($currentIndex === false) {
            $currentIndex = max(0, $steps->count() - 1);
        }
        $steps = $steps->map(function (array $step, int $index) use ($currentIndex) {
            $step['current'] = $index === $currentIndex && ! ($step['complete'] ?? false);

            return $step;
        });
    }

    $completedCount = $steps->where('complete', true)->count();
    $currentStep = $steps->first(fn (array $step) => (bool) ($step['current'] ?? false));
    $displayPercent = $percent ?? (int) round((($completedCount + ($currentStep ? 0.5 : 0)) / max(1, $steps->count())) * 100);
@endphp

@if ($steps->isNotEmpty())
    @if ($compact)
        <div {{ $attributes->merge(['class' => 'mt-3']) }}>
            <div class="flex items-center gap-1" role="list" aria-label="{{ $title ?? __('borrower.loan_profile.application_progress') }}">
                @foreach ($steps as $step)
                    @php
                        $isComplete = (bool) ($step['complete'] ?? false);
                        $isCurrent = (bool) ($step['current'] ?? false);
                        $dotClass = match (true) {
                            $isComplete => 'bg-emerald-500',
                            $isCurrent  => 'bg-amber-500 ring-2 ring-amber-200 scale-110',
                            default     => 'bg-gray-200',
                        };
                    @endphp
                    <span role="listitem"
                          title="{{ $step['label'] }}"
                          @class(['size-2 rounded-full shrink-0 transition', $dotClass])></span>
                @endforeach
            </div>
            @if ($currentStep)
                <p class="text-[11px] text-gray-500 mt-1.5 truncate">{{ $currentStep['label'] }}</p>
            @endif
        </div>
    @else
        <section {{ $attributes->merge(['class' => 'glass-card overflow-hidden mb-6 ring-1 ring-brand/10']) }}>
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-semibold text-gray-900">{{ $title ?? __('borrower.loan_profile.application_progress') }}</h2>
                    @if ($currentStep)
                        <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $currentStep['label'] }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-3 shrink-0 sm:min-w-[140px]">
                    <div class="flex-1 sm:w-24 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand transition-all" style="width: {{ min(100, max(0, $displayPercent)) }}%"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900">{{ min(100, max(0, $displayPercent)) }}%</span>
                </div>
            </div>

            <div class="p-4 sm:p-5">
                <ol class="flex items-center gap-1.5 overflow-x-auto pb-1 snap-x snap-mandatory scrollbar-none"
                    aria-label="{{ $title ?? __('borrower.loan_profile.application_progress') }}">
                    @foreach ($steps as $index => $step)
                        @php
                            $isComplete = (bool) ($step['complete'] ?? false);
                            $isCurrent = (bool) ($step['current'] ?? false);
                            $circleClass = match (true) {
                                $isComplete => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                $isCurrent  => 'bg-brand text-white border-brand shadow-sm',
                                default     => 'bg-white text-gray-400 border-gray-200 opacity-70',
                            };
                            $labelClass = match (true) {
                                $isComplete => 'text-emerald-700',
                                $isCurrent  => 'text-brand',
                                default     => 'text-gray-400',
                            };
                        @endphp
                        <li class="flex items-center gap-1.5 shrink-0 snap-start">
                            <span @class([
                                'size-8 rounded-full grid place-items-center text-xs font-bold border-2 shrink-0',
                                $circleClass,
                            ])
                                  title="{{ ($index + 1).'. '.$step['label'] }}"
                                  aria-current="{{ $isCurrent ? 'step' : 'false' }}">
                                @if ($isComplete)
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 10l3 3 7-7"/></svg>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            <span @class(['hidden sm:inline text-[11px] font-medium max-w-[6rem] truncate', $labelClass])
                                  title="{{ $step['label'] }}">{{ $step['label'] }}</span>
                            @unless ($loop->last)
                                <span class="text-gray-200 hidden sm:inline" aria-hidden="true">→</span>
                            @endunless
                        </li>
                    @endforeach
                </ol>
                @if ($currentStep)
                    <p class="sm:hidden text-sm font-semibold text-gray-900 mt-3">{{ $currentStep['label'] }}</p>
                    <p class="text-xs text-amber-700/80 mt-1.5">{{ __('borrower.loan_profile.timeline_in_progress') }}</p>
                @endif
            </div>
        </section>
    @endif
@endif
