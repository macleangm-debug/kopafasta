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
    $isCommitteeStage = ($record->current_stage ?? null) === 'pre_approval';
    $decisionUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'decision',
    ]).($isCommitteeStage ? '#committee-sprint' : '#review-recommendation');
    $blockingItems = $readiness['blocking_items'] ?? [];
    $needsAttention = $readiness['needs_attention'] ?? [];
    $unresolved = $readiness['unresolved'] ?? array_merge($blockingItems, $needsAttention);
    $autoCompleted = $readiness['auto_completed'] ?? [];
    $submissions = $readiness['submissions'] ?? [];
    $snapshot = $readiness['overview_snapshot'] ?? [];
    $gateChips = $readiness['gate_chips'] ?? [];
    $done = (int) ($readiness['checklist_done'] ?? 0);
    $total = (int) ($readiness['checklist_total'] ?? 0);
    $percent = (int) ($readiness['checklist_percent'] ?? ($total > 0 ? round($done / $total * 100) : 0));
    $ready = (bool) ($readiness['ready'] ?? false);
    $needCount = count($needsAttention);
    $blockCount = count($blockingItems);
    $attentionCount = (int) ($readiness['attention_count'] ?? count($unresolved));
    $primaryHref = $isCommitteeStage
        ? $decisionUrl
        : ($readiness['primary_href'] ?? ($ready ? $decisionUrl : ''));
    $primaryLabel = $isCommitteeStage
        ? 'Open decision'
        : ($ready ? 'Continue to decision' : ($readiness['primary_cta'] ?? 'Continue'));
    $defaultTab = $attentionCount > 0 ? 'attention' : ($submissions !== [] ? 'submissions' : 'system');
    $memberSummaries = $readiness['member_summaries'] ?? [];
    $decisionStatus = $readiness['decision_status'] ?? null;
    $showMembersTab = count($memberSummaries) > 1;
@endphp

