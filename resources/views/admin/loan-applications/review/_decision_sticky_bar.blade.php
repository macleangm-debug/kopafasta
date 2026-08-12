@php
    $stage = $record->current_stage ?? 'submitted';
    $isScreeningSticky = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
    $isCommitteeSticky = $stage === 'pre_approval';
    $recType = data_get($review, 'recommendation.type');
    $canReview = auth()->user()?->hasPermission('applications.review');
    $workspace = $workspace ?? request('workspace', 'checklist');
    // Decision desk lives on the Decision tab — do not cover the checklist.
    $showScreeningSticky = $isScreeningSticky && $canReview && empty($recType) && $workspace === 'decision';
    $showCommitteeSticky = $isCommitteeSticky && collect($availableActions ?? [])->isNotEmpty() && $workspace === 'decision';
    $decisionPanelUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'decision',
    ]).'#review-recommendation';
@endphp

@if ($showScreeningSticky || $showCommitteeSticky)
    <div class="fixed inset-x-0 bottom-0 z-40 pointer-events-none">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-4 pointer-events-auto">
            <div class="rounded-2xl bg-brand text-white shadow-2xl ring-1 ring-brand-gold/40 px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
                @if ($showScreeningSticky)
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand-gold">Screening team · Decision desk</p>
                        <p class="text-sm font-bold mt-0.5 truncate">Approve, Reject, or Counter-offer from this Decision tab</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ $decisionPanelUrl }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold rounded-lg bg-white/10 hover:bg-white/20 px-3 py-2 ring-1 ring-white/20 transition">
                            Open Decision tab
                        </a>
                        <button type="button"
                                data-open-dialog="recommend-{{ $record->id }}"
                                class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                            Record decision
                        </button>
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
