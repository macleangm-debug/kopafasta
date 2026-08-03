@php
    $currentIndex = collect($steps)->search(fn ($s) => ($s['current'] ?? false));
    if ($currentIndex === false) {
        $currentIndex = 0;
    }
    $doneCount = collect($steps)->where('done', true)->count();
    $progress = count($steps) > 0
        ? (int) round((($doneCount + (($steps[$currentIndex]['done'] ?? false) ? 0 : 0.5)) / count($steps)) * 100)
        : 0;
@endphp

<div class="sticky top-0 z-10 -mx-4 px-4 py-3 mb-6 bg-white/95 backdrop-blur-md border-b border-brand/10">
    <div class="glass-card p-4 ring-1 ring-brand/10">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-500">
                {{ __('borrower.marketplace.wizard_progress') }}
            </p>
            <p class="text-xs font-bold text-brand tabular-nums">{{ $currentIndex + 1 }}/{{ count($steps) }}</p>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-4">
            <div class="h-full bg-brand transition-all duration-500 rounded-full" style="width: {{ min(100, $progress) }}%"></div>
        </div>
        <p class="lg:hidden text-sm font-semibold text-gray-900 mb-3">{{ $steps[$currentIndex]['label'] ?? '' }}</p>
        <ol class="flex items-center gap-1.5 overflow-x-auto pb-1 snap-x snap-mandatory scrollbar-none">
            @foreach ($steps as $i => $step)
                <li class="flex items-center gap-1.5 shrink-0 snap-start">
                    <span
                        class="size-8 rounded-full grid place-items-center text-xs font-bold border-2 transition
                            {{ ($step['done'] ?? false)
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : (($step['current'] ?? false)
                                    ? 'bg-brand text-white border-brand shadow-sm'
                                    : 'bg-white text-gray-400 border-gray-200 opacity-70') }}"
                        title="{{ $i + 1 }}. {{ $step['label'] }}"
                    >
                        @if ($step['done'] ?? false)
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span class="hidden sm:inline text-[11px] font-medium max-w-[6rem] truncate
                        {{ ($step['current'] ?? false) ? 'text-brand' : (($step['done'] ?? false) ? 'text-emerald-700' : 'text-gray-400') }}"
                          title="{{ $step['label'] }}">
                        {{ $step['label'] }}
                    </span>
                    @if (! $loop->last)
                        <span class="text-gray-200 hidden sm:inline">→</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</div>
