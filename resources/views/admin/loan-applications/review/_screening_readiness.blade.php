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
    $blockingItems = $readiness['blocking_items'] ?? [];
    $needsAttention = $readiness['needs_attention'] ?? [];
    $autoCompleted = $readiness['auto_completed'] ?? [];
    $done = (int) ($readiness['checklist_done'] ?? 0);
    $total = (int) ($readiness['checklist_total'] ?? 0);
    $percent = (int) ($readiness['checklist_percent'] ?? ($total > 0 ? round($done / $total * 100) : 0));
    $ready = (bool) ($readiness['ready'] ?? false);
    $statusLabel = $readiness['status_label'] ?? ($ready ? 'Ready for decision' : 'Review in progress');
    $needCount = count($needsAttention);
    $blockCount = count($blockingItems);
    $primaryHref = $isCommitteeStage
        ? $decisionUrl
        : ($readiness['primary_href'] ?? ($ready ? $decisionUrl : ''));
    $primaryLabel = $isCommitteeStage
        ? 'Open decision'
        : ($ready ? 'Continue to decision' : ($readiness['primary_cta'] ?? 'Continue'));
    $docService = app(\App\Services\ApplicationDocumentRequestService::class);
    $inboxRequests = collect($documentRequests ?? [])
        ->filter(fn ($req) => $docService->isInboxPending($req, $record))
        ->sortByDesc('updated_at')
        ->values();
@endphp

<details id="screening-readiness"
         class="group rounded-2xl overflow-hidden shadow-sm ring-1 {{ $shell }} bg-gradient-to-br text-white scroll-mt-24">
    <summary class="cursor-pointer list-none px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
        <div class="min-w-0 flex-1">
            <p class="text-[10px] uppercase tracking-widest font-semibold text-white/70">Screening review</p>
            <p class="text-sm font-bold tabular-nums mt-0.5">
                {{ $done }} of {{ $total }} complete
                @if ($needCount > 0)
                    · {{ $needCount }} need attention
                @endif
            </p>
            <div class="mt-2 h-1.5 w-full max-w-xs rounded-full bg-white/20 overflow-hidden">
                <div class="h-full bg-brand-gold" style="width: {{ $percent }}%"></div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge }}">
                    {{ $statusLabel }}
                </span>
                @if ($blockCount > 0)
                    <span class="rounded-lg bg-rose-100 text-rose-950 px-2 py-0.5 text-[11px] font-bold">
                        {{ $blockCount }} {{ $blockCount === 1 ? 'item currently blocks Committee' : 'items currently block Committee' }}
                    </span>
                @elseif ($ready)
                    <span class="rounded-lg bg-emerald-100 text-emerald-950 px-2 py-0.5 text-[11px] font-bold">
                        All required screening checks complete
                    </span>
                @endif
                @if (($readiness['auto_complete_count'] ?? 0) > 0)
                    <span class="rounded-lg bg-white/15 px-2 py-0.5 text-[11px] font-semibold group-open:hidden">
                        {{ $readiness['auto_complete_count'] }} system-checked
                    </span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if ($primaryHref !== '')
                <a href="{{ $primaryHref }}"
                   @click.stop
                   class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 hover:brightness-95 shadow-sm">
                    {{ $primaryLabel }}
                </a>
            @endif
            <span class="text-[11px] text-white/70 group-open:hidden">Expand</span>
            <span class="text-[11px] text-white/70 hidden group-open:inline">Collapse</span>
        </div>
    </summary>

    <div class="border-t border-white/10 bg-black/20 px-4 sm:px-5 py-3 space-y-4 text-slate-900">
        @if ($autoCompleted !== [])
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70 mb-1.5">System already checked</p>
                <ul class="flex flex-wrap gap-1.5">
                    @foreach (array_slice($autoCompleted, 0, 10) as $item)
                        <li class="rounded-lg bg-emerald-100/90 text-emerald-950 px-2.5 py-1 text-[11px] font-semibold">
                            ✓ {{ $item['label'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($inboxRequests->isNotEmpty())
            <div id="submissions-inbox">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/80 mb-1.5">
                    Borrower submissions · {{ $inboxRequests->count() }}
                </p>
                <ul class="space-y-1.5">
                    @foreach ($inboxRequests as $docReq)
                        @php
                            $kind = $docService->borrowerActionKind($docReq);
                            $kindLabel = $docService->screeningKindLabel($docReq);
                            $reviewUrl = $docService->screeningReviewUrl($docReq, $record, collect($review['guarantors'] ?? [])->all());
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2">
                            <span class="text-xs font-semibold text-emerald-950">
                                {{ $docReq->label }}
                                <span class="block font-medium text-emerald-800">{{ $docReq->subjectRoleLabel($groupReview ?? null) }}</span>
                                <span class="font-medium text-emerald-800">{{ $kindLabel }}</span>
                            </span>
                            <a href="{{ $reviewUrl }}" class="shrink-0 text-[11px] font-bold text-brand underline underline-offset-2">
                                {{ $kind === 'income' ? 'Review statements' : ($kind === 'collateral' ? 'Open collateral' : 'Open request') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($blockingItems !== [])
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-rose-100 mb-1.5">Blocking Committee</p>
                <ul class="space-y-1.5">
                    @foreach (array_slice($blockingItems, 0, 6) as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-3 py-2">
                            <span class="min-w-0">
                                <span class="text-xs font-semibold text-rose-950">{{ $item['label'] }}</span>
                                @if (! empty($item['detail']))
                                    <span class="block text-[11px] text-rose-800/80">{{ $item['detail'] }}</span>
                                @endif
                            </span>
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
                    {{ count($needsAttention) }} {{ count($needsAttention) === 1 ? 'thing needs' : 'things need' }} attention
                </p>
                <ul class="space-y-1.5">
                    @foreach (array_slice($needsAttention, 0, 6) as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/95 ring-1 ring-white/40 px-3 py-2">
                            <span class="min-w-0">
                                <span class="text-xs font-semibold text-slate-900">{{ $item['label'] }}</span>
                                @if (! empty($item['detail']))
                                    <span class="block text-[11px] text-slate-600">{{ $item['detail'] }}</span>
                                @endif
                            </span>
                            <a href="{{ $item['href'] }}" class="shrink-0 text-[11px] font-bold text-brand underline underline-offset-2">
                                {{ $item['cta'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($ready)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2">
                <p class="text-xs font-semibold text-emerald-950">All required screening checks complete</p>
                <a href="{{ $decisionUrl }}" class="text-[11px] font-bold text-brand underline underline-offset-2">Continue to decision</a>
            </div>
        @endif
    </div>
</details>
