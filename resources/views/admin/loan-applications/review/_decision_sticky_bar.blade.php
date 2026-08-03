@php
    $stage = $record->current_stage ?? 'submitted';
    $isScreeningSticky = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
    $isCommitteeSticky = $stage === 'pre_approval';
    $recType = data_get($review, 'recommendation.type');
    $canReview = auth()->user()?->hasPermission('applications.review');
    $showScreeningSticky = $isScreeningSticky && $canReview && empty($recType);
    $showCommitteeSticky = $isCommitteeSticky && collect($availableActions ?? [])->isNotEmpty();
@endphp

@if ($showScreeningSticky || $showCommitteeSticky)
    <div class="fixed inset-x-0 bottom-0 z-40 pointer-events-none">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-4 pointer-events-auto">
            <div class="rounded-2xl bg-brand text-white shadow-2xl ring-1 ring-brand-gold/40 px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
                @if ($showScreeningSticky)
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand-gold">Screening team · Same desk as committee</p>
                        <p class="text-sm font-bold mt-0.5 truncate">Record your recommendation, then push to committee</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="#review-recommendation"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold rounded-lg bg-white/10 hover:bg-white/20 px-3 py-2 ring-1 ring-white/20 transition">
                            Open panel
                        </a>
                        <button type="button"
                                data-open-dialog="recommend-{{ $record->id }}"
                                class="inline-flex items-center gap-1.5 text-sm font-bold rounded-lg bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm">
                            Push recommendation to committee
                        </button>
                    </div>
                @else
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand-gold">Credit committee</p>
                        <p class="text-sm font-bold mt-0.5 truncate">Record the committee decision on this file</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="#review-recommendation"
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
