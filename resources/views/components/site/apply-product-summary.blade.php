{{-- Slim product metrics strip (no name/type repeat — header already shows the product). --}}
<div x-show="current" x-cloak class="mb-4 rounded-xl bg-white ring-1 ring-brand/10 px-3.5 sm:px-4 py-2.5">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
        <template x-if="isGroupProduct(current)">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 w-full">
                <div class="min-w-0">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.group.headline.per_member') }}</span>
                    <p class="font-bold tabular-nums text-gray-900" x-text="formatTzs(group.amount_per_member || 0)"></p>
                </div>
                <div class="min-w-0">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.group.headline.total_loan') }}</span>
                    <p class="font-bold tabular-nums text-gray-900" x-text="formatTzs(groupTotalAmount())"></p>
                </div>
                <div class="min-w-0 sm:ml-auto sm:text-right">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.group.headline.total_fee') }}</span>
                    <p class="font-bold tabular-nums text-brand" x-text="formatTzs(groupFeeBreakdown()?.total ?? effectiveFeeAmount())"></p>
                </div>
            </div>
        </template>

        <template x-if="!isGroupProduct(current)">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 w-full">
                <div class="min-w-0">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold" x-text="current?.rate_field_label || @js(__('borrower.apply.product_summary.interest_rate'))"></span>
                    <p class="font-bold text-gray-900" x-text="current?.rate_label || ((current?.rate ?? 0) + '% / mo')"></p>
                </div>
                <div class="min-w-0">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.product_summary.max_tenure') }}</span>
                    <p class="font-bold tabular-nums text-gray-900">
                        <span x-text="current?.tmax ?? current?.tenure_max_months ?? '—'"></span>
                        <span class="font-medium text-gray-500">{{ __('borrower.apply.quote.months') }}</span>
                    </p>
                </div>
                <div class="min-w-0 sm:ml-auto sm:text-right">
                    <span class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.product_summary.application_fee') }}</span>
                    <p class="font-bold tabular-nums text-brand" x-text="formatTzs(current?.application_fee ?? 0)"></p>
                </div>
            </div>
        </template>
    </div>
</div>
