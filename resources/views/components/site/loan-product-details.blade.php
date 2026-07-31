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

            <div class="grid sm:grid-cols-3 gap-3">
                <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/15 px-4 py-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="size-8 rounded-xl bg-brand/10 text-brand grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.details.loan_amount') }}</p>
                    </div>
                    <p class="text-sm font-bold tabular-nums text-gray-900 leading-snug" x-text="formatTzs(readiness.product.min_amount) + ' – ' + formatTzs(readiness.product.max_amount)"></p>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/15 px-4 py-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="size-8 rounded-xl bg-brand/10 text-brand grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.details.tenure') }}</p>
                    </div>
                    <p class="text-sm font-bold tabular-nums text-gray-900 leading-snug" x-text="readiness.product.tenure_min_months + ' – ' + readiness.product.tenure_max_months + ' {{ __('borrower.apply.details.months') }}'"></p>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-brand/10 to-white ring-1 ring-brand/20 px-4 py-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="size-8 rounded-xl bg-brand text-white grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                        </span>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold" x-text="readiness.fees.application_label"></p>
                    </div>
                    <p class="text-sm font-extrabold tabular-nums text-brand leading-snug" x-text="formatTzs(readiness.fees.application)"></p>
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
