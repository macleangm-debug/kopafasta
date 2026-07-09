{{-- Selected product summary during apply wizard. Parent Alpine: current, formatTzs, isGroupProduct, group, groupTotalAmount, groupFeeBreakdown --}}
<div x-show="current" x-cloak class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
    <div class="bg-gradient-to-r from-brand-muted/40 to-white px-4 sm:px-5 py-4 border-b border-brand/10">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.product_summary.label') }}</p>
                <h2 class="text-lg font-bold text-gray-900 mt-0.5" x-text="current?.name"></h2>
                <p class="text-xs font-semibold text-brand/80 mt-1" x-text="current?.loan_type"></p>
            </div>
            <div class="text-right shrink-0" x-show="!isGroupProduct(current)">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.product_summary.application_fee') }}</p>
                <p class="text-sm font-bold text-gray-900 mt-0.5 tabular-nums" x-text="formatTzs(current?.application_fee ?? 0)"></p>
            </div>
        </div>
    </div>

    <div class="px-4 sm:px-5 py-4">
        <div x-show="isGroupProduct(current)" x-cloak class="grid sm:grid-cols-3 gap-3">
            <div class="rounded-xl bg-brand-muted/20 ring-1 ring-brand/10 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.headline.per_member') }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900 tabular-nums" x-text="formatTzs(group.amount_per_member || 0)"></p>
            </div>
            <div class="rounded-xl bg-brand-muted/20 ring-1 ring-brand/10 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.headline.total_loan') }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900 tabular-nums" x-text="formatTzs(groupTotalAmount())"></p>
            </div>
            <div class="rounded-xl bg-brand-muted/20 ring-1 ring-brand/10 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.headline.total_fee') }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900 tabular-nums" x-text="formatTzs(groupFeeBreakdown()?.total ?? effectiveFeeAmount())"></p>
            </div>
        </div>

        <ul class="space-y-1 text-xs text-gray-700" x-show="current?.features?.length">
            <template x-for="(feature, fi) in current.features" :key="fi">
                <li class="flex gap-2"><span class="text-brand shrink-0">•</span><span x-text="feature"></span></li>
            </template>
        </ul>
    </div>
</div>
