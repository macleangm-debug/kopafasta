@php
    $isGuarantor = (bool) ($review['is_guarantor_subject'] ?? false);
    $afford = $isGuarantor
        ? ($review['guarantor_row']['affordability'] ?? null)
        : ($affordability ?? $review['affordability'] ?? null);
    $counter = $isGuarantor ? null : ($counterOffer ?? $review['counter_offer'] ?? null);
@endphp

<section class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $isGuarantor ? 'Guarantor' : 'Borrower' }} · Capacity</p>
        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Affordability</h2>
        <p class="text-xs text-gray-500 mt-0.5">
            @if ($isGuarantor)
                Can this guarantor carry the instalment if the borrower fails?
            @else
                Can the borrower service the proposed instalment from available income?
            @endif
        </p>
    </div>
    <div class="p-5">
        @if (empty($afford))
            <p class="text-sm text-gray-500">
                @if ($isGuarantor)
                    Affordability unlocks after the guarantor profile is complete.
                @else
                    Affordability data is not available for this file yet.
                @endif
            </p>
        @else
            @include('admin.loan-applications.review._affordability-summary', [
                'affordability' => $afford,
                'counterOffer' => $counter,
                'embedded' => true,
                'record' => $record,
            ])
        @endif
    </div>
</section>
