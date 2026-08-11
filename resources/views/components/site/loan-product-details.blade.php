{{-- Product details phase — compact essentials + continue. Alpine: readiness, readinessLoading, formatTzs, assetApplication --}}
<div class="p-4 sm:p-5 space-y-4">
    <template x-if="readinessLoading">
        <div class="rounded-xl bg-brand-muted/20 ring-1 ring-brand/10 p-8 text-center">
            <div class="inline-block size-7 border-2 border-brand/30 border-t-brand rounded-full animate-spin mb-2"></div>
            <p class="text-sm text-gray-600">{{ __('borrower.apply.details.loading') }}</p>
        </div>
    </template>

    <template x-if="! readinessLoading && readiness">
        <div class="space-y-4">
            {{-- Asset lending: show the chosen asset, not generic product ranges --}}
            <template x-if="assetApplication">
                <div class="space-y-4">
                    <div class="overflow-hidden rounded-2xl ring-1 ring-brand/15 bg-gradient-to-br from-brand via-brand to-brand-light">
                        <div class="grid sm:grid-cols-5">
                            <div class="sm:col-span-2 relative min-h-[9rem] bg-black/20">
                                <template x-if="assetApplication.photo_url">
                                    <img :src="assetApplication.photo_url" :alt="assetApplication.asset_title"
                                         class="absolute inset-0 h-full w-full object-cover">
                                </template>
                                <template x-if="!assetApplication.photo_url">
                                    <div class="absolute inset-0 grid place-items-center text-white/70 text-3xl" aria-hidden="true">🚗</div>
                                </template>
                            </div>
                            <div class="sm:col-span-3 p-4 sm:p-5 text-white">
                                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('borrower.apply.review_step.asset') }}</p>
                                <h2 class="text-lg font-extrabold tracking-tight mt-1" x-text="assetApplication.asset_title"></h2>
                                <p class="text-sm text-white/75 mt-1" x-show="assetApplication.supplier" x-text="assetApplication.supplier"></p>
                                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-xl bg-white/10 ring-1 ring-white/15 px-3 py-2">
                                        <dt class="text-white/65 uppercase tracking-widest text-[9px] font-semibold">{{ __('borrower.marketplace.asset_value') }}</dt>
                                        <dd class="font-bold tabular-nums mt-0.5" x-text="formatTzs(assetApplication.asset_value)"></dd>
                                    </div>
                                    <div class="rounded-xl bg-white/10 ring-1 ring-white/15 px-3 py-2">
                                        <dt class="text-white/65 uppercase tracking-widest text-[9px] font-semibold">{{ __('borrower.marketplace.deposit') }}</dt>
                                        <dd class="font-bold tabular-nums text-brand-gold mt-0.5" x-text="formatTzs(assetApplication.deposit)"></dd>
                                    </div>
                                    <div class="rounded-xl bg-white/10 ring-1 ring-white/15 px-3 py-2 col-span-2">
                                        <dt class="text-white/65 uppercase tracking-widest text-[9px] font-semibold">{{ __('borrower.apply.asset_tenure.financed_amount') }}</dt>
                                        <dd class="font-extrabold tabular-nums mt-0.5" x-text="formatTzs(assetApplication.remaining_loan)"></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-stretch gap-2 text-xs">
                        <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-3 py-2.5 min-w-[7.5rem] flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.marketplace.duration_range_label') }}</p>
                            <p class="mt-0.5 font-bold tabular-nums text-gray-900"
                               x-text="'1 – ' + (assetApplication.max_tenure_months || readiness.product.tenure_max_months) + ' {{ __('borrower.apply.details.months') }}'"></p>
                        </div>
                        <div class="rounded-xl bg-brand/10 ring-1 ring-brand/20 px-3 py-2.5 min-w-[7.5rem] flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold" x-text="readiness.fees.application_label"></p>
                            <p class="mt-0.5 font-extrabold tabular-nums text-brand" x-text="formatTzs(readiness.fees.application)"></p>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="!assetApplication">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-gray-900 tracking-tight" x-text="readiness.product.name"></h2>
                            <p class="text-xs text-gray-500 mt-1 max-w-xl line-clamp-2" x-text="readiness.product.description"></p>
                        </div>
                        <button type="button" @click="backToBrowse()" class="text-xs font-semibold text-gray-600 hover:text-gray-900 shrink-0">
                            {{ __('borrower.apply.details.all_products') }}
                        </button>
                    </div>

                    <div class="flex flex-wrap items-stretch gap-2 text-xs">
                        <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-3 py-2.5 min-w-[7.5rem] flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.details.loan_amount') }}</p>
                            <p class="mt-0.5 font-bold tabular-nums text-gray-900" x-text="formatTzs(readiness.product.min_amount) + ' – ' + formatTzs(readiness.product.max_amount)"></p>
                        </div>
                        <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-3 py-2.5 min-w-[7.5rem] flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.details.tenure') }}</p>
                            <p class="mt-0.5 font-bold tabular-nums text-gray-900" x-text="readiness.product.tenure_min_months + ' – ' + readiness.product.tenure_max_months + ' {{ __('borrower.apply.details.months') }}'"></p>
                        </div>
                        <div class="rounded-xl bg-brand/10 ring-1 ring-brand/20 px-3 py-2.5 min-w-[7.5rem] flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold" x-text="readiness.fees.application_label"></p>
                            <p class="mt-0.5 font-extrabold tabular-nums text-brand" x-text="formatTzs(readiness.fees.application)"></p>
                        </div>
                    </div>
                </div>
            </template>

            <div class="rounded-xl ring-1 ring-brand/15 bg-brand-muted/25 px-3.5 py-3 flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.profile_verify.profile_label') }}</p>
                        <p class="text-sm font-extrabold tabular-nums text-brand" x-text="(readiness.profile_percent ?? readiness.readiness_percent ?? 0) + '%'"></p>
                    </div>
                    <div class="h-1.5 rounded-full bg-white/80 ring-1 ring-brand/10 overflow-hidden">
                        <div class="h-full rounded-full bg-brand transition-all duration-500"
                             :style="'width:' + (readiness.profile_percent ?? readiness.readiness_percent ?? 0) + '%'"></div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <a :href="profileUrl || '{{ route('site.borrower.profile') }}'"
                       x-show="(readiness.profile_percent ?? readiness.readiness_percent ?? 100) < 100"
                       class="inline-flex bg-white hover:bg-gray-50 text-gray-900 font-semibold px-3.5 py-2 rounded-lg text-xs ring-1 ring-gray-300 transition">
                        {{ __('borrower.apply.profile_verify.complete_cta') }}
                    </a>
                    <button type="button" @click="startApplication()"
                            class="inline-flex items-center gap-1.5 bg-brand hover:bg-brand-light text-white font-bold px-4 py-2 rounded-lg text-xs shadow-sm transition">
                        {{ __('borrower.apply.details.continue_application') }}
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M8 4l6 6-6 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
