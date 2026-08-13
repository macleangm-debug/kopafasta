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
    $primaryHref = ($isCommitteeStage || ($readiness['ready'] ?? false))
        ? $decisionUrl
        : ($readiness['income_gate_href'] ?? $checklistUrl);
    $primaryLabel = match (true) {
        $isCommitteeStage => 'Sprint →',
        ($readiness['ready'] ?? false) => 'Decision →',
        ! empty($readiness['income_gate_open']) => 'Gate 2 →',
        default => 'Continue →',
    };
@endphp

<div id="screening-readiness"
     class="rounded-2xl overflow-hidden shadow-sm ring-1 {{ $shell }} bg-gradient-to-br text-white scroll-mt-24"
     x-data="{ openNext: {{ ! empty($nextSteps) && ! ($readiness['ready'] ?? false) ? 'true' : 'false' }} }">
    <div class="px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 min-w-0">
            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge }}">
                {{ $readiness['suggestion_label'] }}
            </span>
            <span class="text-sm font-bold tabular-nums">
                {{ $readiness['checklist_done'] }}/{{ $readiness['checklist_total'] }}
            </span>
            @if (($readiness['checklist_failed'] ?? 0) > 0)
                <span class="rounded-lg bg-rose-100 text-rose-950 px-2 py-0.5 text-[11px] font-bold tabular-nums">
                    {{ $readiness['checklist_failed'] }}✗
                </span>
            @endif
            @if (! empty($readiness['critical_fails']))
                <span class="rounded-lg bg-rose-100 text-rose-950 px-2 py-0.5 text-[11px] font-bold tabular-nums">
                    {{ count($readiness['critical_fails']) }} risk
                </span>
            @endif
            @if (! empty($readiness['income_gate_open']))
                <span class="rounded-lg bg-brand-gold/25 text-brand-gold px-2 py-0.5 text-[11px] font-bold">Gate 2</span>
            @endif
        </div>

        <a href="{{ $primaryHref }}"
           class="shrink-0 inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 hover:brightness-95 shadow-sm">
            {{ $primaryLabel }}
        </a>
    </div>

    @if (! empty($nextSteps) && ! ($readiness['ready'] ?? false))
        <div class="border-t border-white/10 bg-black/15 px-4 sm:px-5 py-2.5">
            <div class="flex flex-wrap gap-1.5">
                @foreach (array_slice($nextSteps, 0, 5) as $step)
                    <a href="{{ $step['href'] }}"
                       @class([
                           'inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-bold ring-1 transition hover:brightness-95',
                           'bg-brand-gold text-brand ring-brand-gold/50' => ($step['tone'] ?? '') === 'gate',
                           'bg-rose-100 text-rose-950 ring-rose-200' => ($step['tone'] ?? '') === 'critical',
                           'bg-amber-100 text-amber-950 ring-amber-200' => ($step['tone'] ?? '') === 'fail',
                           'bg-white/95 text-slate-900 ring-white/40' => ! in_array($step['tone'] ?? '', ['gate', 'critical', 'fail'], true),
                       ])>
                        {{ $step['label'] }} →
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
