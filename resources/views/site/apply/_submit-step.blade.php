{{-- Submit step — final confirmation --}}
<div x-show="stepKey === 'submit'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.submit')"
        :title="__('borrower.apply.submit_step.title')"
        :subtitle="__('borrower.apply.submit_step.subtitle')"
    />

    <x-site.kyc-gate-banner :apply-requirements="$applyRequirements ?? null" variant="hint" class="mb-6" />

    <div x-show="supplementMode" x-cloak class="glass-card rounded-2xl ring-1 ring-sky-200 bg-gradient-to-br from-sky-50 to-white px-5 py-4 text-sm text-sky-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.submit_step.supplement_title') }}</p>
        <p class="mt-1 text-sky-800">{{ __('borrower.apply.submit_step.supplement_hint') }}</p>
    </div>

    <div x-show="canApply && !supplementMode" x-cloak class="glass-card rounded-2xl ring-1 ring-emerald-200 bg-gradient-to-br from-emerald-50 to-white px-5 py-5 mb-6">
        <div class="flex items-start gap-3">
            <span class="size-10 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center text-lg shrink-0">✓</span>
            <div>
                <p class="text-sm font-semibold text-emerald-900">{{ __('borrower.apply.submit_step.signed_title') }}</p>
                <p class="text-sm text-emerald-800 mt-1">{{ __('borrower.apply.submit_step.signed_hint') }}</p>
            </div>
        </div>
    </div>

    <section class="glass-card overflow-hidden ring-1 ring-brand/15 mb-6">
        <div class="bg-gradient-to-r from-brand-muted/40 to-white px-5 py-3 border-b border-brand/10 flex items-center justify-between gap-3">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.submit_step.summary_title') }}</p>
            <button type="button"
                    x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details')"
                    @click="gotoKey(hasStep('asset_details') ? 'asset_details' : (hasStep('quote') ? 'quote' : 'asset_tenure'))"
                    class="text-xs font-semibold text-brand hover:underline shrink-0">
                {{ __('borrower.apply.submit_step.edit_quote') }}
            </button>
        </div>
        <dl class="grid sm:grid-cols-2 text-sm">
            <div class="px-5 py-4 border-b sm:border-r border-gray-100">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.product') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900" x-text="current?.name || '—'"></dd>
            </div>
            <div class="px-5 py-4 border-b border-gray-100">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.loan_amount') }}</dt>
                <dd class="mt-1 font-semibold tabular-nums text-gray-900" x-text="formatTzs(form.requested_amount)"></dd>
            </div>
            <div class="px-5 py-4 sm:border-r border-gray-100">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.duration') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500" x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></dt>
                <dd class="mt-1 font-semibold tabular-nums text-brand" x-text="formatTzs(reviewSummary.installment_amount ?? quote.primary ?? quote.monthly)"></dd>
            </div>
        </dl>
    </section>

    <div x-show="hasStep('guarantor') && form.guarantor_mode && form.guarantor_mode !== 'none'" x-cloak
         class="glass-card rounded-2xl ring-1 ring-amber-200 bg-gradient-to-br from-amber-50 to-white px-5 py-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">{{ __('borrower.apply.review_step.guarantor_section') }}</p>
                <p class="text-base font-semibold text-amber-950 mt-1" x-text="review.guarantorName || guarantorSummaryText()"></p>
                <p class="text-sm text-amber-900 mt-2 font-semibold">{{ __('borrower.apply.submit_step.guarantor_pending_title') }}</p>
                <p class="text-sm text-amber-800 mt-1">{{ __('borrower.apply.submit_step.guarantor_pending_hint') }}</p>
            </div>
            <div class="flex flex-col gap-2 shrink-0">
                <button type="button"
                        @click="gotoKey('guarantor')"
                        class="inline-flex justify-center bg-white ring-1 ring-amber-300 text-amber-950 font-semibold px-4 py-2 rounded-xl text-sm">
                    {{ __('borrower.apply.submit_step.view_guarantor') }}
                </button>
                <button type="button"
                        @click="gotoKey('guarantor'); $nextTick(() => { if (isGuarantorLocked()) changeGuarantor(); })"
                        :disabled="guarantorChanging"
                        class="inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2 rounded-xl text-sm disabled:opacity-60">
                    {{ __('borrower.apply.change_guarantor') }}
                </button>
            </div>
        </div>
    </div>

    <div x-show="borrowerSignature?.signature_data" x-cloak class="mb-6 glass-card p-5 ring-1 ring-gray-200/80">
        <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold mb-2">{{ __('borrower.apply.signature_draw_label') }}</p>
        <p class="text-sm font-semibold text-gray-900 mb-3" x-text="borrowerSignature?.signer_name || verifiedLegalName"></p>
        <img :src="borrowerSignature?.signature_data" alt="" class="max-h-32 border border-gray-200 rounded-xl bg-white">
    </div>

    <div x-show="draftReference" class="glass-card rounded-2xl bg-brand-muted/30 ring-1 ring-brand/15 px-5 py-4 text-sm text-gray-700 mb-6">
        {{ __('borrower.apply.submit_step.reference') }}:
        <span class="font-mono font-semibold text-gray-900" x-text="draftReference"></span>
    </div>

    <input type="hidden" name="signature_data" data-submit-signature>
    <input type="hidden" name="signer_name" data-submit-signer>
    <input type="hidden" name="consent" value="1">
</div>
