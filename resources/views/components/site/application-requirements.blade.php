@props(['items' => []])

@php
    $completion = $items['completion_percent'] ?? 0;
    $profilePct = $items['profile_percent'] ?? $completion;
@endphp

<div class="mb-6 rounded-2xl bg-white ring-1 ring-gray-200 overflow-hidden" x-data="{ open: false }">
    <button type="button" @click="open = !open"
            class="w-full text-left p-5 flex flex-wrap items-center justify-between gap-4 hover:bg-gray-50/80 transition">
        <div class="min-w-0 flex-1">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Application readiness</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $completion }}% complete</h2>
            <p class="text-sm text-gray-500 mt-1">Profile {{ $profilePct }}% · tap to view requirements</p>
        </div>
        <div class="flex items-center gap-4 shrink-0">
            <div class="hidden sm:block w-24 h-2 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full bg-emerald-500 transition-all" style="width: {{ $completion }}%"></div>
            </div>
            <span class="inline-flex items-center gap-1 text-sm font-semibold text-amber-700">
                <span x-text="open ? '▲ Hide' : '▼ View requirements'"></span>
            </span>
        </div>
    </button>

    <div x-show="open" x-transition x-cloak class="border-t border-gray-100 px-5 pb-5">
        <div class="mb-4 h-2 rounded-full bg-gray-100 overflow-hidden sm:hidden">
            <div class="h-full bg-emerald-500 transition-all" style="width: {{ $completion }}%"></div>
        </div>

        <ul class="space-y-2 pt-4">
            @foreach (($items['items'] ?? []) as $item)
                @php
                    $isComplete = $item['complete'] ?? false;
                    $isPending = ($item['pending'] ?? false) && ! $isComplete;
                @endphp
                <li class="flex items-start gap-3 rounded-xl px-3 py-3 {{ $isComplete ? 'bg-emerald-50/80' : ($isPending ? 'bg-amber-50/80' : 'bg-gray-50') }}">
                    <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full grid place-items-center text-xs font-bold
                        {{ $isComplete ? 'bg-emerald-500 text-white' : ($isPending ? 'bg-amber-400 text-white' : 'bg-gray-200 text-gray-500') }}">
                        @if ($isComplete)
                            ✓
                        @elseif ($isPending)
                            ⏳
                        @else
                            !
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
</div>
