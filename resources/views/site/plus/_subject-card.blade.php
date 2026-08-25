@php
    $row = $progress ?? null;
    if ($row instanceof \Illuminate\Support\Collection) {
        $row = $row->get($subject->id);
    }
@endphp
<a href="{{ route('site.borrower.plus.subject', $subject) }}" class="block rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30">
    <p class="text-xs font-semibold text-brand">{{ $subject->category?->localizedTitle() }}</p>
    <p class="font-semibold text-gray-900 mt-1">{{ $subject->localizedTitle() }}</p>
    <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $subject->localizedIntro() }}</p>
    <p class="text-xs text-gray-500 mt-2">{{ __('plus.learn.minutes', ['minutes' => $subject->duration_minutes]) }}@if($row && $row->started_at && ! $row->completed_at) · {{ app(\App\Services\Plus\PlusLearningService::class)->progressPercent($row) }}%@endif</p>
    <p class="text-sm font-semibold text-brand mt-2">{{ __('plus.learn.read') }} →</p>
</a>
