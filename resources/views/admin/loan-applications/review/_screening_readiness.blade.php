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
    $blockingItems = $readiness['blocking_items'] ?? [];
    $needsAttention = $readiness['needs_attention'] ?? [];
    $autoCompleted = $readiness['auto_completed'] ?? [];
    $done = (int) ($readiness['checklist_done'] ?? 0);
    $total = (int) ($readiness['checklist_total'] ?? 0);
    $percent = (int) ($readiness['checklist_percent'] ?? ($total > 0 ? round($done / $total * 100) : 0));
    $ready = (bool) ($readiness['ready'] ?? false);
    $primaryHref = ($isCommitteeStage || $ready) ? $decisionUrl : ($readiness['income_gate_href'] ?? $checklistUrl);
    $primaryLabel = match (true) {
        $isCommitteeStage => 'Open decision',
        $ready => 'Open decision',
        ! empty($readiness['income_gate_open']) => 'Open statements',
        default => 'Continue',
    };
@endphp

<div id="screening-readiness"
     class="rounded-2xl overflow-hidden shadow-sm ring-1 {{ $shell }} bg-gradient-to-br text-white scroll-mt-24">
    <div class="px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[10px] uppercase tracking-widest font-semibold text-white/70">Screening review</p>
            <p class="text-sm font-bold tabular-nums mt-0.5">
                {{ $done }} of {{ $total }} checks complete · {{ $percent }}%
            </p>
            <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge }}">
                    {{ $readiness['suggestion_label'] }}
                </span>
                @if (($readiness['auto_complete_count'] ?? 0) > 0)
                    <span class="rounded-lg bg-white/15 px-2 py-0.5 text-[11px] font-semibold">
                        {{ $readiness['auto_complete_count'] }} completed automatically
                    </span>
                @endif
                @if ($blockingItems !== [])
                    <span class="rounded-lg bg-rose-100 text-rose-950 px-2 py-0.5 text-[11px] font-bold">
                        {{ count($blockingItems) }} blocking Committee
                    </span>
                @endif
            </div>
        </div>

        <a href="{{ $primaryHref }}"
           class="shrink-0 inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 hover:brightness-95 shadow-sm">
            {{ $primaryLabel }}
        </a>
    </div>

    @if (! $ready && ($blockingItems !== [] || $needsAttention !== [] || $autoCompleted !== []))
        <div class="border-t border-white/10 bg-black/20 px-4 sm:px-5 py-3 space-y-3 text-slate-900">
            @if ($blockingItems !== [])
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-rose-100 mb-1.5">Blocking Committee</p>
                    <ul class="space-y-1.5">
                        @foreach (array_slice($blockingItems, 0, 4) as $item)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-3 py-2">
                                <span class="text-xs font-semibold text-rose-950">{{ $item['label'] }}</span>
                                <a href="{{ $item['href'] }}" class="shrink-0 text-[11px] font-bold text-brand underline underline-offset-2">
                                    {{ $item['cta'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($needsAttention !== [])
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/80 mb-1.5">
                        {{ count($needsAttention) }} {{ count($needsAttention) === 1 ? 'thing needs' : 'things need' }} your attention
                    </p>
                    <ul class="space-y-1.5">
                        @foreach (array_slice($needsAttention, 0, 5) as $item)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/95 ring-1 ring-white/40 px-3 py-2">
                                <span class="text-xs font-semibold text-slate-900">{{ $item['label'] }}</span>
                                <a href="{{ $item['href'] }}" class="shrink-0 text-[11px] font-bold text-brand underline underline-offset-2">
                                    {{ $item['cta'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($autoCompleted !== [])
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70 mb-1.5">Completed automatically</p>
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach (array_slice($autoCompleted, 0, 8) as $item)
                            <li class="rounded-lg bg-emerald-100/90 text-emerald-950 px-2.5 py-1 text-[11px] font-semibold">
                                {{ $item['label'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
