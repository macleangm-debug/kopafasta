{{-- Review step. Expects Alpine parent: review, form, group, repaymentSchedule, etc. --}}
<div x-show="stepKey === 'review'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.review_step.eyebrow')"
        :title="__('borrower.apply.review_step.title')"
        :subtitle="__('borrower.apply.review_step.subtitle')"
    />

    <x-site.kyc-gate-banner :apply-requirements="$applyRequirements ?? null" variant="submit" class="mb-6" />

    <div class="space-y-5">
        {{-- Product summary --}}
        <section class="glass-card overflow-hidden ring-1 ring-gray-200/80">
            <div class="px-5 py-3 bg-brand-muted/30 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.product') }}</h3>
                <a :href="loanProductsUrl" class="text-xs font-semibold text-brand hover:underline shrink-0" x-show="! reservationMode">{{ __('borrower.apply.change') }}</a>
            </div>
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="px-5 py-3 flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('borrower.apply.review_step.product') }}</dt>
                    <dd class="font-semibold text-right" x-text="current ? current.name : '—'"></dd>
                </div>
                <template x-if="assetApplication">
                    <div class="px-5 py-3">
                        <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.asset') }}</dt>
                        <dd class="font-semibold" x-text="assetApplication.asset_title"></dd>
                        <p class="text-xs text-gray-500 mt-1">
                            <span x-text="formatTzs(assetApplication.asset_value)"></span> ·
                            {{ __('borrower.marketplace.deposit') }} <span x-text="formatTzs(assetApplication.deposit)"></span> ·
                            <span x-text="assetApplication.max_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}
                        </p>
                    </div>
                </template>
                <div class="px-5 py-3" x-show="hasStep('quote')">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.purpose') }}</dt>
                    <dd class="font-semibold" x-text="purposeLabels[form.purpose] || form.purpose || '—'"></dd>
                </div>
                <div class="px-5 py-3" x-show="hasStep('group_setup')">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.group_setup.name') }}</dt>
                    <dd class="font-semibold" x-text="group.name || '—'"></dd>
                </div>
                <div class="px-5 py-3" x-show="hasStep('group_setup')">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.group_setup.purpose') }}</dt>
                    <dd class="font-semibold" x-text="purposeLabels[group.purpose] || group.purpose || '—'"></dd>
                </div>
            </dl>
        </section>

        {{-- Borrower --}}
        <section class="glass-card overflow-hidden ring-1 ring-gray-200/80">
            <div class="px-5 py-3 bg-brand-muted/30 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.borrower_section') }}</h3>
                <a :href="profileUrl" class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit_profile') }}</a>
            </div>
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.personal') }}</dt>
                    <dd class="font-semibold" x-text="review.personal"></dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.employment') }}</dt>
                    <dd class="font-semibold" x-text="review.employment"></dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.residence') }}</dt>
                    <dd class="font-semibold" x-text="review.residence"></dd>
                </div>
            </dl>
        </section>

        {{-- Loan terms --}}
        <section class="glass-card overflow-hidden ring-1 ring-gray-200/80">
            <div class="px-5 py-3 bg-brand-muted/30 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.loan_section') }}</h3>
                <button type="button"
                        @click="gotoKey(hasStep('asset_details') ? 'asset_details' : (hasStep('group_members') ? 'group_members' : (hasStep('quote') ? 'quote' : 'asset_tenure')))"
                        x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details') || hasStep('group_members')"
                        class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit') }}</button>
            </div>
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="px-5 py-3" x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details') || hasStep('group_members')">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.loan_amount') }}</dt>
                    <dd class="font-semibold tabular-nums" x-text="formatTzs(form.requested_amount)"></dd>
                </div>
                <div class="px-5 py-3" x-show="hasStep('group_members')">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.group_members.title') }}</dt>
                    <dd class="font-semibold">
                        <span x-text="group.members.length"></span> {{ __('borrower.apply.group.fee_breakdown.members') }}
                    </dd>
                </div>
                <div class="px-5 py-3" x-show="hasStep('asset_details')">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.asset_details.selected_asset') }}</dt>
                    <dd class="font-semibold" x-text="selectedCustomerAsset()?.label || assetTypeOptions[form.asset_type] || form.asset_type || '—'"></dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.duration') }}</dt>
                    <dd class="font-semibold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.interest_rate') }}</dt>
                    <dd class="font-semibold" x-text="reviewSummary.monthly_rate_pct ? (reviewSummary.monthly_rate_pct + '% / month') : '—'"></dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.application_fee') }}</dt>
                    <dd class="font-semibold tabular-nums" x-text="formatTzs(reviewSummary.application_fee ?? applicationFee)"></dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1" x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></dt>
                    <dd class="font-semibold tabular-nums text-brand" x-text="formatTzs(reviewSummary.installment_amount ?? quote.primary ?? quote.monthly)"></dd>
                </div>
            </dl>
        </section>

        {{-- Guarantor --}}
        <section x-show="hasStep('guarantor')" class="glass-card overflow-hidden ring-1 ring-gray-200/80">
            <div class="px-5 py-3 bg-brand-muted/30 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.guarantor_section') }}</h3>
                <button type="button" @click="gotoKey('guarantor')" class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit') }}</button>
            </div>
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.guarantor_type') }}</dt>
                    <dd class="font-semibold" x-text="review.guarantorType"></dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.guarantor_name') }}</dt>
                    <dd class="font-semibold" x-text="review.guarantorName || '—'"></dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-gray-500 block mb-1">{{ __('borrower.apply.review_step.guarantor_status') }}</dt>
                    <dd class="font-semibold" x-text="review.guarantorStatus"></dd>
                </div>
            </dl>
        </section>

        {{-- Repayment schedule --}}
        <section class="glass-card overflow-hidden ring-1 ring-gray-200/80">
            <div class="px-5 py-3 bg-brand-muted/30 border-b border-gray-100">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.schedule_section') }}</h3>
                <p class="text-xs text-gray-500 mt-1" x-show="!scheduleDatesAvailable">{{ __('borrower.apply.review_step.schedule_before_disbursement') }}</p>
            </div>
            <div class="text-sm">
                <p x-show="scheduleLoading" class="px-5 py-8 text-center text-gray-500">{{ __('borrower.apply.review_step.schedule_loading') }}</p>
                <p x-show="!scheduleLoading && !repaymentSchedule.length" class="px-5 py-8 text-center text-gray-500">{{ __('borrower.apply.review_step.schedule_empty') }}</p>
                <div x-show="!scheduleLoading && repaymentSchedule.length" class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-brand text-white">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold">{{ __('borrower.apply.review_step.col_installment') }}</th>
                                <th class="px-4 py-2.5 text-left font-semibold" x-show="scheduleDatesAvailable">{{ __('borrower.apply.review_step.col_due_date') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_principal') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_interest') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_total') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in repaymentSchedule" :key="row.installment_no">
                                <tr class="hover:bg-brand-muted/20 transition">
                                    <td class="px-4 py-2.5 font-medium" x-text="row.label || row.installment_no"></td>
                                    <td class="px-4 py-2.5 whitespace-nowrap" x-show="scheduleDatesAvailable" x-text="row.due_date"></td>
                                    <td class="px-4 py-2.5 text-right tabular-nums" x-text="formatTzs(row.principal_due)"></td>
                                    <td class="px-4 py-2.5 text-right tabular-nums" x-text="formatTzs(row.interest_due)"></td>
                                    <td class="px-4 py-2.5 text-right font-semibold tabular-nums" x-text="formatTzs(row.total_due)"></td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-500" x-text="formatTzs(row.remaining_balance)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
