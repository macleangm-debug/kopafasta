{{-- Review step: 3 short premium pages — Overview → Terms → Schedule --}}
<div x-show="stepKey === 'review'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.review_step.eyebrow')"
        :title="__('borrower.apply.review_step.title')"
        :subtitle="__('borrower.apply.review_step.subtitle')"
    />

    <x-site.kyc-gate-banner :apply-requirements="$applyRequirements ?? null" variant="hint" class="mb-6" />

    {{-- Progress rail — sticky within the review step --}}
    <nav class="sticky top-[4.5rem] z-10 -mx-2 px-2 py-3 mb-8 bg-[#faf8f5]/95 backdrop-blur-md border-b border-gray-200/60"
         aria-label="{{ __('borrower.apply.review_step.pages_nav') }}">
        <ol class="flex items-center gap-0">
            <template x-for="page in reviewPageCount" :key="'review-rail-' + page">
                <li class="flex items-center min-w-0" :class="page < reviewPageCount ? 'flex-1' : ''">
                    <button type="button"
                            @click="setReviewPage(page)"
                            class="group flex flex-col items-center gap-1.5 shrink-0 focus:outline-none">
                        <span class="size-8 rounded-full grid place-items-center text-xs font-bold transition ring-2"
                              :class="reviewPage === page
                                  ? 'bg-brand text-white ring-brand shadow-sm'
                                  : (reviewPage > page
                                      ? 'bg-emerald-500 text-white ring-emerald-500'
                                      : 'bg-white text-gray-400 ring-gray-200 group-hover:ring-brand/40 group-hover:text-brand')">
                            <span x-show="reviewPage <= page" x-text="page"></span>
                            <span x-show="reviewPage > page" aria-hidden="true">✓</span>
                        </span>
                        <span class="text-[10px] uppercase tracking-widest font-semibold transition"
                              :class="reviewPage === page ? 'text-brand' : (reviewPage > page ? 'text-emerald-700' : 'text-gray-400')"
                              x-text="page === 1 ? @js(__('borrower.apply.review_step.page_overview')) : (page === 2 ? @js(__('borrower.apply.review_step.page_terms')) : @js(__('borrower.apply.review_step.page_schedule')))"></span>
                    </button>
                    <div x-show="page < reviewPageCount"
                         class="mx-2 sm:mx-3 h-px flex-1 min-w-[1.25rem] transition"
                         :class="reviewPage > page ? 'bg-emerald-400' : 'bg-gray-200'"
                         aria-hidden="true"></div>
                </li>
            </template>
        </ol>
        <p class="mt-3 text-center text-[10px] uppercase tracking-widest text-gray-400 font-semibold"
           x-text="@js(__('borrower.apply.review_step.page_of', ['current' => '__C__', 'total' => '__T__'])).replace('__C__', String(reviewPage)).replace('__T__', String(reviewPageCount))"></p>
    </nav>

    {{-- Page 1: Overview — hero deal strip + who/what --}}
    <div x-show="reviewPage === 1" class="space-y-6">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20">
            <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 45%), radial-gradient(circle at 80% 0%, #fbbf24 0, transparent 35%);"></div>
            <div class="relative px-5 sm:px-7 py-6 sm:py-7">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-white/70">{{ __('borrower.apply.review_step.deal_snapshot') }}</p>
                <p class="mt-2 text-lg sm:text-xl font-semibold tracking-tight" x-text="current ? current.name : '—'"></p>
                <div class="mt-5 grid grid-cols-3 gap-3 sm:gap-6">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.apply.review_step.loan_amount') }}</p>
                        <p class="mt-1 text-base sm:text-2xl font-extrabold tabular-nums tracking-tight" x-text="formatTzs(form.requested_amount)"></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.apply.review_step.duration') }}</p>
                        <p class="mt-1 text-base sm:text-2xl font-extrabold tracking-tight">
                            <span x-text="form.requested_tenure_months"></span>
                            <span class="text-sm font-semibold text-white/70">{{ __('borrower.apply.browse.months_short') }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60"
                           x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></p>
                        <p class="mt-1 text-base sm:text-2xl font-extrabold tabular-nums tracking-tight text-amber-300"
                           x-text="formatTzs(reviewSummary.installment_amount ?? quote.primary ?? quote.monthly)"></p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid lg:grid-cols-2 gap-5">
            <section class="rounded-2xl bg-gradient-to-b from-brand-muted/30 to-white ring-1 ring-brand/10 overflow-hidden">
                <div class="px-5 py-3.5 flex items-center justify-between gap-3 border-b border-brand/10">
                    <h3 class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.product') }}</h3>
                    <a :href="loanProductsUrl" class="text-xs font-semibold text-brand hover:underline shrink-0" x-show="! reservationMode">{{ __('borrower.apply.change') }}</a>
                </div>
                <dl class="px-5 py-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.product') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900" x-text="current ? current.name : '—'"></dd>
                    </div>
                    <template x-if="assetApplication">
                        <div>
                            <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.asset') }}</dt>
                            <dd class="mt-1 font-semibold text-gray-900" x-text="assetApplication.asset_title"></dd>
                            <p class="text-xs text-gray-500 mt-1">
                                <span x-text="formatTzs(assetApplication.asset_value)"></span>
                                · {{ __('borrower.marketplace.deposit') }}
                                <span x-text="formatTzs(assetApplication.deposit)"></span>
                            </p>
                        </div>
                    </template>
                    <div x-show="hasStep('quote')">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.purpose') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900" x-text="purposeLabels[form.purpose] || form.purpose || '—'"></dd>
                    </div>
                    <div x-show="hasStep('group_setup')">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.group_setup.name') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900" x-text="group.name || '—'"></dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl bg-gradient-to-b from-slate-50 to-white ring-1 ring-gray-200/80 overflow-hidden">
                <div class="px-5 py-3.5 flex items-center justify-between gap-3 border-b border-gray-100">
                    <h3 class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.review_step.borrower_section') }}</h3>
                    <a :href="profileUrl" class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit_profile') }}</a>
                </div>
                <dl class="px-5 py-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.personal') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900" x-text="review.personal"></dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.employment') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900" x-text="review.employment"></dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.residence') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900" x-text="review.residence"></dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="rounded-2xl ring-1 ring-brand/15 overflow-hidden" x-show="profileSections?.length">
            <div class="px-5 py-3.5 bg-gradient-to-r from-brand-muted/50 to-white border-b border-brand/10">
                <h3 class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.profile_verify.title') }}</h3>
                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.profile_verify.subtitle') }}</p>
            </div>
            <ul class="divide-y divide-gray-100/80">
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

    {{-- Page 2: Terms — sparse deal numbers + guarantor --}}
    <div x-show="reviewPage === 2" x-cloak class="space-y-6">
        <section class="rounded-3xl overflow-hidden ring-1 ring-brand/15 bg-gradient-to-b from-brand-muted/40 via-white to-white">
            <div class="px-5 sm:px-7 py-4 flex items-center justify-between gap-3 border-b border-brand/10">
                <div>
                    <h3 class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.loan_section') }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.review_step.terms_hint') }}</p>
                </div>
                <button type="button"
                        @click="gotoKey(hasStep('asset_details') ? 'asset_details' : (hasStep('group_members') ? 'group_members' : (hasStep('quote') ? 'quote' : 'asset_tenure')))"
                        x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details') || hasStep('group_members')"
                        class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit') }}</button>
            </div>

            <div class="p-5 sm:p-7 grid sm:grid-cols-2 gap-5 sm:gap-6">
                <div x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details') || hasStep('group_members')">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.review_step.loan_amount') }}</p>
                    <p class="mt-2 text-3xl font-extrabold tabular-nums tracking-tight text-gray-900" x-text="formatTzs(form.requested_amount)"></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.review_step.duration') }}</p>
                    <p class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900">
                        <span x-text="form.requested_tenure_months"></span>
                        <span class="text-base font-semibold text-gray-500">{{ __('borrower.apply.browse.months_short') }}</span>
                    </p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.review_step.interest_rate') }}</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900" x-text="reviewSummary.monthly_rate_pct ? (reviewSummary.monthly_rate_pct + '% / month') : (current?.rate_label || '—')"></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold"
                       x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></p>
                    <p class="mt-2 text-2xl font-extrabold tabular-nums text-brand" x-text="formatTzs(reviewSummary.installment_amount ?? quote.primary ?? quote.monthly)"></p>
                </div>
            </div>

            <div class="mx-5 sm:mx-7 mb-5 sm:mb-7 rounded-2xl bg-slate-50/80 ring-1 ring-gray-100 px-5 py-4 flex flex-wrap gap-x-8 gap-y-3 text-sm">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.review_step.application_fee') }}</p>
                    <p class="mt-1 font-semibold tabular-nums text-gray-900" x-text="formatTzs(reviewSummary.application_fee ?? applicationFee)"></p>
                </div>
                <div x-show="hasStep('group_members')">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.group_members.title') }}</p>
                    <p class="mt-1 font-semibold text-gray-900"><span x-text="group.members.length"></span> {{ __('borrower.apply.group.fee_breakdown.members') }}</p>
                </div>
                <div x-show="hasStep('asset_details')">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.asset_details.selected_asset') }}</p>
                    <p class="mt-1 font-semibold text-gray-900" x-text="selectedCustomerAsset()?.label || assetTypeOptions[form.asset_type] || form.asset_type || '—'"></p>
                </div>
            </div>
        </section>

        <section x-show="hasStep('guarantor')" class="rounded-2xl overflow-hidden ring-1 ring-gray-200/80 bg-white">
            <div class="px-5 py-3.5 flex items-center justify-between gap-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <h3 class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.review_step.guarantor_section') }}</h3>
                <button type="button" @click="gotoKey('guarantor')" class="text-xs font-semibold text-brand hover:underline shrink-0">{{ __('borrower.apply.edit') }}</button>
            </div>
            <dl class="px-5 py-4 grid sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.guarantor_name') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900" x-text="review.guarantorName || '—'"></dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.review_step.guarantor_status') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900" x-text="review.guarantorStatus"></dd>
                </div>
            </dl>
        </section>
    </div>

    {{-- Page 3: Schedule — compact table only --}}
    <div x-show="reviewPage === 3" x-cloak>
        <section class="rounded-3xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm">
            <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-brand-muted/50 to-white border-b border-brand/10">
                <h3 class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.schedule_section') }}</h3>
                <p class="text-xs text-gray-500 mt-1" x-show="!scheduleDatesAvailable">{{ __('borrower.apply.review_step.schedule_before_disbursement') }}</p>
            </div>
            <div class="text-sm">
                <p x-show="scheduleLoading" class="px-5 py-10 text-center text-gray-500">{{ __('borrower.apply.review_step.schedule_loading') }}</p>
                <p x-show="!scheduleLoading && !repaymentSchedule.length" class="px-5 py-10 text-center text-gray-500">{{ __('borrower.apply.review_step.schedule_empty') }}</p>
                <div x-show="!scheduleLoading && repaymentSchedule.length" class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-brand text-white sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold"
                                    x-text="(reviewSummary.repayment_cadence || repaymentCadence()) === 'monthly'
                                        ? @js(__('borrower.apply.review_step.col_month'))
                                        : @js(__('borrower.apply.review_step.col_week'))"></th>
                                <th class="px-4 py-2.5 text-left font-semibold" x-show="scheduleDatesAvailable">{{ __('borrower.apply.review_step.col_due_date') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_principal') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_interest') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_total') }}</th>
                                <th class="px-4 py-2.5 text-right font-semibold">{{ __('borrower.apply.review_step.col_balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(row, idx) in repaymentSchedule" :key="row.installment_no">
                                <tr class="transition"
                                    :class="idx % 2 === 0 ? 'bg-white hover:bg-brand-muted/20' : 'bg-slate-50/70 hover:bg-brand-muted/20'">
                                    <td class="px-4 py-2.5 font-medium tabular-nums" x-text="row.installment_no"></td>
                                    <td class="px-4 py-2.5 whitespace-nowrap" x-show="scheduleDatesAvailable" x-text="row.due_date"></td>
                                    <td class="px-4 py-2.5 text-right tabular-nums" x-text="formatAmount(row.principal_due)"></td>
                                    <td class="px-4 py-2.5 text-right tabular-nums" x-text="formatAmount(row.interest_due)"></td>
                                    <td class="px-4 py-2.5 text-right font-semibold tabular-nums" x-text="formatAmount(row.total_due)"></td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-500" x-text="formatAmount(row.remaining_balance)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
