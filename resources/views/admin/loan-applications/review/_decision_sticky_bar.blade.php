@php
    $stage = $record->current_stage ?? 'submitted';
    $isScreeningSticky = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
    $isCommitteeSticky = $stage === 'pre_approval';
    $isManagementSticky = $stage === 'awaiting_management';
    $recType = data_get($review, 'recommendation.type');
    $canReview = auth()->user()?->hasPermission('applications.review');
    $workspace = $workspace ?? request('workspace', 'checklist');
    $readiness = $screeningReadiness ?? null;
    $ready = is_array($readiness) ? (bool) ($readiness['ready'] ?? false) : false;
    $suggestionLabel = is_array($readiness) ? (string) ($readiness['suggestion_label'] ?? '') : '';
    $nextStep = is_array($readiness) ? (($readiness['next_steps'][0] ?? null)) : null;
    $incomeGateOpen = is_array($readiness) && ! empty($readiness['income_gate_open']);
    $continueHref = is_array($nextStep) && filled($nextStep['href'] ?? null)
        ? (string) $nextStep['href']
        : (route('admin.loan-applications.show', [
            'loan_application' => $record,
            'workspace' => 'checklist',
        ]).'#review-desk');
    $continueLabel = $incomeGateOpen
        ? 'Open Gate 2 · Statements vs revenue'
        : 'Continue checklist';

    $decisionPanelUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'decision',
    ]).'#review-recommendation';

    // Show sticky on checklist when guiding next step; on decision when recording.
    $showScreeningSticky = ! ($fileIsClosed ?? $record->isClosed())
        && $isScreeningSticky && $canReview && empty($recType)
        && in_array($workspace, ['checklist', 'decision'], true);
    $showCommitteeSticky = ! ($fileIsClosed ?? $record->isClosed())
        && $isCommitteeSticky && collect($availableActions ?? [])->isNotEmpty() && $workspace === 'decision';
    $showManagementSticky = ! ($fileIsClosed ?? $record->isClosed())
        && $isManagementSticky && collect($availableActions ?? [])->isNotEmpty() && $workspace === 'decision';
@endphp

@if ($showScreeningSticky || $showCommitteeSticky || $showManagementSticky)
    <div class="fixed inset-x-0 bottom-0 z-40 pointer-events-none">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-4 pointer-events-auto">
            <div class="rounded-2xl bg-brand text-white shadow-2xl ring-1 ring-brand-gold/40 px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
                @if ($showScreeningSticky)
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand-gold">Screening team · Guided next step</p>
                        <p class="text-sm font-bold mt-0.5 truncate">
                            @if (! $ready && $incomeGateOpen)
                                Gate 2 — match financial statements to profile monthly revenue first
                            @elseif (! $ready)
                                Finish the checklist before recording a decision
                            @elseif ($workspace !== 'decision')
                                Ready — {{ $suggestionLabel !== '' ? $suggestionLabel : 'open Decision' }}
                            @else
                                Record {{ $suggestionLabel !== '' ? $suggestionLabel : 'your recommendation' }} on this Decision tab
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        @if (! $ready)
                            <a href="{{ $continueHref }}"
                               class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                                {{ $continueLabel }}
                            </a>
                        @elseif ($workspace !== 'decision')
                            <a href="{{ $decisionPanelUrl }}"
                               class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                                Go to Decision
                            </a>
                        @else
                            <button type="button"
                                    data-open-dialog="recommend-{{ $record->id }}"
                                    class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                                Record decision
                            </button>
                        @endif
                    </div>
                @elseif ($showManagementSticky)
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand-gold">Credit management</p>
                        <p class="text-sm font-bold mt-0.5 truncate">Approve within authority, refer back, or reject</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ $decisionPanelUrl }}"
                           class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                            Go to decision
                        </a>
                    </div>
                @else
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand-gold">Credit committee</p>
                        <p class="text-sm font-bold mt-0.5 truncate">Validate screening or record a different decision</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ $decisionPanelUrl }}"
                           class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                            Go to decision
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
    {{-- Spacer so page content is not hidden behind the bar --}}
    <div class="h-24" aria-hidden="true"></div>
@endif
