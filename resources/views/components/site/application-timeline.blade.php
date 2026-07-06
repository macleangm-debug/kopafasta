@props([
    'steps' => [],
    'title' => null,
    'percent' => null,
    'compact' => false,
])

@php
    $steps = collect($steps)->filter(fn ($step) => filled($step['label'] ?? null))->values();

    $stepIcons = [
        'submitted'           => '📤',
        'under_review'        => '🔍',
        'documents_requested' => '📄',
        'approved'            => '✅',
        'accept_offer'        => '✍️',
        'post_approval_fee'   => '💳',
        'destination'         => '🏦',
        'contract'            => '📝',
        'disbursement'        => '💰',
        'active_loan'         => '🎉',
    ];

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
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xl shrink-0" aria-hidden="true">🗓️</span>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-gray-900">{{ $title ?? __('borrower.loan_profile.application_progress') }}</h2>
                        @if ($currentStep)
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $currentStep['label'] }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0 sm:min-w-[140px]">
                    <div class="flex-1 sm:w-24 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand transition-all" style="width: {{ min(100, max(0, $displayPercent)) }}%"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900">{{ min(100, max(0, $displayPercent)) }}%</span>
                </div>
            </div>

            <ol class="p-5 sm:p-6 space-y-0">
                @foreach ($steps as $index => $step)
                    @php
                        $isComplete = (bool) ($step['complete'] ?? false);
                        $isCurrent = (bool) ($step['current'] ?? false);
                        $isLast = $index === $steps->count() - 1;
                        $icon = $stepIcons[$step['key'] ?? ''] ?? '📋';

                        $dotClass = match (true) {
                            $isComplete => 'bg-emerald-500 text-white',
                            $isCurrent  => 'bg-amber-500 text-white ring-4 ring-amber-100',
                            default     => 'bg-gray-100 text-gray-400 ring-1 ring-gray-200',
                        };
                        $textClass = match (true) {
                            $isComplete => 'text-emerald-800',
                            $isCurrent  => 'text-amber-900 font-semibold',
                            default     => 'text-gray-500',
                        };
                        $lineClass = $isComplete ? 'bg-emerald-200' : 'bg-gray-200';
                    @endphp
                    <li class="flex gap-3">
                        <div class="flex flex-col items-center shrink-0">
                            <span @class([
                                'size-8 rounded-full grid place-items-center text-sm shrink-0',
                                $dotClass,
                            ]) aria-hidden="true">{{ $icon }}</span>
                            @unless ($isLast)
                                <span @class(['w-0.5 flex-1 min-h-[1.25rem] my-1', $lineClass])></span>
                            @endunless
                        </div>
                        <div @class(['pb-5 min-w-0', 'pb-0' => $isLast])>
                            <p @class(['text-sm leading-snug', $textClass])>{{ $step['label'] }}</p>
                            @if ($isCurrent)
                                <p class="text-xs text-amber-700/80 mt-0.5">{{ __('borrower.loan_profile.timeline_in_progress') }}</p>
                            @elseif ($isComplete)
                                <p class="text-xs text-emerald-600/80 mt-0.5">{{ __('borrower.loan_profile.timeline_complete') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
@endif
