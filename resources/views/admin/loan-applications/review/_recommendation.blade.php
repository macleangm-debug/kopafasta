@php
    $rec = $review['recommendation'] ?? [];
    $affordPass = (bool) ($affordability['pass'] ?? false);
    $affordFail = ($affordability['verdict'] ?? '') === 'fail' || ! $affordPass;
    $counter = $counterOffer ?? ($review['counter_offer'] ?? null);
    $stage = $record->current_stage ?? 'submitted';
    $isCreditStage = in_array($stage, ['credit_appraisal', 'screening'], true);
    $isCommitteeStage = in_array($stage, ['pre_approval', 'approval'], true);
@endphp

<div id="review-recommendation" class="scroll-mt-24 mb-6 space-y-4">
    {{-- Credit analyst recommendation panel --}}
    <div @class([
        'bg-white rounded-xl shadow-sm ring-1 p-6',
        'ring-brand ring-2' => $isCreditStage,
        'ring-gray-200' => ! $isCreditStage,
    ])>
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand">Step 1 · Credit analyst</p>
                <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Credit recommendation</h3>
                <p class="text-xs text-gray-500 mt-0.5">Suggest approve, counter, or return for documents — committee decides</p>
            </div>
            @if ($isCreditStage)
                <span class="text-xs font-semibold rounded-full px-3 py-1 bg-brand-muted text-brand">Active stage</span>
            @endif
        </div>

        <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm mb-4">
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Requested</dt>
                <dd class="font-bold text-gray-900 mt-1">{{ format_money((float) $record->requested_amount) }}</dd>
            </div>
            @if ($record->recommended_amount)
                <div class="rounded-lg bg-brand-muted/60 ring-1 ring-brand/15 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-brand">Recommended</dt>
                    <dd class="font-bold text-brand mt-1">{{ format_money((float) $record->recommended_amount) }}</dd>
                </div>
            @endif
            @if ($counter && ($counter['amount'] ?? 0) > 0)
                <div class="rounded-lg bg-brand-gold/15 ring-1 ring-brand-gold/40 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-brand">Max affordable (counter)</dt>
                    <dd class="font-bold text-brand mt-1">{{ format_money((float) $counter['amount']) }}</dd>
                    <dd class="text-[10px] text-brand/70 mt-0.5">Est. {{ format_money((float) ($counter['installment'] ?? 0)) }}/mo</dd>
                </div>
            @endif
        </dl>

        @if (! empty($rec['type']))
            <div class="rounded-lg bg-brand-muted/60 ring-1 ring-brand/15 px-4 py-3 text-sm">
                <p class="font-semibold text-brand">
                    Recommendation:
                    <span class="capitalize">{{ str_replace('_', ' ', $rec['type']) }}</span>
                </p>
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
        @elseif ($affordFail && $stage === 'credit_appraisal')
            @php $autoReject = app(\App\Services\UnderwritingSettingsService::class)->automaticRejectionEnabled(); @endphp
            <p class="text-sm text-red-700 bg-red-50 ring-1 ring-red-100 rounded-lg px-4 py-3">
                @if ($autoReject)
                    Affordability failed at requested amount — reject the application or return for documents.
                @else
                    Affordability failed at requested amount — recommend a counter-offer or suggest the asset-backed product.
                @endif
            </p>
        @else
            <p class="text-sm text-gray-500">No credit recommendation recorded yet.</p>
        @endif

        @if ($isCreditStage && ($availableActions ?? collect())->isNotEmpty())
            <div class="mt-4 border-t border-brand/10 pt-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand mb-3">Analyst actions</p>
                @include('admin.loan-applications._workflow_actions')
            </div>
        @endif
    </div>

    {{-- Committee / offer panel --}}
    <div @class([
        'bg-white rounded-xl shadow-sm ring-1 p-6',
        'ring-brand-gold ring-2' => $isCommitteeStage,
        'ring-gray-200' => ! $isCommitteeStage,
    ])>
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand">Step 2 · Credit committee</p>
                <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Committee decision &amp; offer</h3>
                <p class="text-xs text-gray-500 mt-0.5">Only this stage can approve, counter-offer, or reject after analyst recommendation</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($isCommitteeStage)
                    <span class="text-xs font-semibold rounded-full px-3 py-1 bg-brand-gold/30 text-brand">Active stage</span>
                @endif
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

        <dl class="grid sm:grid-cols-2 gap-4 text-sm mb-3">
            @if ($record->offered_amount)
                <div class="rounded-lg bg-brand-gold/15 ring-1 ring-brand-gold/40 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-brand">Offered to borrower</dt>
                    <dd class="font-bold text-brand mt-1">{{ format_money((float) $record->offered_amount) }}</dd>
                </div>
            @else
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Offered to borrower</dt>
                    <dd class="font-medium text-gray-500 mt-1">Not issued yet</dd>
                </div>
            @endif
            @if ($record->recommended_amount)
                <div class="rounded-lg bg-brand-muted/50 ring-1 ring-brand/10 px-3 py-3">
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

        @if ($isCommitteeStage && ($availableActions ?? collect())->isNotEmpty())
            <div class="mt-4 border-t border-brand-gold/30 pt-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand">Committee actions</p>
                    <a href="{{ route('admin.loan-applications.pre-approvals') }}"
                       class="text-xs font-semibold text-brand hover:underline">
                        Committee queue →
                    </a>
                </div>
                @include('admin.loan-applications._workflow_actions')
            </div>
        @endif
    </div>
</div>
