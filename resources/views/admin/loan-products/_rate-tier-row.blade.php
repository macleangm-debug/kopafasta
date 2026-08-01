@php
    use App\Support\RatePercent;
    $total = is_numeric($row['monthly_rate'] ?? null) && (float) ($row['monthly_rate'] ?? 0) <= 1
        ? (float) $row['monthly_rate']
        : \App\Models\LoanProductRateTier::totalFromComponents(
            RatePercent::toDecimal($row['bot_regulated_rate'] ?? 0),
            RatePercent::toDecimal($row['processing_fee_rate'] ?? 0),
            RatePercent::toDecimal($row['service_fee_rate'] ?? 0),
            RatePercent::toDecimal($row['administration_fee_rate'] ?? 0),
        );
@endphp
<details class="rate-tier-row rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden" {{ ($open ?? false) ? 'open' : '' }}>
    <summary class="cursor-pointer list-none px-4 py-3 flex flex-wrap items-center justify-between gap-2 bg-gray-50 hover:bg-gray-100">
        <span class="text-sm font-semibold text-gray-900">
            <span data-tier-summary-band>{{ format_money((float) ($row['min_amount'] ?? 0)) }} – {{ format_number((float) ($row['max_amount'] ?? 0)) }}</span>
        </span>
        <span class="text-sm text-amber-800">
            Monthly rate: <strong data-tier-summary-total>{{ RatePercent::formatOne($total) }}</strong>
        </span>
    </summary>
    <div class="p-4 space-y-4 border-t border-gray-100">
        <div class="grid md:grid-cols-2 gap-3">
            <x-admin.money-input :name="'rate_tiers['.$index.'][min_amount]'" label="Min amount (TZS)" :value="$row['min_amount']" data-tier-band />
            <x-admin.money-input :name="'rate_tiers['.$index.'][max_amount]'" label="Max amount (TZS)" :value="$row['max_amount']" data-tier-band />
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-700 mb-2">Rate components (% per month)</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">BOT rate</label>
                    <input type="text" inputmode="decimal" name="rate_tiers[{{ $index }}][bot_regulated_rate]" data-tier-component data-tier-bot
                           value="{{ RatePercent::forInput($row['bot_regulated_rate'] ?? 3.5) }}" placeholder="3.5"
                           class="w-full text-sm rounded-lg border-gray-300 px-3 py-2 focus:border-brand focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Processing rate</label>
                    <input type="text" inputmode="decimal" name="rate_tiers[{{ $index }}][processing_fee_rate]" data-tier-component data-tier-processing
                           value="{{ RatePercent::forInput($row['processing_fee_rate'] ?? null) }}" placeholder="5"
                           class="w-full text-sm rounded-lg border-gray-300 px-3 py-2 focus:border-brand focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Risk rate</label>
                    <input type="text" inputmode="decimal" name="rate_tiers[{{ $index }}][service_fee_rate]" data-tier-component data-tier-risk
                           value="{{ RatePercent::forInput($row['service_fee_rate'] ?? null) }}" placeholder="3.5"
                           class="w-full text-sm rounded-lg border-gray-300 px-3 py-2 focus:border-brand focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Insurance rate</label>
                    <input type="text" inputmode="decimal" name="rate_tiers[{{ $index }}][administration_fee_rate]" data-tier-component data-tier-insurance
                           value="{{ RatePercent::forInput($row['administration_fee_rate'] ?? null) }}" placeholder="0"
                           class="w-full text-sm rounded-lg border-gray-300 px-3 py-2 focus:border-brand focus:ring-brand/20">
                </div>
            </div>
        </div>
        <p class="text-sm rounded-lg bg-amber-50 px-4 py-2.5 text-amber-950 ring-1 ring-amber-100">
            Total monthly rate: <strong data-tier-inline-total>{{ RatePercent::formatOne($total) }}</strong>
            <span class="text-xs text-amber-800/80 block mt-0.5">Shown to borrowers for this amount band.</span>
        </p>
        <input type="hidden" name="rate_tiers[{{ $index }}][monthly_rate]" data-tier-monthly-hidden value="{{ $total > 0 ? $total : '' }}">
    </div>
</details>
