@props(['items' => []])

@php
    $completion = $items['completion_percent'] ?? 0;
    $profilePct = $items['profile_percent'] ?? $completion;
@endphp

<div class="mb-6 rounded-2xl bg-white ring-1 ring-gray-200 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Loan eligibility</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1">Application requirements</h2>
            <p class="text-sm text-gray-500 mt-1">Profile completion <span class="font-semibold text-gray-900">{{ $profilePct }}%</span></p>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-gray-900">{{ $completion }}%</div>
            <p class="text-[11px] text-gray-500">Checklist complete</p>
        </div>
    </div>

    <div class="mb-4 h-2 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-full bg-emerald-500 transition-all" style="width: {{ $completion }}%"></div>
    </div>

    <ul class="space-y-2">
        @foreach (($items['items'] ?? []) as $item)
            @php
                $isComplete = $item['complete'] ?? false;
                $isPending = ($item['pending'] ?? false) && ! $isComplete;
            @endphp
            <li class="flex items-start gap-3 rounded-xl px-3 py-3 {{ $isComplete ? 'bg-emerald-50/80' : ($isPending ? 'bg-amber-50/80' : 'bg-gray-50') }}">
                <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full grid place-items-center text-xs font-bold
                    {{ $isComplete ? 'bg-emerald-500 text-white' : ($isPending ? 'bg-amber-400 text-white' : 'bg-gray-200 text-gray-500') }}">
                    @if ($isComplete)
                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg>
                    @elseif ($isPending)
                        <span>⏳</span>
                    @else
                        <span>!</span>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold {{ $isComplete ? 'text-emerald-900' : ($isPending ? 'text-amber-900' : 'text-gray-900') }}">{{ $item['label'] }}</p>
                    <p class="text-xs {{ $isComplete ? 'text-emerald-700' : ($isPending ? 'text-amber-700' : 'text-gray-500') }} mt-0.5">{{ $item['detail'] }}</p>
                </div>
                @if (! $isComplete && ! empty($item['action_url']))
                    <a href="{{ $item['action_url'] }}" class="shrink-0 text-xs font-semibold text-amber-700 hover:underline whitespace-nowrap">Complete →</a>
                @endif
            </li>
        @endforeach
    </ul>
</div>
