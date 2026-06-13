@php
    $rec = $review['recommendation'] ?? [];
    $affordPass = (bool) ($affordability['pass'] ?? false);
    $affordFail = ($affordability['verdict'] ?? '') === 'fail' || ! $affordPass;
    $counter = $counterOffer ?? ($review['counter_offer'] ?? null);
@endphp

<div id="review-recommendation" class="scroll-mt-24 mb-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Credit committee &amp; offer</h3>
            <p class="text-xs text-gray-500 mt-0.5">Recommendation, counter-offer, and borrower response</p>
        </div>
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
                Offer: {{ str_replace('_', ' ', ucfirst($rec['offer_status'])) }}
            </span>
        @endif
    </div>

    <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-4">
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Requested</dt>
            <dd class="font-bold text-gray-900 mt-1">{{ format_money((float) $record->requested_amount) }}</dd>
        </div>
        @if ($record->recommended_amount)
            <div class="rounded-lg bg-sky-50 ring-1 ring-sky-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-sky-700">Recommended</dt>
                <dd class="font-bold text-sky-900 mt-1">{{ format_money((float) $record->recommended_amount) }}</dd>
            </div>
        @endif
        @if ($record->offered_amount)
            <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-amber-700">Offered to borrower</dt>
                <dd class="font-bold text-amber-900 mt-1">{{ format_money((float) $record->offered_amount) }}</dd>
            </div>
        @endif
        @if ($counter && ($counter['amount'] ?? 0) > 0)
            <div class="rounded-lg bg-violet-50 ring-1 ring-violet-100 px-3 py-3">
                <dt class="text-[10px] uppercase tracking-widest text-violet-700">Max affordable (counter)</dt>
                <dd class="font-bold text-violet-900 mt-1">{{ format_money((float) $counter['amount']) }}</dd>
                <dd class="text-[10px] text-violet-700 mt-0.5">Est. {{ format_money((float) ($counter['installment'] ?? 0)) }}/mo</dd>
            </div>
        @endif
    </dl>

    @if (! empty($rec['type']))
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-4 py-3 text-sm mb-3">
            <p class="font-semibold text-gray-900">
                Recommendation:
                <span class="capitalize">{{ str_replace('_', ' ', $rec['type']) }}</span>
            </p>
            @if (! empty($rec['remarks']))
                <p class="text-gray-600 mt-1">{{ $rec['remarks'] }}</p>
            @endif
            @if (! empty($rec['recommended_by']))
                <p class="text-xs text-gray-500 mt-2">
                    By {{ $rec['recommended_by']->name ?? 'Staff' }}
                    @if (! empty($rec['recommended_at']))
                        · {{ $rec['recommended_at']->format('d M Y, H:i') }}
                    @endif
                </p>
            @endif
        </div>
    @elseif ($affordFail && ($record->current_stage ?? '') === 'credit_appraisal')
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

    @if ($record->alternative_loan_product_id && $record->alternativeProduct)
        <p class="text-sm text-sky-800 bg-sky-50 ring-1 ring-sky-100 rounded-lg px-4 py-3 mt-3">
            Asset-backed alternative suggested:
            <span class="font-semibold">{{ $record->alternativeProduct->name }}</span>
        </p>
    @endif
</div>