<details id="screening-readiness"
         class="group rounded-2xl overflow-hidden shadow-sm ring-1 {{ $shell }} bg-gradient-to-br text-white scroll-mt-24"
         x-data="{ tab: @js($defaultTab) }">
    <summary class="cursor-pointer list-none px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold tabular-nums">
                {{ $readiness['sequence']['summary_kicker'] ?? 'Screening review' }} · {{ $percent }}%
                @if ($attentionCount > 0)
                    · {{ $attentionCount }} {{ $attentionCount === 1 ? 'thing needs you' : 'things need you' }}
                @elseif ($ready)
                    · All required screening checks complete
                @endif
                @if ($blockCount > 0)
                    · {{ $blockCount }} {{ $blockCount === 1 ? 'blocker' : 'blockers' }}
                @endif
            </p>
            <p class="text-[11px] text-white/75 mt-0.5 tabular-nums">
                {{ $done }}/{{ $total }} complete
                @if ($needCount > 0)
                    · {{ $needCount }} need attention
                @endif
            </p>
            <div class="mt-2 h-1.5 w-full max-w-xs rounded-full bg-white/20 overflow-hidden">
                <div class="h-full bg-brand-gold" style="width: {{ $percent }}%"></div>
            </div>
            @if ($gateChips !== [])
                <p class="mt-2 text-[11px] text-white/80 leading-snug">
                    {{ collect($gateChips)->pluck('chip')->implode(' · ') }}
                </p>
            @endif
            @if (is_array($decisionStatus) && ($decisionStatus['state'] ?? '') === 'pending_rejection')
                <p class="mt-1.5 text-[11px] font-semibold text-brand-gold">
                    {{ $decisionStatus['headline'] }}
                    @if (! empty($decisionStatus['detail']))
                        · {{ $decisionStatus['detail'] }}
                    @endif
                    @if (! empty($decisionStatus['countdown']))
                        · {{ $decisionStatus['countdown'] }}
                    @endif
                </p>
            @endif
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if ($primaryHref !== '')
                <a href="{{ $primaryHref }}"
                   @click.stop
                   class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 hover:brightness-95 shadow-sm">
                    {{ $primaryLabel }}
                </a>
            @endif
            <svg class="size-4 text-white/80 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
        </div>
    </summary>

    <div class="border-t border-white/10 bg-black/20 px-4 sm:px-5 py-3 space-y-3 text-slate-900" @click.stop>
        @if ($snapshot !== [])
            <dl class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                @foreach ($snapshot as $fact)
                    <div class="rounded-lg bg-white/10 px-2.5 py-2 text-white">
                        <dt class="text-[10px] uppercase tracking-widest text-white/60">{{ $fact['label'] }}</dt>
                        <dd class="text-[11px] font-semibold mt-0.5 leading-snug">{{ $fact['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if (is_array($decisionStatus))
            <div class="rounded-lg px-3 py-2 {{ in_array($decisionStatus['state'] ?? '', ['pending_rejection', 'hard_failure'], true) ? 'bg-rose-950/40 ring-1 ring-rose-300/30' : 'bg-white/10' }}">
                <p class="text-[10px] uppercase tracking-widest text-white/60 font-semibold">Decision status</p>
                <p class="text-sm font-bold text-white mt-0.5">{{ $decisionStatus['headline'] ?? 'No decision blockers' }}</p>
                @if (! empty($decisionStatus['detail']))
                    <p class="text-[11px] text-white/80 mt-0.5">{{ $decisionStatus['detail'] }}</p>
                @endif
                @if (! empty($decisionStatus['countdown']))
                    <p class="text-[11px] font-semibold text-brand-gold mt-0.5">{{ $decisionStatus['countdown'] }}</p>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap gap-1 rounded-lg bg-black/20 p-1">
            @php
                $summaryTabs = [
                    'system' => 'System checked',
                    'submissions' => 'Borrower submissions',
                    'attention' => 'Needs attention',
                ];
                if ($showMembersTab) {
                    $summaryTabs['members'] = 'Members';
                }
            @endphp
            @foreach ($summaryTabs as $tabKey => $tabLabel)
                <button type="button"
                        @click="tab = @js($tabKey)"
                        class="rounded-md px-3 py-1.5 text-[11px] font-bold transition"
                        :class="tab === @js($tabKey) ? 'bg-white text-slate-900' : 'text-white/80 hover:bg-white/10'">
                    {{ $tabLabel }}
                    @if ($tabKey === 'attention' && $attentionCount > 0)
                        · {{ $attentionCount }}
                    @elseif ($tabKey === 'submissions' && count($submissions) > 0)
                        · {{ count($submissions) }}
                    @elseif ($tabKey === 'system' && count($autoCompleted) > 0)
                        · {{ count($autoCompleted) }}
                    @elseif ($tabKey === 'members' && $showMembersTab)
                        · {{ count($memberSummaries) }}
                    @endif
                </button>
            @endforeach
        </div>

        <div x-show="tab === 'system'" x-cloak>
            @if ($autoCompleted === [])
                <p class="text-xs text-white/80">No automatic checks have passed yet.</p>
            @else
                <ul class="flex flex-wrap gap-1.5">
                    @foreach ($autoCompleted as $item)
                        <li class="rounded-lg bg-emerald-100/90 text-emerald-950 px-2.5 py-1 text-[11px] font-semibold">
                            ✓ {{ $item['label'] }}
                            @if (! empty($item['detail']))
                                <span class="block font-medium text-emerald-800/80">{{ $item['detail'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div x-show="tab === 'submissions'" x-cloak>
            @php
                $inboxPending = collect($submissions)->where('status', 'uploaded')->values();
            @endphp
            @if ($inboxPending->isNotEmpty())
                <div id="submissions-inbox" class="mb-2">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/80 mb-1.5">Waiting for review</p>
                </div>
            @endif
            @if ($submissions === [])
                <p class="text-xs text-white/80">No borrower submissions listed yet.</p>
            @else
                <ul class="space-y-1.5">
                    @foreach ($submissions as $row)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/95 ring-1 ring-white/40 px-3 py-2">
                            <span class="min-w-0">
                                <span class="text-xs font-semibold text-slate-900">{{ $row['label'] }}</span>
                                <span class="block text-[11px] text-slate-600">{{ $row['detail'] }} · {{ display_label($row['status'] ?? '', 'document_request_status') ?: ($row['status'] ?? '') }}</span>
                            </span>
                            <a href="{{ $row['href'] }}" class="shrink-0 text-[11px] font-bold text-brand underline underline-offset-2">
                                {{ $row['cta'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div x-show="tab === 'attention'" x-cloak>
            @if ($unresolved === [])
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2">
                    <p class="text-xs font-semibold text-emerald-950">All required screening checks complete</p>
                    <a href="{{ $decisionUrl }}" class="text-[11px] font-bold text-brand underline underline-offset-2">Continue to decision</a>
                </div>
            @else
                <ul class="space-y-1.5">
                    @foreach ($unresolved as $item)
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
            @endif
        </div>

        @if ($showMembersTab)
            <div x-show="tab === 'members'" x-cloak>
                <ul class="grid sm:grid-cols-2 gap-2">
                    @foreach ($memberSummaries as $member)
                        <li>
                            <a href="{{ $member['href'] }}"
                               class="block rounded-lg bg-white/95 ring-1 ring-white/40 px-3 py-2.5 hover:bg-white">
                                <p class="text-xs font-bold text-slate-900">
                                    {{ $member['name'] }} — {{ $member['status'] }}
                                </p>
                                @if (! empty($member['chips']))
                                    <p class="text-[11px] text-slate-600 mt-0.5">{{ implode(' · ', $member['chips']) }}</p>
                                @endif
                                @if (! empty($member['issue']))
                                    <p class="text-[11px] text-amber-800 mt-0.5">{{ $member['issue'] }}</p>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</details>
