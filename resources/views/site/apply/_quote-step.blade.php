{{-- Quote / loan amount step. Keep simple for lay borrowers across all products. --}}
{{-- Use $data.feeGateOpen so a stale JS bundle without feeGateOpen cannot ReferenceError-blank this step. --}}
<div x-show="stepKey === 'quote' && ! $data.feeGateOpen" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.quote.eyebrow')"
        :title="__('borrower.apply.quote.title')"
        :subtitle="__('borrower.apply.quote.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-5">
            <div class="rounded-2xl bg-white ring-1 ring-brand/15 p-5 sm:p-6 space-y-6">
                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.loan_amount') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums" x-text="formatAmount(form.requested_amount)"></span>
                    </div>
                    <input type="range"
                           :min="current.min"
                           :max="current.max"
                           step="50000"
                           x-model.number="form.requested_amount"
                           @input="updateQuote(); scheduleDraftSave()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span x-text="formatAmount(current.min)"></span>
                        <span x-text="formatAmount(current.max)"></span>
                    </div>
                </div>

                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.tenure') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums">
                            <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                        </span>
                    </div>
                    <input type="range"
                           :min="current.tmin"
                           :max="current.tmax"
                           step="1"
                           x-model.number="form.requested_tenure_months"
                           @input="updateQuote(); scheduleDraftSave()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span><span x-text="current.tmin"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                        <span><span x-text="current.tmax"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                    </div>
                </div>

                <x-site.sheet-select
                    model="form.purpose"
                    :label="__('borrower.apply.quote.purpose')"
                    :options="$loanPurposes"
                    :required="true"
                    :placeholder="__('borrower.apply.quote.select_purpose')"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-2xl bg-white ring-1 ring-brand/20 p-5 min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold"
                       x-text="repaymentCadence() === 'monthly'
                           ? @js(__('borrower.apply.quote.monthly_installment_tzs'))
                           : @js(__('borrower.apply.quote.weekly_installment_tzs'))"></p>
                    <p class="text-2xl font-extrabold mt-3 text-gray-900 tabular-nums" x-text="formatAmount(quote.primary ?? quote.monthly)"></p>
                </div>
                <div class="rounded-2xl bg-white ring-1 ring-gray-200/80 p-5 min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.quote.interest_est_tzs') }}</p>
                    <p class="text-2xl font-extrabold mt-3 text-gray-900 tabular-nums" x-text="formatAmount(quote.interest)"></p>
                </div>
                <div class="rounded-2xl bg-white ring-1 ring-gray-200/80 p-5 min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.quote.total_repayment_tzs') }}</p>
                    <p class="text-2xl font-extrabold mt-3 text-gray-900 tabular-nums" x-text="formatAmount(quote.total)"></p>
                </div>
            </div>

            {{-- Rewards only when the borrower can actually redeem something --}}
            <div x-show="canShowQuoteRewards()" x-cloak
                 class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">{{ __('borrower.apply.quote.engagement_title') }}</p>
                    <p class="text-sm text-emerald-900" x-show="hasActiveLoanReward()">
                        {{ __('borrower.apply.quote.active_reward') }}
                    </p>
                    <p class="text-sm text-emerald-900" x-show="!hasActiveLoanReward() && feeLoyaltyOption?.can_redeem">
                        {{ __('borrower.apply.quote.redeem_hint') }}
                    </p>
                </div>
                <a href="{{ route('site.borrower.engagement', ['tab' => 'rewards']) }}"
                   class="shrink-0 text-xs font-semibold text-emerald-900 underline">
                    {{ __('borrower.apply.quote.rewards_cta') }}
                </a>
            </div>
        </div>
    </template>
</div>
