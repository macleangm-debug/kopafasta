@php
    $summary = $completionSummary ?? app(\App\Services\ProfileCompletionService::class)->completionSummary($customer);
    $percent = (int) ($summary['percent'] ?? 0);
    $ringOffset = 100 - min(100, max(0, $percent));
@endphp

<div class="mb-6 glass-card p-5 sm:p-6">
    <div class="flex items-center gap-5 mb-5">
        <div class="relative size-16 shrink-0" aria-hidden="true">
            <svg class="size-16 -rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#004d40" stroke-width="3" stroke-linecap="round"
                        stroke-dasharray="100" stroke-dashoffset="{{ $ringOffset }}"/>
            </svg>
            <span class="absolute inset-0 grid place-items-center text-sm font-bold text-brand">{{ $percent }}%</span>
        </div>
        <div class="min-w-0">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.completion_summary_title') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ __('borrower.profile.completion_summary_percent', ['percent' => $percent]) }}</p>
        </div>
    </div>
    <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-5 lg:hidden">
        <div class="h-full bg-brand transition-all" style="width: {{ $percent }}%"></div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        @if (! empty($summary['remaining']))
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.profile.completion_remaining') }}</p>
                <ul class="space-y-1.5">
                    @foreach ($summary['remaining'] as $item)
                        <li class="text-sm text-gray-700 flex items-start gap-2">
                            <span class="text-brand mt-0.5">○</span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (! empty($summary['completed']))
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.profile.completion_completed') }}</p>
                <ul class="space-y-1.5">
                    @foreach ($summary['completed'] as $item)
                        <li class="text-sm text-emerald-700 flex items-start gap-2">
                            <span class="mt-0.5">✓</span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
