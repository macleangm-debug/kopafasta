@php
    $afford = $affordability ?? ($review['affordability'] ?? []);
    $affPass = (bool) ($afford['pass'] ?? false);
    $affWarn = ($afford['verdict'] ?? '') === 'warn';
    $affFail = ! $affPass && ! $affWarn && ! empty($afford);
    $systemResult = ! empty($afford)
        ? ($affPass && ! $affWarn ? 'Pass' : ($affWarn ? 'Concern' : 'Fail'))
        : 'Not calculated';
    $needsConfirm = $affWarn || $affFail;
@endphp

<div id="item-activity_income.affordability" class="rounded-2xl ring-2 ring-brand/15 overflow-hidden shadow-sm bg-white"
     x-data="{ affordOpen: {{ $needsConfirm ? 'true' : 'false' }}, affordConcern: {{ $affFail || $affWarn ? 'true' : 'false' }} }">
    <button type="button" class="w-full text-left px-4 py-3.5 bg-brand text-white flex flex-wrap items-center justify-between gap-2"
            @click="affordOpen = !affordOpen">
        <div>
            <p class="text-base font-extrabold tracking-tight">2.4 Affordability</p>
            <p class="text-[11px] text-white/80 mt-0.5">
                System result:
                <span class="font-semibold">{{ $systemResult }}</span>
            </p>
        </div>
        <svg class="size-4 text-brand-gold transition" :class="affordOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
    </button>
    <div x-show="affordOpen" x-cloak class="p-4 space-y-3">
        <p class="text-sm text-gray-600">
            Kopafasta already ran the one-third rule. Confirm the result or raise a concern if the numbers do not match what you see on the statements.
        </p>
        @if ($needsConfirm)
            <div class="flex flex-wrap gap-1.5">
                <button type="button"
                        @click="affordConcern = false"
                        class="inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1"
                        :class="!affordConcern ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-white text-gray-600 ring-gray-200'">
                    Confirm
                </button>
                <button type="button"
                        @click="affordConcern = true"
                        class="inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1"
                        :class="affordConcern ? 'bg-amber-50 text-amber-950 ring-amber-200' : 'bg-white text-gray-600 ring-gray-200'">
                    Raise concern
                </button>
            </div>
        @endif
        <details class="rounded-xl ring-1 ring-brand/10 bg-white">
            <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-bold text-brand [&::-webkit-details-marker]:hidden">
                View calculation
            </summary>
            @include('admin.loan-applications.review._checklist_phase_panels', [
                'phase' => 'capacity',
                'section' => 'affordability',
                'deskPerson' => $deskPerson,
                'deskG' => $deskG,
                'deskM' => $deskM,
            ])
        </details>
    </div>
</div>
