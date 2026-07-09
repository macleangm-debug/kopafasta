{{-- Loan product details + readiness panel. Expects Alpine parent with: readiness, readinessLoading, formatTzs --}}
<div class="p-6 sm:p-8 space-y-6">
    <template x-if="readinessLoading">
        <div class="rounded-2xl bg-brand-muted/20 ring-1 ring-brand/10 p-10 text-center">
            <div class="inline-block size-8 border-2 border-brand/30 border-t-brand rounded-full animate-spin mb-3"></div>
            <p class="text-sm text-gray-600">{{ __('borrower.apply.details.loading') }}</p>
        </div>
    </template>

    <template x-if="! readinessLoading && readiness">
        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.details.heading') }}</p>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1 tracking-tight" x-text="readiness.product.name"></h2>
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand/70 mt-1" x-text="readiness.product.loan_type"></p>
                    <p class="text-sm text-gray-600 mt-2 max-w-xl" x-text="readiness.product.description"></p>
                    <ul class="mt-3 space-y-1 text-sm text-gray-700" x-show="readiness.product.features?.length">
                        <template x-for="(feature, fi) in readiness.product.features" :key="fi">
                            <li class="flex gap-2"><span class="text-brand shrink-0">•</span><span x-text="feature"></span></li>
                        </template>
                    </ul>
                </div>
                <button type="button" @click="backToBrowse()" class="text-sm font-semibold text-gray-600 hover:text-gray-900 shrink-0">
                    {{ __('borrower.apply.details.all_products') }}
                </button>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div class="glass-card p-5 ring-1 ring-brand/10 space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.apply.details.features') }}</h3>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3 py-2 border-b border-gray-100"><dt class="text-gray-500">{{ __('borrower.apply.details.loan_amount') }}</dt><dd class="font-semibold text-right tabular-nums" x-text="formatTzs(readiness.product.min_amount) + ' – ' + formatTzs(readiness.product.max_amount)"></dd></div>
                        <div class="flex justify-between gap-3 py-2 border-b border-gray-100"><dt class="text-gray-500">{{ __('borrower.apply.details.tenure') }}</dt><dd class="font-semibold tabular-nums" x-text="readiness.product.tenure_min_months + ' – ' + readiness.product.tenure_max_months + ' {{ __('borrower.apply.details.months') }}'"></dd></div>
                        <div class="flex justify-between gap-3 py-2 border-b border-gray-100"><dt class="text-gray-500">{{ __('borrower.apply.details.monthly_rate') }}</dt><dd class="font-semibold" x-text="readiness.product.displayed_monthly_rate_label || ((readiness.product.displayed_monthly_rate ?? readiness.product.interest_rate) * 100).toFixed(1) + '%'"></dd></div>
                        <div class="flex justify-between gap-3 py-2 border-b border-gray-100"><dt class="text-gray-500">{{ __('borrower.apply.details.repayment') }}</dt><dd class="font-semibold capitalize" x-text="readiness.product.repayment_frequency || 'weekly'"></dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-gray-500">{{ __('borrower.apply.details.processing_time') }}</dt><dd class="font-semibold" x-text="readiness.processing_time"></dd></div>
                    </dl>
                </div>

                <div class="glass-card p-5 ring-1 ring-brand/10 space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.apply.details.fees') }}</h3>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3 py-2 border-b border-gray-100"><dt class="text-gray-500" x-text="readiness.fees.application_label"></dt><dd class="font-semibold tabular-nums" x-text="formatTzs(readiness.fees.application)"></dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-gray-500" x-text="readiness.fees.post_approval_label"></dt><dd class="font-semibold text-right text-gray-600">{{ __('borrower.apply.details.post_approval_calculated') }}</dd></div>
                        <p class="text-xs text-gray-500" x-text="readiness.fees.post_approval_detail"></p>
                    </dl>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div class="glass-card p-5 ring-1 ring-gray-200/80">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">{{ __('borrower.apply.details.requirements') }}</h3>
                    <ul class="space-y-2.5">
                        <template x-for="req in readiness.requirements" :key="req.key">
                            <li class="flex items-start gap-2.5 text-sm">
                                <span class="shrink-0 mt-0.5 size-5 rounded-full grid place-items-center text-[10px] font-bold"
                                      :class="req.complete ? 'bg-emerald-100 text-emerald-700' : (req.application_step ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700')"
                                      x-text="req.complete ? '✓' : (req.application_step ? '○' : '!')"></span>
                                <span>
                                    <span class="font-medium" x-text="req.label"></span>
                                    <span class="block text-xs text-gray-500" x-text="req.detail"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="glass-card p-5 ring-1 ring-gray-200/80">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">{{ __('borrower.apply.details.documents') }}</h3>
                    <ul class="space-y-2.5">
                        <template x-for="(doc, idx) in readiness.documents" :key="idx">
                            <li class="flex items-start gap-2.5 text-sm">
                                <span class="shrink-0 size-5 rounded-full grid place-items-center text-[10px] font-bold"
                                      :class="doc.complete ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                      x-text="doc.complete ? '✓' : '•'"></span>
                                <span>
                                    <span class="font-medium" x-text="doc.name"></span>
                                    <span class="block text-xs text-gray-500" x-text="doc.detail"></span>
                                </span>
                            </li>
                        </template>
                        <template x-if="! readiness.documents.length">
                            <li class="text-sm text-gray-500">{{ __('borrower.apply.details.documents_default') }}</li>
                        </template>
                    </ul>
                </div>
            </div>

            <template x-if="readiness.product_specific && readiness.product_specific.length">
                <div class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/20 p-5">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-brand mb-3">{{ __('borrower.apply.details.product_specific') }}</h3>
                    <ul class="grid sm:grid-cols-2 gap-3">
                        <template x-for="(item, idx) in readiness.product_specific" :key="idx">
                            <li class="text-sm text-gray-800">
                                <span class="font-medium" x-text="item.label"></span>
                                <span class="block text-xs text-gray-600" x-text="item.detail"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            <div class="rounded-2xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/30 to-white p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-brand">{{ __('borrower.apply.details.eligibility') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('borrower.apply.details.eligibility_hint') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-extrabold tabular-nums"
                           :class="readiness.readiness_level === 'green' ? 'text-emerald-700' : (readiness.readiness_level === 'amber' ? 'text-amber-700' : 'text-red-700')"
                           x-text="readiness.readiness_label"></p>
                        <p class="text-[11px] text-gray-500 uppercase tracking-widest mt-0.5">{{ __('borrower.apply.details.readiness_score') }}</p>
                    </div>
                </div>
                <div class="h-2.5 rounded-full bg-white/80 ring-1 ring-gray-200 overflow-hidden mb-4">
                    <div class="h-full transition-all duration-500 rounded-full"
                         :class="readiness.readiness_level === 'green' ? 'bg-emerald-500' : (readiness.readiness_level === 'amber' ? 'bg-amber-500' : 'bg-red-500')"
                         :style="'width:' + readiness.readiness_percent + '%'"></div>
                </div>
                <template x-if="readiness.missing.length">
                    <div class="mb-5 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                        <p class="text-xs font-semibold text-amber-900 mb-2">{{ __('borrower.apply.details.still_need') }}</p>
                        <ul class="space-y-1">
                            <template x-for="item in readiness.missing" :key="item.key">
                                <li class="text-sm text-amber-800" x-text="'• ' + item.label"></li>
                            </template>
                        </ul>
                    </div>
                </template>
                <div class="flex flex-wrap gap-3">
                    <button type="button" @click="completeMissingRequirements()"
                            x-show="readiness.missing.length"
                            class="inline-flex bg-white hover:bg-gray-50 text-gray-900 font-semibold px-5 py-3 rounded-xl text-sm ring-1 ring-gray-300 transition">
                        {{ __('borrower.apply.details.complete_missing') }}
                    </button>
                    <button type="button" @click="startApplication()"
                            class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-6 py-3 rounded-xl text-sm shadow-sm transition">
                        {{ __('borrower.apply.details.continue_application') }}
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M8 4l6 6-6 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
