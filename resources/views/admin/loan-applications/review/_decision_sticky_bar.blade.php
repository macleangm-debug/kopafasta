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
    $pendingRejection = app(\App\Services\CapacityAutoRejectService::class)->isPending($record)
        || (is_array($readiness) && (
            ($readiness['status'] ?? '') === 'pending_rejection'
            || ($readiness['pending_rejection'] ?? false)
            || (($readiness['decision_status']['state'] ?? '') === 'pending_rejection')
        ));
    $suggestionLabel = is_array($readiness) ? (string) ($readiness['suggestion_label'] ?? '') : '';
    $nextStep = is_array($readiness) ? (($readiness['next_steps'][0] ?? null)) : null;
    $wizardEntry = app(\App\Services\ScreeningSequenceService::class)->wizardEntry($record);
    $continueHref = is_array($readiness) && filled($readiness['primary_href'] ?? null)
        ? (string) $readiness['primary_href']
        : (is_array($nextStep) && filled($nextStep['href'] ?? null)
            ? (string) $nextStep['href']
            : $wizardEntry['href']);
    $continueLabel = $pendingRejection
        ? ((is_array($readiness) ? ($readiness['primary_cta'] ?? null) : null) ?: 'View parked status')
        : ($ready
            ? 'Continue to decision'
            : ((is_array($readiness) ? ($readiness['primary_cta'] ?? $readiness['primary_block_cta'] ?? null) : null) ?: $wizardEntry['cta']));

    $decisionPanelUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'decision',
    ]).'#review-recommendation';

    // Show sticky on checklist when guiding next step; on decision when recording.
    // Capacity park: still show sticky, but as Pending automatic rejection (not ordinary next checks).
    $showScreeningSticky = ! ($fileIsClosed ?? $record->isClosed())
        && $isScreeningSticky && $canReview && empty($recType)
        && in_array($workspace, ['overview', 'checklist', 'decision'], true);
    $showCommitteeSticky = ! ($fileIsClosed ?? $record->isClosed())
        && $isCommitteeSticky && collect($availableActions ?? [])->isNotEmpty() && $workspace === 'decision';
    $showManagementSticky = ! ($fileIsClosed ?? $record->isClosed())
        && $isManagementSticky && collect($availableActions ?? [])->isNotEmpty() && $workspace === 'decision';
@endphp

@if ($showScreeningSticky || $showCommitteeSticky || $showManagementSticky)
    <div class="fixed inset-x-0 bottom-0 z-40 pointer-events-none">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-4 pointer-events-auto">
            <div class="rounded-2xl {{ $pendingRejection ? 'bg-rose-900 ring-rose-300/40' : 'bg-brand ring-brand-gold/40' }} text-white shadow-2xl ring-1 px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
                @if ($showScreeningSticky)
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest font-semibold {{ $pendingRejection ? 'text-rose-200' : 'text-brand-gold' }}">
                            {{ $pendingRejection ? 'System · Capacity park' : 'Screening team · Guided next step' }}
                        </p>
                        <p class="text-sm font-bold mt-0.5 truncate">
                            @if ($pendingRejection)
                                Pending automatic rejection
                            @elseif (! $ready)
                                {{ is_array($readiness) ? ($readiness['status_label'] ?? 'Review in progress') : 'Review in progress' }}
                            @elseif ($workspace !== 'decision')
                                All required screening checks complete
                            @else
                                Record {{ $suggestionLabel !== '' ? $suggestionLabel : 'your recommendation' }} on this Decision tab
                            @endif
                        </p>
                        @if ($pendingRejection && is_array($readiness) && filled($readiness['pending_rejection_detail'] ?? $readiness['detail'] ?? null))
                            <p class="text-xs text-white/80 mt-1 line-clamp-2">{{ $readiness['pending_rejection_detail'] ?? $readiness['detail'] }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        @if ($pendingRejection)
                            <a href="{{ $continueHref }}"
                               class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-white text-rose-900 hover:bg-rose-50 px-4 py-2.5 shadow-sm">
                                {{ $continueLabel }}
                            </a>
                        @elseif (! $ready)
                            <a href="{{ $continueHref }}"
                               class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                                {{ $continueLabel }}
                            </a>
                        @elseif ($workspace !== 'decision')
                            <a href="{{ $decisionPanelUrl }}"
                               class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                                Continue to decision
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
