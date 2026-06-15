@php
    $currentIndex = collect($steps)->search(fn ($s) => $s['current']) ?: 0;
@endphp

<ol class="flex items-center gap-1 mb-8 overflow-x-auto pb-2">
    @foreach ($steps as $i => $step)
        <li class="flex items-center gap-1 shrink-0">
            <span
                class="size-9 rounded-full grid place-items-center text-sm font-bold border-2 transition
                    {{ $step['done'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($step['current'] ? 'bg-amber-500 text-gray-900 border-amber-500' : 'bg-white text-gray-500 border-gray-300') }}"
                title="{{ $i + 1 }}. {{ $step['label'] }}"
            >
                @if ($step['done'])
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg>
                @else
                    {{ $i + 1 }}
                @endif
            </span>
            <span class="text-[10px] sm:text-[11px] font-medium text-gray-600 mr-1 sm:mr-2 max-w-[5.5rem] sm:max-w-none truncate" title="{{ $step['label'] }}">
                <span class="text-gray-400">{{ $i + 1 }}.</span>
                {{ $step['label'] }}
            </span>
            @if (! $loop->last)
                <span class="text-gray-300">→</span>
            @endif
        </li>
    @endforeach
</ol>
