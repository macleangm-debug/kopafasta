{{-- Sleek product details: name, essentials, profile %, continue. Expects Alpine: readiness, readinessLoading, formatTzs --}}
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
                    <p class="text-sm text-gray-600 mt-2 max-w-xl line-clamp-2" x-text="readiness.product.description"></p>
                </div>
                <button type="button" @click="backToBrowse()" class="text-sm font-semibold text-gray-600 hover:text-gray-900 shrink-0">
                    {{ __('borrower.apply.details.all_products') }}
                </button>
            </div>

            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/15 grid sm:grid-cols-3 gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.details.loan_amount') }}</p>
                    <p class="mt-1 text-sm font-bold tabular-nums text-gray-900" x-text="formatTzs(readiness.product.min_amount) + ' – ' + formatTzs(readiness.product.max_amount)"></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.details.tenure') }}</p>
                    <p class="mt-1 text-sm font-bold tabular-nums text-gray-900" x-text="readiness.product.tenure_min_months + ' – ' + readiness.product.tenure_max_months + ' {{ __('borrower.apply.details.months') }}'"></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold" x-text="readiness.fees.application_label"></p>
                    <p class="mt-1 text-sm font-bold tabular-nums text-brand" x-text="formatTzs(readiness.fees.application)"></p>
                </div>
            </div>

            <div class="rounded-2xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 to-white p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.profile_verify.profile_label') }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ __('borrower.apply.details.eligibility_hint') }}</p>
                    </div>
                    <p class="text-3xl font-extrabold tabular-nums text-brand" x-text="(readiness.profile_percent ?? readiness.readiness_percent ?? 0) + '%'"></p>
                </div>
                <div class="h-2.5 rounded-full bg-white/80 ring-1 ring-brand/10 overflow-hidden mb-5">
                    <div class="h-full rounded-full bg-brand transition-all duration-500"
                         :style="'width:' + (readiness.profile_percent ?? readiness.readiness_percent ?? 0) + '%'"></div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a :href="profileUrl || '{{ route('site.borrower.profile') }}'"
                       x-show="(readiness.profile_percent ?? readiness.readiness_percent ?? 100) < 100"
                       class="inline-flex bg-white hover:bg-gray-50 text-gray-900 font-semibold px-5 py-3 rounded-xl text-sm ring-1 ring-gray-300 transition">
                        {{ __('borrower.apply.profile_verify.complete_cta') }}
                    </a>
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
