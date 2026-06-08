@php
    $summary = $completionSummary ?? app(\App\Services\ProfileCompletionService::class)->completionSummary($customer);
@endphp

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5">
    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
        <div>
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.completion_summary_title') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ __('borrower.profile.completion_summary_percent', ['percent' => $summary['percent'] ?? 0]) }}</p>
        </div>
        <span class="text-2xl font-bold text-amber-700">{{ $summary['percent'] ?? 0 }}%</span>
    </div>
    <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-5">
        <div class="h-full bg-amber-500 transition-all" style="width: {{ $summary['percent'] ?? 0 }}%"></div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        @if (! empty($summary['remaining']))
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.profile.completion_remaining') }}</p>
                <ul class="space-y-1.5">
                    @foreach ($summary['remaining'] as $item)
                        <li class="text-sm text-gray-700 flex items-start gap-2">
                            <span class="text-amber-500 mt-0.5">○</span>
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
