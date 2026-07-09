{{-- Review step with 3 in-step pages. Alpine: reviewPage, review, form, repaymentSchedule, etc. --}}
<div x-show="stepKey === 'review'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.review_step.eyebrow')"
        :title="__('borrower.apply.review_step.title')"
        :subtitle="__('borrower.apply.review_step.subtitle')"
    />

    <x-site.kyc-gate-banner :apply-requirements="$applyRequirements ?? null" variant="submit" class="mb-6" />

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <template x-for="page in reviewPageCount" :key="'review-page-' + page">
            <button type="button"
                    @click="setReviewPage(page)"
                    class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="reviewPage === page ? 'bg-brand text-white shadow-sm' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-brand-muted/40'">
                <span class="size-5 rounded-full grid place-items-center text-[10px] font-bold"
                      :class="reviewPage === page ? 'bg-white/20' : 'bg-gray-100 text-gray-500'"
                      x-text="page"></span>
                <span x-text="page === 1 ? @js(__('borrower.apply.review_step.page_overview')) : (page === 2 ? @js(__('borrower.apply.review_step.page_terms')) : @js(__('borrower.apply.review_step.page_schedule')))"></span>
            </button>
        </template>
        <p class="ml-auto text-[10px] uppercase tracking-widest text-gray-400 font-semibold"
           x-text="@js(__('borrower.apply.review_step.page_of', ['current' => '__C__', 'total' => '__T__'])).replace('__C__', String(reviewPage)).replace('__T__', String(reviewPageCount))"></p>
    </div>

    {{-- Page 1: Overview — product + borrower + verification --}}
    <div x-show="reviewPage === 1" class="space-y-5">
        <div class="grid lg:grid-cols-2 gap-5">
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
                                {{ __('borrower.marketplace.deposit') }} <span x-text="formatTzs(assetApplication.deposit)"></span>
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
                </dl>
            </section>

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
        </div>

        <section class="glass-card overflow-hidden ring-1 ring-brand/15" x-show="profileSections?.length">
            <div class="px-5 py-3 bg-gradient-to-r from-brand-muted/40 to-white border-b border-brand/10">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.profile_verify.title') }}</h3>
                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.profile_verify.subtitle') }}</p>
            </div>
            <ul class="divide-y divide-gray-100">
                <template x-for="section in (profileSections || [])" :key="section.key">
                    <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900" x-text="section.label"></p>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="section.detail"></p>
                        </div>
                        <span class="shrink-0 size-8 rounded-full grid place-items-center text-sm font-bold"
                              :class="section.complete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                              x-text="section.complete ? '✓' : '!'"></span>
                    </li>
                </template>
            </ul>
        </section>
    </div>

    {{-- Page 2: Terms + guarantor --}}
    <div x-show="reviewPage === 2" x-cloak class="space-y-5">
        <section class="glass-card overflow-hidden ring-1 ring-gray-200/80">
            <div class="px-5 py-3 bg-brand-muted/30 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.loan_section') }}</h3>
                <button type="button"
                        @click="gotoKey(hasStep('asset_details') ? 'asset_details' : (hasStep('group_members') ? 'group_members' : (hasStep('quote') ? 'quote' : 'asset_tenure')))"
                        x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details') || hasStep('group_members')"
                        class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit') }}</button>
            </div>
            <dl class="grid sm:grid-cols-2 divide-y sm:divide-y-0 divide-gray-100 text-sm">
                <div class="px-5 py-4 border-b sm:border-b border-gray-100 sm:border-r" x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details') || hasStep('group_members')">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.loan_amount') }}</dt>
                    <dd class="mt-1 text-lg font-bold tabular-nums text-gray-900" x-text="formatTzs(form.requested_amount)"></dd>
                </div>
                <div class="px-5 py-4 border-b border-gray-100">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.duration') }}</dt>
                    <dd class="mt-1 text-lg font-bold text-gray-900"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</dd>
                </div>
                <div class="px-5 py-4 border-b sm:border-b-0 border-gray-100 sm:border-r">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.interest_rate') }}</dt>
                    <dd class="mt-1 text-lg font-bold text-gray-900" x-text="reviewSummary.monthly_rate_pct ? (reviewSummary.monthly_rate_pct + '% / month') : (current?.rate_label || '—')"></dd>
                </div>
                <div class="px-5 py-4 border-b sm:border-b-0 border-gray-100">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500" x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></dt>
                    <dd class="mt-1 text-lg font-bold tabular-nums text-brand" x-text="formatTzs(reviewSummary.installment_amount ?? quote.primary ?? quote.monthly)"></dd>
                </div>
                <div class="px-5 py-4 sm:col-span-2 border-t border-gray-100 flex flex-wrap justify-between gap-3">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.application_fee') }}</dt>
                        <dd class="mt-1 font-semibold tabular-nums" x-text="formatTzs(reviewSummary.application_fee ?? applicationFee)"></dd>
                    </div>
                    <div x-show="hasStep('group_members')">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group_members.title') }}</dt>
                        <dd class="mt-1 font-semibold"><span x-text="group.members.length"></span> {{ __('borrower.apply.group.fee_breakdown.members') }}</dd>
                    </div>
                    <div x-show="hasStep('asset_details')">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.asset_details.selected_asset') }}</dt>
                        <dd class="mt-1 font-semibold" x-text="selectedCustomerAsset()?.label || assetTypeOptions[form.asset_type] || form.asset_type || '—'"></dd>
                    </div>
                </div>
            </dl>
        </section>

        <section x-show="hasStep('guarantor')" class="glass-card overflow-hidden ring-1 ring-gray-200/80">
            <div class="px-5 py-3 bg-brand-muted/30 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.guarantor_section') }}</h3>
                <button type="button" @click="gotoKey('guarantor')" class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit') }}</button>
            </div>
            <dl class="divide-y divide-gray-100 text-sm">
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
    </div>

    {{-- Page 3: Schedule --}}
    <div x-show="reviewPage === 3" x-cloak>
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
