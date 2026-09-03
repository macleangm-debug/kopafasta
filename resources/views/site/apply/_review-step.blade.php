{{-- Review step: 2 short premium pages — Overview → Schedule --}}
<div x-show="stepKey === 'review' && ! $data.feeGateOpen" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.review_step.eyebrow')"
        :title="__('borrower.apply.review_step.title')"
        :subtitle="__('borrower.apply.review_step.subtitle')"
    />

    {{-- Progress rail — sticky within the review step --}}
    <nav class="sticky top-[4.5rem] z-10 -mx-2 px-2 py-3 mb-8 bg-white/95 backdrop-blur-md border-b border-gray-200/60"
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
                              x-text="page === 1 ? @js(__('borrower.apply.review_step.page_overview')) : @js(__('borrower.apply.review_step.page_schedule'))"></span>
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

    {{-- Page 1: Overview — deal card + guarantor --}}
    <div x-show="reviewPage === 1" class="space-y-6">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20">
            <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 45%), radial-gradient(circle at 80% 0%, #fbbf24 0, transparent 35%);"></div>
            <div class="relative px-5 sm:px-6 py-5 sm:py-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-white/70">{{ __('borrower.apply.review_step.deal_snapshot') }}</p>
                        <p class="mt-1.5 text-base sm:text-lg font-semibold tracking-tight truncate" x-text="current ? current.name : '—'"></p>
                    </div>
                    <a :href="profileUrl"
                       class="shrink-0 text-[11px] font-semibold text-brand-gold/95 hover:text-brand-gold underline-offset-2 hover:underline">
                        {{ __('borrower.apply.edit_profile') }}
                    </a>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2.5 sm:gap-5">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.apply.review_step.loan_amount') }}</p>
                        <p class="mt-1 text-sm sm:text-xl font-extrabold tabular-nums tracking-tight" x-text="formatTzs(form.requested_amount)"></p>
                        <p class="mt-0.5 text-[10px] text-white/55" x-show="isAssetBackedProduct(current)">{{ __('borrower.apply.asset_details.request_label') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.apply.review_step.duration') }}</p>
                        <p class="mt-1 text-sm sm:text-xl font-extrabold tracking-tight">
                            <span x-text="form.requested_tenure_months"></span>
                            <span class="text-xs sm:text-sm font-semibold text-white/70">{{ __('borrower.apply.browse.months_short') }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60"
                           x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></p>
                        <template x-if="isAssetBackedProduct(current)">
                            <p class="mt-1 text-xs sm:text-sm font-semibold text-brand-gold leading-snug">{{ __('borrower.apply.asset_details.repayment_pending_offer') }}</p>
                        </template>
                        <template x-if="!isAssetBackedProduct(current)">
                            <p class="mt-1 text-sm sm:text-xl font-extrabold tabular-nums tracking-tight text-brand-gold"
                               x-text="formatTzs(displayInstallmentAmount())"></p>
                        </template>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-white/15 grid sm:grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                    <div x-show="hasStep('quote') || hasStep('asset_details') || hasStep('group_setup')" class="flex items-baseline justify-between gap-3 sm:block sm:col-span-2">
                        <p class="text-[10px] uppercase tracking-widest text-white/55">{{ __('borrower.apply.review_step.purpose') }}</p>
                        <p class="font-semibold text-white/95 sm:mt-0.5 text-right sm:text-left"
                           x-text="purposeLabels[form.purpose] || form.purpose || (group.purpose ? (purposeLabels[group.purpose] || group.purpose) : '—')"></p>
                        <p x-show="isOtherPurpose() && form.purpose_other"
                           class="text-sm text-white/75 sm:mt-0.5 text-right sm:text-left"
                           x-text="form.purpose_other"></p>
                    </div>
                    <template x-if="assetApplication">
                        <div class="flex items-baseline justify-between gap-3 sm:block">
                            <p class="text-[10px] uppercase tracking-widest text-white/55">{{ __('borrower.apply.review_step.asset') }}</p>
                            <p class="font-semibold text-white/95 sm:mt-0.5 text-right sm:text-left truncate" x-text="assetApplication.asset_title"></p>
                        </div>
                    </template>
                    <div x-show="hasStep('group_setup')" class="flex items-baseline justify-between gap-3 sm:block">
                        <p class="text-[10px] uppercase tracking-widest text-white/55">{{ __('borrower.apply.group_setup.name') }}</p>
                        <p class="font-semibold text-white/95 sm:mt-0.5 text-right sm:text-left truncate" x-text="group.name || '—'"></p>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 sm:block">
                        <p class="text-[10px] uppercase tracking-widest text-white/55">{{ __('borrower.apply.review_step.application_fee') }}</p>
                        <p class="font-semibold tabular-nums text-white/95 sm:mt-0.5 text-right sm:text-left"
                           x-text="formatTzs(reviewSummary.application_fee ?? applicationFee)"></p>
                    </div>
                    <div x-show="review.employment" class="flex items-baseline justify-between gap-3 sm:block">
                        <p class="text-[10px] uppercase tracking-widest text-white/55">{{ __('borrower.apply.review_step.employment') }}</p>
                        <p class="font-medium text-white/90 sm:mt-0.5 text-right sm:text-left text-xs sm:text-sm leading-snug" x-text="review.employment"></p>
                    </div>
                    <div x-show="review.residence" class="flex items-baseline justify-between gap-3 sm:block sm:col-span-2">
                        <p class="text-[10px] uppercase tracking-widest text-white/55">{{ __('borrower.apply.review_step.residence') }}</p>
                        <p class="font-medium text-white/90 sm:mt-0.5 text-right sm:text-left text-xs sm:text-sm leading-snug" x-text="review.residence"></p>
                    </div>
                </div>

                <div x-show="hasStep('guarantor')"
                     class="mt-4 pt-4 border-t border-white/15">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <p class="text-[10px] uppercase tracking-widest text-white/55">{{ __('borrower.apply.review_step.guarantor_section') }}</p>
                        <button type="button"
                                @click="gotoKey('guarantor', { returnTo: 'review' })"
                                class="text-[11px] font-semibold text-brand-gold/95 hover:text-brand-gold underline-offset-2 hover:underline shrink-0">
                            {{ __('borrower.apply.edit') }}
                        </button>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3 sm:block">
                            <p class="text-[10px] uppercase tracking-widest text-white/45">{{ __('borrower.apply.review_step.guarantor_name') }}</p>
                            <p class="font-semibold text-white/95 sm:mt-0.5 text-right sm:text-left" x-text="review.guarantorName || '—'"></p>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 sm:block">
                            <p class="text-[10px] uppercase tracking-widest text-white/45">{{ __('borrower.apply.review_step.guarantor_status') }}</p>
                            <p class="font-semibold text-white/95 sm:mt-0.5 text-right sm:text-left" x-text="review.guarantorStatus"></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl ring-1 ring-brand/15 overflow-hidden" x-show="profileSections?.length">
            <div class="px-5 sm:px-6 py-5 bg-gradient-to-br from-brand-muted/50 to-white">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.profile_verify.title') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('borrower.apply.profile_verify.compact_subtitle') }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-3xl font-extrabold tabular-nums text-brand"
                           x-text="profileCompletionPercent() + '%'"></p>
                        <p class="text-[11px] uppercase tracking-widest text-gray-500 mt-0.5">{{ __('borrower.apply.profile_verify.profile_label') }}</p>
                    </div>
                </div>
                <div class="mt-4 h-2.5 rounded-full bg-white/80 ring-1 ring-brand/10 overflow-hidden">
                    <div class="h-full rounded-full bg-brand transition-all duration-500"
                         :style="'width:' + profileCompletionPercent() + '%'"></div>
                </div>
                <p class="mt-3 text-xs text-gray-500"
                   x-show="profileIncompleteCount() > 0"
                   x-text="profileIncompleteCount() + ' ' + @js(__('borrower.apply.profile_verify.items_left'))"></p>
                <p class="mt-3 text-xs text-emerald-700 font-semibold" x-show="profileIncompleteCount() === 0">
                    {{ __('borrower.apply.profile_verify.all_set') }}
                </p>
                <a :href="profileUrl || '{{ route('site.borrower.profile') }}'"
                   class="mt-4 inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    <span x-text="profileIncompleteCount() > 0 ? @js(__('borrower.apply.profile_verify.complete_cta')) : @js(__('borrower.apply.profile_verify.view_profile_cta'))"></span>
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </section>
    </div>

    {{-- Page 2: Schedule --}}
    <div x-show="reviewPage === 2" x-cloak>
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
                                <th class="px-4 py-2.5 text-right font-semibold"
                                    x-text="current?.hides_interest
                                        ? @js(__('borrower.pricing.sharia.charge_column'))
                                        : @js(__('borrower.apply.review_step.col_interest'))"></th>
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
