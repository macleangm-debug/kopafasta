@php
    $rec = $review['recommendation'] ?? [];
    $affordPass = (bool) ($affordability['pass'] ?? false);
    $affordFail = ($affordability['verdict'] ?? '') === 'fail' || ! $affordPass;
    $counter = $counterOffer ?? ($review['counter_offer'] ?? null);
    $stage = $record->current_stage ?? 'submitted';
    $isCreditStage = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
    $isCommitteeStage = $stage === 'pre_approval';
    $showAnalystPanel = $isCreditStage || (! $isCommitteeStage && ! empty($rec['type']));
    $showCommitteePanel = $isCommitteeStage;
    if ($isCommitteeStage) {
        $showAnalystPanel = false;
    }
    $viewer = auth()->user();
    $canReview = (bool) ($viewer?->hasPermission('applications.review'));
    $screeningActions = collect($availableActions ?? []);
    $canPushRecommendation = $isCreditStage && $canReview && empty($rec['type']);
    $hasScreeningActions = $screeningActions->isNotEmpty() || $canPushRecommendation;
@endphp

<div id="review-recommendation" class="scroll-mt-24 mb-2 space-y-4">
    @if ($showAnalystPanel)
    {{-- Same chrome as committee decision: gold ring, Decide here, Your recommendation --}}
    <div @class([
        'bg-white rounded-2xl shadow-sm ring-1 overflow-hidden',
        'ring-brand-gold ring-2' => $isCreditStage,
        'ring-brand/10' => ! $isCreditStage,
    ])>
        <div @class([
            'px-5 sm:px-6 py-4 border-b flex flex-wrap items-start justify-between gap-3',
            'border-brand-gold/30 bg-gradient-to-r from-brand-gold/20 to-white' => $isCreditStage,
            'border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white' => ! $isCreditStage,
        ])>
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand">Step 1 · Screening team</p>
                <h3 class="text-base font-bold text-gray-900 mt-0.5">Record the screening recommendation</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Review CRB and affordability above, then recommend approve or counter, reject, or return for documents — same decision desk pattern as committee.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($isCreditStage)
                    <span class="text-xs font-semibold rounded-full px-3 py-1 bg-brand-gold text-brand ring-1 ring-brand/20">Decide here</span>
                @endif
                @if (! empty($rec['type']))
                    <span class="text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800">
                        Rec: {{ str_replace('_', ' ', ucfirst($rec['type'])) }}
                    </span>
                @endif
            </div>
        </div>

        <div class="p-5 sm:p-6">
            <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm mb-3">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Requested</dt>
                    <dd class="font-bold text-gray-900 mt-1">{{ format_money((float) $record->requested_amount) }}</dd>
                </div>
                @if ($record->recommended_amount)
                    <div class="rounded-xl bg-brand-muted/60 ring-1 ring-brand/15 px-3 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-brand">Your recommended amount</dt>
                        <dd class="font-bold text-brand mt-1">{{ format_money((float) $record->recommended_amount) }}</dd>
                    </div>
                @elseif ($counter && ($counter['amount'] ?? 0) > 0)
                    <div class="rounded-xl bg-brand-gold/15 ring-1 ring-brand-gold/40 px-3 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-brand">Max affordable (counter)</dt>
                        <dd class="font-bold text-brand mt-1">{{ format_money((float) $counter['amount']) }}</dd>
                        <dd class="text-[10px] text-brand/70 mt-0.5">Est. {{ format_money((float) ($counter['installment'] ?? 0)) }}/mo</dd>
                    </div>
                @else
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Recommended amount</dt>
                        <dd class="font-medium text-gray-500 mt-1">Not set yet</dd>
                    </div>
                @endif
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Affordability</dt>
                    <dd @class([
                        'font-bold mt-1',
                        'text-emerald-800' => $affordPass,
                        'text-amber-800' => ! $affordPass && ($affordability['verdict'] ?? '') === 'warn',
                        'text-rose-800' => $affordFail,
                    ])>
                        {{ $affordPass ? 'Pass' : (($affordability['status_label'] ?? null) ?: ($affordFail ? 'Fail' : 'Review')) }}
                    </dd>
                </div>
            </dl>

            @if (! empty($rec['type']))
                <div class="rounded-xl bg-brand-muted/60 ring-1 ring-brand/15 px-4 py-3 text-sm mb-3">
                    <p class="font-semibold text-brand">
                        Recommendation on file:
                        <span class="capitalize">{{ str_replace('_', ' ', $rec['type']) }}</span>
                    </p>
                    @if (! empty($rec['rationale_label']))
                        <p class="text-xs font-semibold text-brand/80 mt-2">{{ $rec['rationale_label'] }}</p>
                    @endif
                    @if (! empty($rec['remarks']))
                        <p class="text-brand/80 mt-1">{{ $rec['remarks'] }}</p>
                    @endif
                    @if (! empty($rec['recommended_by']))
                        <p class="text-xs text-brand/70 mt-2">
                            By {{ $rec['recommended_by']->name ?? 'Staff' }}
                            @if (! empty($rec['recommended_at']))
                                · {{ $rec['recommended_at']->format('d M Y, H:i') }}
                            @endif
                        </p>
                    @endif
                </div>
            @elseif ($affordFail && $isCreditStage)
                @php $autoReject = app(\App\Services\UnderwritingSettingsService::class)->automaticRejectionEnabled(); @endphp
                <p class="text-sm text-red-700 bg-red-50 ring-1 ring-red-100 rounded-lg px-4 py-3 mb-3">
                    @if ($autoReject)
                        Affordability failed at requested amount — reject the application or return for documents.
                    @else
                        Affordability failed — use <span class="font-semibold">Push recommendation to committee</span> for a counter-offer, or return for documents.
                    @endif
                </p>
            @elseif ($isCreditStage)
                <p class="text-sm text-gray-500 mb-3">No screening recommendation recorded yet — choose an action below.</p>
            @endif

            @if ($hasScreeningActions)
                @php
                    $actionsForButtons = $screeningActions;
                    if ($canPushRecommendation && ! $actionsForButtons->contains(fn ($a) => ($a['key'] ?? '') === 'submit_recommendation')) {
                        $actionsForButtons = $actionsForButtons->push([
                            'key' => 'submit_recommendation',
                            'label' => 'Push recommendation to committee',
                            'to_stage' => 'pre_approval',
                            'permission' => 'applications.review',
                        ]);
                    }
                @endphp
                <div class="mt-4 rounded-2xl bg-gradient-to-br from-brand-muted/40 to-white ring-1 ring-brand/15 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-brand">Your recommendation</p>
                            <p class="text-xs text-gray-500 mt-0.5">Choose one action — this moves the file to the committee queue.</p>
                        </div>
                        <a href="{{ route('admin.teams.screening') }}"
                           class="text-xs font-semibold text-brand hover:underline">
                            Screening queue →
                        </a>
                    </div>
                    @include('admin.loan-applications._workflow_actions', ['availableActions' => $actionsForButtons])
                </div>
            @elseif ($isCreditStage)
                <p class="mt-4 text-sm text-amber-900 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-4 py-3">
                    No screening actions available for your role on this file. You need the <span class="font-semibold">applications.review</span> permission.
                </p>
            @endif

            @if ($isCreditStage)
                <div class="mt-6 border-t border-gray-100 pt-5 space-y-6" id="screening-decision-panel">
                    @include('admin.loan-applications._workflow', ['showHistory' => false, 'showStepper' => false])
                    @include('admin.loan-applications._loan-link')
                </div>
            @endif
        </div>
    </div>
    @endif

    @if ($showCommitteePanel)
    <div class="bg-white rounded-2xl shadow-sm ring-1 overflow-hidden ring-brand-gold ring-2">
        <div class="px-5 sm:px-6 py-4 border-b border-brand-gold/30 bg-gradient-to-r from-brand-gold/20 to-white flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand">Step 2 · Credit committee</p>
                <h3 class="text-base font-bold text-gray-900 mt-0.5">Record the committee decision</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Compare CRB vs screening above, then issue an offer, final-approve, reject, or return for documents.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold rounded-full px-3 py-1 bg-brand-gold text-brand ring-1 ring-brand/20">Decide here</span>
                @if (! empty($rec['offer_status']))
                    @php
                        $offerTone = match ($rec['offer_status']) {
                            'accepted' => 'bg-emerald-100 text-emerald-800',
                            'declined' => 'bg-red-100 text-red-800',
                            'pending_borrower' => 'bg-amber-100 text-amber-900',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <span class="text-xs font-semibold rounded-full px-3 py-1 {{ $offerTone }}">
                        Offer: {{ match ($rec['offer_status']) {
                            'declined' => 'Declined by borrower',
                            'pending_borrower' => 'Pending borrower',
                            default => str_replace('_', ' ', ucfirst($rec['offer_status'])),
                        } }}
                    </span>
                @endif
            </div>
        </div>

        <div class="p-5 sm:p-6">
        <dl class="grid sm:grid-cols-2 gap-4 text-sm mb-3">
            @if ($record->offered_amount)
                <div class="rounded-xl bg-brand-gold/15 ring-1 ring-brand-gold/40 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-brand">Offered to borrower</dt>
                    <dd class="font-bold text-brand mt-1">{{ format_money((float) $record->offered_amount) }}</dd>
                </div>
            @else
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Offered to borrower</dt>
                    <dd class="font-medium text-gray-500 mt-1">Not issued yet</dd>
                </div>
            @endif
            @if ($record->recommended_amount)
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-brand">From analyst</dt>
                    <dd class="font-bold text-brand mt-1">{{ format_money((float) $record->recommended_amount) }}</dd>
                </div>
            @endif
        </dl>

        @if ($record->alternative_loan_product_id && $record->alternativeProduct)
            <p class="text-sm text-brand bg-brand-muted ring-1 ring-brand/15 rounded-lg px-4 py-3">
                Asset-backed alternative suggested:
                <span class="font-semibold">{{ $record->alternativeProduct->name }}</span>
            </p>
        @endif

        @if (($availableActions ?? collect())->isNotEmpty())
            <div class="mt-4 rounded-2xl bg-gradient-to-br from-brand-muted/40 to-white ring-1 ring-brand/15 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-brand">Your decision</p>
                        <p class="text-xs text-gray-500 mt-0.5">Choose one action — this moves the file forward.</p>
                    </div>
                    <a href="{{ route('admin.loan-applications.pre-approvals') }}"
                       class="text-xs font-semibold text-brand hover:underline">
                        Committee queue →
                    </a>
                </div>
                @include('admin.loan-applications._workflow_actions')
            </div>
        @else
            <p class="mt-4 text-sm text-amber-900 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-4 py-3">
                No committee actions available for your role on this file. Ask an admin to grant pre-approve / approve / reject permissions.
            </p>
        @endif

        <div class="mt-6 border-t border-gray-100 pt-5 space-y-6" id="decision-panel">
            @include('admin.loan-applications._workflow', ['showHistory' => false, 'showStepper' => false])
            @include('admin.loan-applications._loan-link')
            @include('admin.loan-applications.review._contract')
        </div>
        </div>
    </div>
    @endif
</div>
