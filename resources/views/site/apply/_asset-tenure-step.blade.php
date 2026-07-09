{{-- Marketplace asset tenure step --}}
<div x-show="stepKey === 'asset_tenure'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.asset_tenure')"
        :title="__('borrower.apply.asset_tenure.title')"
        :subtitle="__('borrower.apply.asset_tenure.subtitle')"
    />

    <template x-if="assetApplication">
        <div class="space-y-6">
            <div class="glass-card overflow-hidden ring-1 ring-brand/15">
                <div class="px-5 py-3 bg-brand-muted/30 border-b border-brand/10">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.asset') }}</p>
                </div>
                <dl class="divide-y divide-gray-100 text-sm px-5">
                    <div class="py-3 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.apply.review_step.asset') }}</dt>
                        <dd class="font-semibold text-right" x-text="assetApplication.asset_title"></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-3" x-show="assetApplication.supplier">
                        <dt class="text-gray-500">{{ __('borrower.marketplace.supplier') }}</dt>
                        <dd class="font-semibold" x-text="assetApplication.supplier"></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.marketplace.asset_value') }}</dt>
                        <dd class="font-semibold tabular-nums" x-text="formatTzs(assetApplication.asset_value)"></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</dt>
                        <dd class="font-semibold tabular-nums" x-text="formatTzs(assetApplication.deposit)"></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.apply.asset_tenure.financed_amount') }}</dt>
                        <dd class="font-semibold tabular-nums text-brand" x-text="formatTzs(assetApplication.remaining_loan)"></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</dt>
                        <dd class="font-semibold tabular-nums" x-text="formatTzs(assetApplication.weekly_installment)"></dd>
                    </div>
                </dl>
            </div>

            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/15">
                <div class="flex items-end justify-between gap-3 mb-3">
                    <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.tenure') }}</label>
                    <span class="text-lg font-extrabold text-brand tabular-nums">
                        <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                    </span>
                </div>
                <input type="range" min="1" :max="assetApplication.max_tenure_months" step="1"
                       x-model.number="form.requested_tenure_months" class="w-full accent-brand h-2 rounded-full">
                <p class="text-xs text-gray-500 mt-3">
                    {{ __('borrower.apply.asset_tenure.max_hint', ['months' => '']) }}
                    <span x-text="assetApplication.max_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                </p>
            </div>
        </div>
    </template>
</div>
