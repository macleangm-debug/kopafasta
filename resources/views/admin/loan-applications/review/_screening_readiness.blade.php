@php
    $readiness = $screeningReadiness ?? null;
    if (! is_array($readiness)) {
        $readiness = app(\App\Services\ScreeningReadinessService::class)->forApplication(
            $record,
            $review ?? [],
            $groupReview ?? null,
            $anomalies ?? [],
            auth()->user(),
        );
    }

    $tone = $readiness['tone'] ?? 'neutral';
    $shell = match ($tone) {
        'good' => 'from-emerald-700 to-emerald-900 ring-emerald-200',
        'bad' => 'from-rose-700 to-rose-900 ring-rose-200',
        'warn' => 'from-amber-600 to-amber-800 ring-amber-200',
        default => 'from-slate-700 to-slate-900 ring-slate-200',
    };
    $badge = match ($tone) {
        'good' => 'bg-emerald-100 text-emerald-950',
        'bad' => 'bg-rose-100 text-rose-950',
        'warn' => 'bg-amber-100 text-amber-950',
        default => 'bg-white/20 text-white',
    };
    $isCommitteeStage = ($record->current_stage ?? null) === 'pre_approval';
    $decisionUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'decision',
    ]).($isCommitteeStage ? '#committee-sprint' : '#review-recommendation');
    $checklistUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'checklist',
    ]).'#review-desk';
    $nextSteps = $readiness['next_steps'] ?? [];
@endphp

