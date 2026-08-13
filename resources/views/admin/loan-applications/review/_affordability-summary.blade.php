@if (! empty($affordability))
    @php
        $pass = (bool) ($affordability['pass'] ?? false);
        $warn = ($affordability['verdict'] ?? '') === 'warn';
        $embedded = $embedded ?? false;
        $cardClass = $pass && ! $warn
            ? 'bg-emerald-50 ring-emerald-200'
            : ($warn ? 'bg-amber-50 ring-amber-200' : 'bg-red-50 ring-red-200');
        $badgeClass = $pass && ! $warn
            ? 'bg-emerald-100 text-emerald-800'
            : ($warn ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
    @endphp
    <div @class([
        'rounded-xl ring-1 p-5' => ! $embedded,
        $cardClass => ! $embedded,
        'mt-4 mb-2' => ! $embedded,
        'space-y-4' => $embedded,
    ])>
        @unless ($embedded)
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Affordability summary</h3>
                    <p class="text-xs text-gray-600 mt-0.5">
                        One-third income rule · max repayment {{ number_format($affordability['repayment_ratio_pct'] ?? 33.33, 2) }}% of monthly income
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-xs font-bold rounded-full px-3 py-1.5 {{ $badgeClass }}">
                    @if ($pass && ! $warn)
                        ✓ Pass
                    @elseif ($warn)
                        ⚠ Near limit
                    @else
                        ✗ Fail
                    @endif
                </span>
            </div>
        @endunless

        <dl class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">
                    {{ ($affordability['income_basis'] ?? '') === 'statement' ? 'Statement monthly' : 'Monthly income' }}
                </dt>
                <dd class="font-semibold text-gray-900 mt-1">{{ format_money($affordability['net_income'] ?? 0) }}</dd>
                @if (($affordability['income_basis'] ?? '') === 'statement')
                    <dd class="text-[11px] text-gray-500 mt-0.5">
                        {{ format_money($affordability['statement_deposits_total'] ?? 0) }}
                        over {{ (int) ($affordability['statement_months'] ?? 6) }} mo
                        · ≈ {{ format_money($affordability['statement_weekly'] ?? 0) }}/week
                    </dd>
                @endif
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Existing commitments</dt>
                <dd class="font-semibold text-gray-900 mt-1">{{ format_money($affordability['existing_obligations'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Max repayment</dt>
                <dd class="font-semibold text-gray-900 mt-1">{{ format_money($affordability['max_repayment_capacity'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Available capacity</dt>
                <dd class="font-semibold text-gray-900 mt-1">{{ format_money($affordability['available_capacity'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Proposed installment</dt>
                <dd class="font-semibold {{ ($affordability['pass'] ?? false) ? 'text-emerald-800' : 'text-red-800' }} mt-1">
                    {{ format_money($affordability['proposed_installment'] ?? $affordability['new_emi'] ?? 0) }}
                </dd>
            </div>
        </dl>

        <p class="text-xs {{ ($affordability['pass'] ?? false) ? 'text-emerald-800' : 'text-red-800' }}">
            Status: <span class="font-semibold">{{ $affordability['status_label'] ?? ($affordability['pass'] ? 'Affordability Passed' : 'Affordability Failed') }}</span>
            · {{ $affordability['reason'] ?? '' }}
        </p>

        @php
            $capacityCeiling = (float) ($affordability['max_affordable_principal'] ?? ($counterOffer['amount'] ?? 0));
            $counterEnabled = app(\App\Services\UnderwritingSettingsService::class)->counterOffersEnabled();
        @endphp
        @if ($capacityCeiling > 0)
            <p class="text-xs text-violet-800 bg-violet-50 ring-1 ring-violet-100 rounded-lg px-3 py-2">
                @if ($counterEnabled && ! ($affordability['pass'] ?? false))
                    System counter-offer:
                @else
                    Max this income can support:
                @endif
                <span class="font-bold">{{ format_money($capacityCeiling) }}</span>
                @if (! empty($counterOffer['tenure_months']) || $record->requested_tenure_months)
                    over {{ $counterOffer['tenure_months'] ?? $record->requested_tenure_months }} months
                @endif
                @if (! empty($counterOffer['installment']))
                    (est. {{ format_money((float) $counterOffer['installment']) }}/month)
                @endif
                @if (! $counterEnabled)
                    · counter-offers are off — used for the decision only
                @endif
            </p>
        @endif
    </div>
@endif
