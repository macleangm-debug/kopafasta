{{-- Quote / loan amount step. Expects Alpine parent: current, form, quote, updateQuote(), etc. --}}
<div x-show="stepKey === 'quote'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.quote.eyebrow')"
        :title="__('borrower.apply.quote.title')"
        :subtitle="__('borrower.apply.quote.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-6">
            <div class="glass-card overflow-hidden ring-1 ring-brand/15">
                <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 border-b border-brand/10">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.quote.configure') }}</p>
                </div>
                <div class="p-5 sm:p-6 space-y-6">
                    <div>
                        <div class="flex items-end justify-between gap-3 mb-3">
                            <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.loan_amount') }}</label>
                            <span class="text-lg font-extrabold text-brand tabular-nums" x-text="formatTzs(form.requested_amount)"></span>
                        </div>
                        <input type="range"
                               :min="current.min"
                               :max="current.max"
                               step="50000"
                               x-model.number="form.requested_amount"
                               @input="updateQuote()"
                               class="w-full accent-brand h-2 rounded-full">
                        <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                            <span x-text="formatTzs(current.min)"></span>
                            <span x-text="formatTzs(current.max)"></span>
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
                               @input="updateQuote()"
                               class="w-full accent-brand h-2 rounded-full">
                        <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                            <span><span x-text="current.tmin"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                            <span><span x-text="current.tmax"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm">
                        <span class="text-gray-600">{{ __('borrower.apply.quote.repayment_frequency') }}</span>
                        <span class="font-semibold capitalize text-gray-900" x-text="current.frequency || 'monthly'"></span>
                    </div>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="glass-card sm:col-span-2 lg:col-span-2 p-5 ring-1 ring-brand/20 bg-gradient-to-br from-brand/5 to-white">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold"
                       x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.quote.monthly_installment')) : @js(__('borrower.apply.quote.weekly_installment'))"></p>
                    <p class="text-3xl font-extrabold mt-2 text-gray-900 tabular-nums" x-text="formatTzs(quote.primary ?? quote.monthly)"></p>
                </div>
                <div class="glass-card p-5 ring-1 ring-gray-200/80">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.quote.interest_est') }}</p>
                    <p class="text-xl font-bold mt-2 text-gray-900 tabular-nums" x-text="formatTzs(quote.interest)"></p>
                </div>
                <div class="glass-card p-5 ring-1 ring-gray-200/80">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.quote.total_repayment') }}</p>
                    <p class="text-xl font-bold mt-2 text-gray-900 tabular-nums" x-text="formatTzs(quote.total)"></p>
                </div>
            </div>

            <div x-show="(engagementBoosts && (engagementBoosts.factors?.length || qualificationLimit > 0 || engagementBoosts?.rate_discount_fraction > 0)) || processingSla || hasActiveLoanReward() || pointsBalance > 0 || loyaltyRateDiscount > 0" x-cloak
                 class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">{{ __('borrower.apply.quote.engagement_title') }}</p>
                    <p class="text-sm text-emerald-900" x-show="qualificationLimit > 0">
                        {{ __('borrower.apply.quote.engagement_limit') }}:
                        <span class="font-semibold" x-text="formatTzs(qualificationLimit)"></span>
                    </p>
                    <p class="text-sm text-emerald-900" x-show="(engagementBoosts?.rate_discount_fraction || 0) + (loyaltyRateDiscount || 0) > 0">
                        {{ __('borrower.apply.quote.engagement_rate') }}:
                        <span class="font-semibold" x-text="(((engagementBoosts?.rate_discount_fraction || 0) + (loyaltyRateDiscount || 0)) * 100).toFixed(2) + '%'"></span>
                    </p>
                    <p class="text-sm text-emerald-900" x-show="processingSla">
                        {{ __('borrower.apply.quote.engagement_sla') }}:
                        <span class="font-semibold" x-text="processingSla"></span>
                    </p>
                    <p class="text-sm text-emerald-900" x-show="hasActiveLoanReward()">
                        {{ __('borrower.apply.quote.active_reward') }}
                    </p>
                    <p class="text-sm text-emerald-900" x-show="!hasActiveLoanReward() && pointsBalance > 0">
                        {{ __('borrower.apply.quote.redeem_hint') }}
                    </p>
                </div>
                <a href="{{ route('site.borrower.engagement', ['tab' => 'rewards']) }}"
                   class="shrink-0 text-xs font-semibold text-emerald-900 underline">
                    {{ __('borrower.apply.quote.rewards_cta') }}
                </a>
            </div>

            <div x-show="current?.rate_disclosure?.length" class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-xs text-amber-900 space-y-1">
                <p class="font-semibold uppercase tracking-widest text-[10px]">{{ __('borrower.rate_disclosure.title') }}</p>
                <template x-for="(line, idx) in (current.rate_disclosure || [])" :key="idx">
                    <p x-text="line"></p>
                </template>
                <p class="text-amber-800/80 pt-1">{{ __('borrower.rate_disclosure.footnote') }}</p>
            </div>

            <div class="glass-card p-5 ring-1 ring-gray-200/80">
                <x-site.sheet-select
                    model="form.purpose"
                    :label="__('borrower.apply.quote.purpose')"
                    :options="$loanPurposes"
                    :required="true"
                    :placeholder="__('borrower.apply.quote.select_purpose')"
                />
            </div>
        </div>
    </template>
</div>