<div id="screening-readiness"
     class="rounded-2xl overflow-hidden shadow-sm ring-1 {{ $shell }} bg-gradient-to-br text-white scroll-mt-24"
     x-data="{ openNext: false }">
    <div class="px-5 py-4 sm:px-6 sm:py-5 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-white/70">
                    {{ $isCommitteeStage ? 'Committee readiness' : 'Screening readiness' }}
                </p>
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge }}">
                    {{ $readiness['suggestion_label'] }}
                </span>
                @if ($readiness['ready'] ?? false)
                    <span class="inline-flex rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-bold">Ready</span>
                @else
                    <span class="inline-flex rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-bold">Not ready</span>
                @endif
            </div>
            <h3 class="text-lg sm:text-xl font-bold mt-1.5 tracking-tight">{{ $readiness['headline'] }}</h3>
            <p class="text-sm text-white/85 mt-1 max-w-3xl">{{ $readiness['detail'] }}</p>

            @if (! empty($readiness['income_gate_open']))
                <div class="mt-3 max-w-3xl rounded-xl bg-brand-gold/20 ring-1 ring-brand-gold/40 px-3.5 py-2.5">
                    <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-brand-gold">Gate 2 · Start here</p>
                    <p class="text-sm font-semibold mt-0.5">
                        Match financial statements to the monthly revenue on the profile before other checklist work.
                        (Gate 1 is capacity auto-reject — this file already cleared that.)
                    </p>
                    @if (filled($readiness['income_gate_href'] ?? null))
                        <a href="{{ $readiness['income_gate_href'] }}"
                           class="inline-flex mt-2 text-xs font-bold text-brand-gold hover:underline">
                            Open statements vs revenue →
                        </a>
                    @endif
                </div>
            @endif

            <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                <span class="rounded-lg bg-white/10 px-2.5 py-1 tabular-nums">
                    Checklist {{ $readiness['checklist_done'] }}/{{ $readiness['checklist_total'] }}
                </span>
                <span class="rounded-lg bg-white/10 px-2.5 py-1 tabular-nums">
                    Subjects {{ ($readiness['subjects_total'] - $readiness['subjects_incomplete']) }}/{{ $readiness['subjects_total'] }} done
                </span>
                @if (($readiness['checklist_failed'] ?? 0) > 0)
                    <span class="rounded-lg bg-rose-100 text-rose-950 px-2.5 py-1 tabular-nums">
                        {{ $readiness['checklist_failed'] }} fail
                    </span>
                @endif
                @if (! empty($readiness['critical_fails']))
                    <span class="rounded-lg bg-rose-100 text-rose-950 px-2.5 py-1 tabular-nums">
                        {{ count($readiness['critical_fails']) }} high-risk
                    </span>
                @endif
            </div>

            @if (! empty($readiness['blockers']))
                <ul class="mt-3 space-y-1 max-w-3xl">
                    @foreach (array_slice($readiness['blockers'], 0, 4) as $blocker)
                        <li class="text-xs text-white/90 flex gap-2">
                            <span class="shrink-0 font-bold">•</span>
                            <span>{{ $blocker }}</span>
                        </li>
                    @endforeach
                </ul>
            @elseif (! empty($readiness['signals']))
                <ul class="mt-3 space-y-1 max-w-3xl">
                    @foreach (array_slice($readiness['signals'], 0, 3) as $signal)
                        <li class="text-xs text-white/90 flex gap-2">
                            <span class="shrink-0 font-bold">•</span>
                            <span>{{ $signal }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex flex-col sm:items-end gap-2 shrink-0">
            @if ($isCommitteeStage)
                <a href="{{ $decisionUrl }}"
                   class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 hover:brightness-95 shadow-sm">
                    Sprint critical areas →
                </a>
                <p class="text-[11px] text-white/70 sm:text-right max-w-[14rem]">
                    Same evidence as screening — change only what needs a reason, then decide.
                </p>
            @elseif ($readiness['ready'] ?? false)
                <a href="{{ $decisionUrl }}"
                   class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 hover:brightness-95 shadow-sm">
                    Go to Decision →
                </a>
                <p class="text-[11px] text-white/70 sm:text-right max-w-[14rem]">
                    Suggested: <span class="font-semibold text-white">{{ $readiness['suggestion_label'] }}</span>. You still confirm the final choice.
                </p>
            @else
                <a href="{{ $checklistUrl }}"
                   class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 hover:brightness-95 shadow-sm">
                    Continue checklist →
                </a>
                <p class="text-[11px] text-white/70 sm:text-right max-w-[14rem]">
                    Decision stays reachable — lean guidance waits until every subject is reviewed.
                </p>
            @endif
        </div>
    </div>

    @if (! empty($nextSteps))
        <div class="border-t border-white/10 bg-black/15 px-5 sm:px-6 py-3">
            <button type="button"
                    class="w-full flex items-center justify-between gap-3 text-left"
                    @click="openNext = !openNext"
                    :aria-expanded="openNext.toString()">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.18em] font-semibold text-white/60">Where to go next</p>
                    <p class="text-sm font-semibold text-white mt-0.5">
                        {{ count($nextSteps) }} action{{ count($nextSteps) === 1 ? '' : 's' }} to clear this progress bar
                    </p>
                </div>
                <svg class="size-4 text-white/70 transition shrink-0" :class="openNext ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
            <div x-show="openNext" x-cloak class="mt-3 space-y-2">
                @foreach ($nextSteps as $step)
                    @php
                        $stepTone = match ($step['tone'] ?? 'open') {
                            'gate' => 'bg-brand-gold text-brand ring-brand-gold/50',
                            'critical' => 'bg-rose-100 text-rose-950 ring-rose-200',
                            'fail' => 'bg-amber-100 text-amber-950 ring-amber-200',
                            default => 'bg-white/95 text-slate-900 ring-white/40',
                        };
                        $chip = match ($step['tone'] ?? 'open') {
                            'critical' => 'High risk',
                            'fail' => 'Failed',
                            default => 'Open',
                        };
                    @endphp
                    <a href="{{ $step['href'] }}"
                       class="flex flex-wrap items-start justify-between gap-2 rounded-xl px-3.5 py-2.5 ring-1 {{ $stepTone }} hover:brightness-95 transition">
                        <div class="min-w-0">
                            <p class="text-sm font-bold">{{ $step['label'] }}</p>
                            <p class="text-[11px] opacity-80 mt-0.5">{{ $step['detail'] }}</p>
                        </div>
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide rounded-md px-2 py-1 bg-black/5">{{ $chip }} →</span>
                    </a>
                @endforeach
                @if (! empty($readiness['na_note']))
                    <p class="text-[11px] text-white/70 pt-1">{{ $readiness['na_note'] }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
