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
    $decisionUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'decision',
    ]).'#review-recommendation';
    $checklistUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'checklist',
    ]).'#review-desk';
@endphp

<div id="screening-readiness" class="rounded-2xl overflow-hidden shadow-sm ring-1 {{ $shell }} bg-gradient-to-br text-white scroll-mt-24">
    <div class="px-5 py-4 sm:px-6 sm:py-5 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-white/70">Screening readiness</p>
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
            @if ($readiness['ready'] ?? false)
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
                    Suggested decision unlocks when every subject is reviewed.
                </p>
            @endif
        </div>
    </div>
</div>
