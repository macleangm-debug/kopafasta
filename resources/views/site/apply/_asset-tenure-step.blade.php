{{-- Marketplace asset tenure step --}}
<div x-show="stepKey === 'asset_tenure'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.asset_tenure')"
        :title="__('borrower.apply.asset_tenure.title')"
        :subtitle="__('borrower.apply.asset_tenure.subtitle')"
    />

    <template x-if="assetApplication">
        <div class="space-y-6">
            {{-- Premium selected-asset hero --}}
            <div class="overflow-hidden rounded-3xl ring-1 ring-brand/15 bg-gradient-to-br from-brand via-brand to-brand-light shadow-[0_20px_50px_rgba(0,77,64,0.18)]">
                <div class="grid sm:grid-cols-5">
                    <div class="sm:col-span-2 relative min-h-[11rem] sm:min-h-full bg-black/20">
                        <template x-if="assetApplication.photo_url">
                            <img :src="assetApplication.photo_url"
                                 :alt="assetApplication.asset_title"
                                 class="absolute inset-0 h-full w-full object-cover">
                        </template>
                        <template x-if="!assetApplication.photo_url">
                            <div class="absolute inset-0 grid place-items-center bg-gradient-to-br from-brand-muted/40 to-brand/40">
                                <span class="text-4xl opacity-80" aria-hidden="true">🚗</span>
                            </div>
                        </template>
                        <div class="absolute inset-0 bg-gradient-to-t from-brand/70 via-transparent to-transparent sm:bg-gradient-to-r sm:from-transparent sm:via-transparent sm:to-brand/40"></div>
                        <div class="absolute bottom-3 left-3 right-3 sm:hidden">
                            <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('borrower.apply.review_step.asset') }}</p>
                            <p class="text-base font-extrabold text-white truncate" x-text="assetApplication.asset_title"></p>
                        </div>
                    </div>
                    <div class="sm:col-span-3 p-5 sm:p-6 text-white">
                        <p class="hidden sm:block text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('borrower.apply.review_step.asset') }}</p>
                        <h3 class="hidden sm:block text-xl font-extrabold tracking-tight mt-1" x-text="assetApplication.asset_title"></h3>
                        <p class="hidden sm:block text-sm text-white/75 mt-1" x-show="assetApplication.supplier" x-text="assetApplication.supplier"></p>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-white/10 ring-1 ring-white/15 px-3 py-2.5">
                                <dt class="text-[10px] uppercase tracking-widest text-white/65 font-semibold">{{ __('borrower.marketplace.asset_value') }}</dt>
                                <dd class="mt-1 font-bold tabular-nums" x-text="formatTzs(assetApplication.asset_value)"></dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 ring-1 ring-white/15 px-3 py-2.5">
                                <dt class="text-[10px] uppercase tracking-widest text-white/65 font-semibold">{{ __('borrower.marketplace.deposit') }}</dt>
                                <dd class="mt-1 font-bold tabular-nums text-brand-gold" x-text="formatTzs(assetApplication.deposit)"></dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 ring-1 ring-white/15 px-3 py-2.5 col-span-2">
                                <dt class="text-[10px] uppercase tracking-widest text-white/65 font-semibold">{{ __('borrower.apply.asset_tenure.financed_amount') }}</dt>
                                <dd class="mt-1 text-lg font-extrabold tabular-nums" x-text="formatTzs(assetApplication.remaining_loan)"></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Tenure + live installment --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/15">
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.tenure') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums">
                            <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                        </span>
                    </div>
                    <input type="range"
                           min="1"
                           :max="assetApplication.max_tenure_months"
                           step="1"
                           x-model.number="form.requested_tenure_months"
                           @input="updateQuote()"
                           class="w-full accent-brand h-2 rounded-full">
                    <p class="text-xs text-gray-500 mt-3">
                        {{ __('borrower.apply.asset_tenure.max_hint') }}
                        <span x-text="assetApplication.max_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                    </p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-brand-muted/70 to-white ring-1 ring-brand/15 p-5 sm:p-6 flex flex-col justify-center">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('borrower.apply.asset_tenure.installment_preview') }}</p>
                    <p class="mt-2 text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums tracking-tight"
                       x-text="formatTzs(displayInstallmentAmount())"></p>
                    <p class="mt-1 text-xs text-gray-600"
                       x-text="repaymentCadence() === 'weekly'
                           ? @js(__('borrower.apply.asset_tenure.weekly_hint'))
                           : @js(__('borrower.apply.asset_tenure.monthly_hint'))"></p>
                    <p class="mt-3 text-[11px] text-gray-500 leading-relaxed">{{ __('borrower.apply.asset_tenure.installment_changes_hint') }}</p>
                </div>
            </div>
        </div>
    </template>
</div>
