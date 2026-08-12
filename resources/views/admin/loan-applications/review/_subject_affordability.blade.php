@php
    $isGuarantor = (bool) ($review['is_guarantor_subject'] ?? false);
    $isMember = (bool) ($review['is_member_subject'] ?? false);
    $afford = $isGuarantor
        ? ($review['guarantor_row']['affordability'] ?? null)
        : ($affordability ?? $review['affordability'] ?? null);
    $counter = ($isGuarantor || $isMember) ? null : ($counterOffer ?? $review['counter_offer'] ?? null);
    $capacityLabel = match (true) {
        $isGuarantor => 'Guarantor',
        $isMember => 'Member',
        default => 'Borrower',
    };
@endphp

<section class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $capacityLabel }} · Capacity</p>
            <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Affordability</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                @if ($isGuarantor)
                    Can this guarantor carry the instalment if the borrower fails?
                @elseif ($isMember)
                    Can this group member service their share from available income?
                @else
                    Can the borrower service the proposed instalment from available income?
                @endif
            </p>
        </div>
        @if (! empty($afford))
            @php
                $affPass = (bool) ($afford['pass'] ?? false);
                $affWarn = ($afford['verdict'] ?? '') === 'warn';
            @endphp
            <span @class([
                'inline-flex items-center rounded-full px-3 py-1.5 text-xs font-bold ring-1',
                'bg-emerald-100 text-emerald-900 ring-emerald-200' => $affPass && ! $affWarn,
                'bg-amber-100 text-amber-900 ring-amber-200' => $affWarn,
                'bg-rose-100 text-rose-900 ring-rose-200' => ! $affPass && ! $affWarn,
            ])>
                @if ($affPass && ! $affWarn)
                    Pass
                @elseif ($affWarn)
                    Near limit
                @else
                    Fail
                @endif
            </span>
        @endif
    </div>
    <div class="p-5">
        @if (empty($afford))
            <p class="text-sm text-gray-500">
                @if ($isGuarantor)
                    Affordability unlocks after the guarantor profile is complete.
                @elseif ($isMember)
                    Affordability unlocks after the member profile is complete.
                @else
                    Affordability data is not available for this file yet.
                @endif
            </p>
        @else
            <div class="mb-4 grid sm:grid-cols-3 gap-2 text-sm">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Income</p>
                    <p class="font-bold text-gray-900 mt-0.5">{{ format_money($afford['net_income'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Available capacity</p>
                    <p class="font-bold text-gray-900 mt-0.5">{{ format_money($afford['available_capacity'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Proposed instalment</p>
                    <p class="font-bold text-gray-900 mt-0.5">{{ format_money($afford['proposed_installment'] ?? $afford['new_emi'] ?? 0) }}</p>
                </div>
            </div>
            @include('admin.loan-applications.review._affordability-summary', [
                'affordability' => $afford,
                'counterOffer' => $counter,
                'embedded' => true,
                'record' => $record,
            ])
        @endif
    </div>
</section>
