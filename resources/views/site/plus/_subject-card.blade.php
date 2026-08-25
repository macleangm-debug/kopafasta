@php
    $row = $progress ?? null;
    if ($row instanceof \Illuminate\Support\Collection) {
        $row = $row->get($subject->id);
    }
@endphp
<a href="{{ route('site.borrower.plus.subject', $subject) }}" class="block rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30 shadow-sm">
    <div class="flex items-start gap-3">
        <div class="size-11 shrink-0 rounded-2xl bg-brand/10 text-xl grid place-items-center">{{ $subject->icon ?: '📘' }}</div>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-brand">{{ $subject->category?->localizedTitle() }}</p>
            <p class="font-extrabold text-gray-900 mt-0.5 leading-snug">{{ $subject->localizedTitle() }}</p>
            <p class="text-sm text-gray-600 mt-1 line-clamp-3">{{ $subject->localizedIntro() }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ __('plus.learn.minutes', ['minutes' => $subject->duration_minutes]) }}@if($row && $row->started_at && ! $row->completed_at) · {{ app(\App\Services\Plus\PlusLearningService::class)->progressPercent($row) }}%@endif</p>
            <span class="mt-3 inline-flex rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">{{ __('plus.learn.read') }} →</span>
        </div>
    </div>
</a>
